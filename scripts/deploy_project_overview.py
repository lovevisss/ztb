"""Publish only the project overview files to the existing application server."""

from __future__ import annotations

from datetime import datetime, timezone
import getpass
import hashlib
from pathlib import Path
import subprocess
import sys

import paramiko


PROJECT = "/var/www/ztb"
EXISTING = ["routes/web.php", "resources/js/components/AppSidebar.vue"]
NEW = [
    "app/Http/Controllers/ProcurementProjectOverviewController.php",
    "resources/js/pages/procurement/ProjectOverview.vue",
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
    root = Path(__file__).resolve().parent.parent
    ssh = paramiko.SSHClient()
    ssh.load_host_keys(str(Path.home() / ".ssh" / "known_hosts"))
    ssh.set_missing_host_key_policy(paramiko.RejectPolicy())
    ssh.connect(
        "10.1.12.238",
        username="root",
        password=getpass.getpass("SSH password: "),
        look_for_keys=False,
        allow_agent=False,
        timeout=15,
    )
    maintenance = False
    archive = None
    try:
        sftp = ssh.open_sftp()
        try:
            for relative in EXISTING:
                baseline = subprocess.check_output(["git", "show", f"HEAD:{relative}"], cwd=root)
                with sftp.open(f"{PROJECT}/{relative}", "rb") as remote:
                    current = remote.read()
                if hashlib.sha256(baseline).digest() != hashlib.sha256(current).digest():
                    raise RuntimeError(f"Server file differs from expected baseline: {relative}")
            for relative in NEW:
                try:
                    sftp.stat(f"{PROJECT}/{relative}")
                except FileNotFoundError:
                    continue
                raise RuntimeError(f"Server already has new file: {relative}")

            stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
            archive = f"/var/backups/ztb-overview-release-{stamp}.tar.gz"
            run(ssh, f"tar -czf {archive} -C {PROJECT} {' '.join(EXISTING)} public/build && tar -tzf {archive} >/dev/null")
            print(f"Backed up existing files: {archive}", flush=True)

            run(ssh, f"cd {PROJECT} && php artisan down --no-ansi")
            maintenance = True
            uid = int(run(ssh, "id -u ztb"))
            gid = int(run(ssh, "getent group ztb | cut -d: -f3"))
            for relative in EXISTING + NEW:
                target = f"{PROJECT}/{relative}"
                temporary = target + ".release-part"
                sftp.put(str(root / relative), temporary)
                sftp.chown(temporary, uid, gid)
                sftp.chmod(temporary, 0o644)
                sftp.posix_rename(temporary, target)
            print("Uploaded the four project overview files", flush=True)
        finally:
            sftp.close()

        output = run(ssh, f"cd {PROJECT} && npm run build")
        print("\n".join(output.splitlines()[-5:]), flush=True)
        run(ssh, f"cd {PROJECT} && php artisan optimize:clear --no-ansi")
        route = run(ssh, f"cd {PROJECT} && php artisan route:list --name=procurement.projects.index --no-ansi")
        if "procurement/projects" not in route:
            raise RuntimeError("Project overview route was not registered")
        run(ssh, f"cd {PROJECT} && php artisan up --no-ansi")
        maintenance = False
        print("Project overview is live", flush=True)
        return 0
    except Exception:
        if maintenance:
            if archive:
                try:
                    run(ssh, f"tar -xzf {archive} -C {PROJECT}")
                    print("Restored previous application files", file=sys.stderr, flush=True)
                except Exception as error:
                    print(f"Rollback failed: {error}", file=sys.stderr, flush=True)
            try:
                run(ssh, f"cd {PROJECT} && php artisan up --no-ansi")
            except Exception as error:
                print(f"Could not exit maintenance mode: {error}", file=sys.stderr, flush=True)
        raise
    finally:
        ssh.close()


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as error:
        print(f"Release stopped: {error}", file=sys.stderr)
        sys.exit(1)
