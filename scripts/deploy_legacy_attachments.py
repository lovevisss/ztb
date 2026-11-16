"""Deploy only the legacy attachment feature and prior autocomplete fix."""

from __future__ import annotations

import argparse
from datetime import datetime, timezone
import getpass
from pathlib import Path
import sys

import paramiko


PROJECT = "/var/www/ztb"
FILES = [
    "app/Console/Commands/ManageLegacyAttachments.php",
    "app/Http/Controllers/LegacyAttachmentController.php",
    "app/Http/Controllers/LegacyProcurementController.php",
    "app/Http/Controllers/ProcurementApplicationController.php",
    "app/Services/LegacyAttachmentPath.php",
    "app/Services/LegacyProcurementAccess.php",
    "database/migrations/2026_11_16_000000_add_stored_path_to_legacy_procurement_attachments.php",
    "resources/js/app.ts",
    "resources/js/pages/procurement/LegacyDetail.vue",
    "routes/web.php",
]
OLD_FILES = [
    "app/Http/Controllers/LegacyProcurementController.php",
    "app/Http/Controllers/ProcurementApplicationController.php",
    "resources/js/app.ts",
    "resources/js/pages/procurement/LegacyDetail.vue",
    "routes/web.php",
    "public/build",
]


def run(ssh: paramiko.SSHClient, command: str) -> str:
    _, stdout, stderr = ssh.exec_command(command)
    output = stdout.read().decode("utf-8")
    error = stderr.read().decode("utf-8")
    status = stdout.channel.recv_exit_status()
    if status:
        raise RuntimeError(f"Command failed ({status}): {command}\n{error or output}")
    return output.strip()


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--host", required=True)
    parser.add_argument("--user", default="root")
    parser.add_argument("--expected-revision", default="db862ef")
    args = parser.parse_args()

    root = Path(__file__).resolve().parent.parent
    ssh = paramiko.SSHClient()
    ssh.load_host_keys(str(Path.home() / ".ssh" / "known_hosts"))
    ssh.set_missing_host_key_policy(paramiko.RejectPolicy())
    ssh.connect(
        args.host,
        username=args.user,
        password=getpass.getpass("SSH password: "),
        look_for_keys=False,
        allow_agent=False,
        timeout=15,
    )
    maintenance = False
    archive = None
    try:
        revision = run(ssh, f"git -C {PROJECT} rev-parse --short HEAD")
        if revision != args.expected_revision:
            raise RuntimeError(f"Unexpected server revision: {revision}")
        tracked_changes = run(ssh, f"git -C {PROJECT} diff --name-only")
        if tracked_changes:
            raise RuntimeError(f"Server has tracked changes:\n{tracked_changes}")
        if run(ssh, f"test -d {PROJECT}/node_modules && test -d {PROJECT}/vendor && echo ready") != "ready":
            raise RuntimeError("Missing production dependencies")

        stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        database = f"/var/backups/ztb-{stamp}.sql.gz"
        archive = f"/var/backups/ztb-legacy-release-{stamp}.tar.gz"
        uid = int(run(ssh, "id -u ztb"))
        gid = int(run(ssh, "getent group ztb | cut -d: -f3"))
        sftp = ssh.open_sftp()
        try:
            backup_script = "/root/backup_ztb_database.php"
            sftp.put(str(root / "scripts/backup_ztb_database.php"), backup_script)
            sftp.chmod(backup_script, 0o600)
            print("Backing up production database", flush=True)
            print(run(ssh, f"php {backup_script} {database} && gzip -t {database} && sha256sum {database}"), flush=True)
            print("Backing up application files and assets", flush=True)
            run(ssh, f"tar -czf {archive} -C {PROJECT} {' '.join(OLD_FILES)} && tar -tzf {archive} >/dev/null")

            print("Putting application into maintenance mode", flush=True)
            run(ssh, f"cd {PROJECT} && php artisan down --no-ansi")
            maintenance = True
            for relative in FILES:
                source = root / relative
                destination = f"{PROJECT}/{relative}"
                temporary = destination + ".release-part"
                sftp.put(str(source), temporary)
                sftp.chown(temporary, uid, gid)
                sftp.chmod(temporary, 0o644)
                sftp.posix_rename(temporary, destination)
            print(f"Uploaded {len(FILES)} selected source files", flush=True)
        finally:
            sftp.close()

        print(run(ssh, f"cd {PROJECT} && php artisan migrate --force --no-ansi"), flush=True)
        print("Building production assets", flush=True)
        build_output = run(ssh, f"cd {PROJECT} && npm run build")
        print("\n".join(build_output.splitlines()[-8:]), flush=True)
        run(ssh, f"cd {PROJECT} && php artisan optimize:clear --no-ansi")
        print(run(ssh, f"cd {PROJECT} && php artisan legacy:attachments --export | wc -l"), flush=True)
        run(ssh, f"cd {PROJECT} && php artisan up --no-ansi")
        maintenance = False
        print(f"Release complete; source backup: {archive}; database backup: {database}", flush=True)
        return 0
    except Exception:
        if maintenance:
            if archive is not None:
                try:
                    run(ssh, f"tar -xzf {archive} -C {PROJECT}")
                    print("Restored previous application files", file=sys.stderr, flush=True)
                except Exception as rollback_error:
                    print(f"Application rollback failed: {rollback_error}", file=sys.stderr, flush=True)
            try:
                run(ssh, f"cd {PROJECT} && php artisan up --no-ansi")
            except Exception as up_error:
                print(f"Maintenance mode exit failed: {up_error}", file=sys.stderr, flush=True)
        raise
    finally:
        ssh.close()


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as error:
        print(f"Release stopped: {error}", file=sys.stderr)
        sys.exit(1)
