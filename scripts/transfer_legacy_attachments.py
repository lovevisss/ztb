"""Transfer only indexed legacy attachments into the Laravel private disk.

Requires Paramiko. Reads the inventory from the server, verifies local and
remote SHA-256 values, then asks Laravel to link the verified files.
"""

from __future__ import annotations

import argparse
import getpass
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import sys

import paramiko


PATH_PATTERN = re.compile(r"^upload/\d{4}/\d{2}/\d{2}/[A-Za-z0-9][A-Za-z0-9._-]*$")


def digest_file(path: Path) -> str:
    sha = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            sha.update(chunk)
    return sha.hexdigest()


def remote_digest(sftp: paramiko.SFTPClient, path: str) -> str:
    sha = hashlib.sha256()
    with sftp.open(path, "rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            sha.update(chunk)
    return sha.hexdigest()


def run(ssh: paramiko.SSHClient, command: str) -> str:
    _, stdout, stderr = ssh.exec_command(command)
    output = stdout.read().decode("utf-8")
    error = stderr.read().decode("utf-8")
    code = stdout.channel.recv_exit_status()
    if code:
        raise RuntimeError(f"Remote command failed ({code}): {error or output}")
    return output


def ensure_directory(sftp: paramiko.SFTPClient, path: str, uid: int, gid: int) -> None:
    current = ""
    for component in path.strip("/").split("/"):
        current += "/" + component
        try:
            attrs = sftp.lstat(current)
            if not stat.S_ISDIR(attrs.st_mode):
                raise RuntimeError(f"Remote path is not a directory: {current}")
        except FileNotFoundError:
            sftp.mkdir(current, 0o750)
            sftp.chown(current, uid, gid)
            sftp.chmod(current, 0o2750)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--host", required=True)
    parser.add_argument("--user", default="root")
    parser.add_argument("--local-root", type=Path, default=Path("G:/upload"))
    parser.add_argument("--remote-project", default="/var/www/ztb")
    parser.add_argument("--expected-count", type=int, default=1771)
    args = parser.parse_args()

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
    try:
        project = args.remote_project.rstrip("/")
        export = run(ssh, f"cd {project} && php artisan legacy:attachments --export")
        inventory = [json.loads(line) for line in export.splitlines() if line.strip()]
        if len(inventory) != args.expected_count:
            raise RuntimeError(f"Expected {args.expected_count} database records, found {len(inventory)}")

        manifest = []
        for item in inventory:
            legacy_path = item["legacy_path"].replace("\\", "/")
            if not PATH_PATTERN.fullmatch(legacy_path):
                raise RuntimeError(f"Unsafe attachment path: {legacy_path}")
            local_file = args.local_root / legacy_path[len("upload/") :]
            if not local_file.is_file() or local_file.is_symlink():
                raise RuntimeError(f"Missing or linked local file: {local_file}")
            manifest.append(
                {
                    "id": int(item["id"]),
                    "legacy_path": item["legacy_path"],
                    "size": local_file.stat().st_size,
                    "sha256": digest_file(local_file),
                }
            )
        print(f"Preflight: {len(manifest)} files, {sum(item['size'] for item in manifest)} bytes", flush=True)

        uid = int(run(ssh, "id -u ztb").strip())
        gid = int(run(ssh, "getent group www-data | cut -d: -f3").strip())
        private = f"{project}/storage/app/private"
        sftp = ssh.open_sftp()
        try:
            for index, item in enumerate(manifest, 1):
                relative = item["legacy_path"].replace("\\", "/")[len("upload/") :]
                local_file = args.local_root / relative
                destination = f"{private}/legacy/{relative}"
                ensure_directory(sftp, os.path.dirname(destination), uid, gid)
                try:
                    existing = sftp.lstat(destination)
                    if not stat.S_ISREG(existing.st_mode):
                        raise RuntimeError(f"Remote file is not regular: {destination}")
                    complete = existing.st_size == item["size"] and remote_digest(sftp, destination) == item["sha256"]
                except FileNotFoundError:
                    complete = False
                if not complete:
                    temporary = destination + ".part"
                    sftp.put(str(local_file), temporary, confirm=True)
                    if sftp.stat(temporary).st_size != item["size"] or remote_digest(sftp, temporary) != item["sha256"]:
                        raise RuntimeError(f"Transfer checksum failed: {relative}")
                    sftp.chown(temporary, uid, gid)
                    sftp.chmod(temporary, 0o640)
                    sftp.posix_rename(temporary, destination)
                if index % 100 == 0 or index == len(manifest):
                    print(f"Verified {index}/{len(manifest)}", flush=True)

            manifest_path = f"{private}/legacy-attachments-manifest.jsonl"
            with sftp.open(manifest_path + ".part", "wb") as output:
                for item in manifest:
                    output.write((json.dumps(item, ensure_ascii=False) + "\n").encode("utf-8"))
            sftp.chown(manifest_path + ".part", uid, gid)
            sftp.chmod(manifest_path + ".part", 0o640)
            sftp.posix_rename(manifest_path + ".part", manifest_path)
        finally:
            sftp.close()

        command = f"cd {project} && php artisan legacy:attachments --manifest={manifest_path}"
        print(run(ssh, command + " --dry-run").strip(), flush=True)
        print(run(ssh, command).strip(), flush=True)
        return 0
    finally:
        ssh.close()


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as error:
        print(f"Transfer stopped: {error}", file=sys.stderr)
        sys.exit(1)
