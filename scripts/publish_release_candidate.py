"""Publish a verified deployment package as a GitHub prerelease using Git credentials.

Credentials stay in memory and are never written to files or printed. Uploads are
assembled in a draft; the release is published only after every asset is checked.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
from urllib.error import HTTPError
from urllib.parse import quote
from urllib.request import Request, urlopen
import zipfile

ROOT = Path(__file__).resolve().parents[1]
REPOSITORY = "sujaysreedhar/isro_collection"
API = f"https://api.github.com/repos/{REPOSITORY}"


def git(*args: str) -> str:
    return subprocess.check_output(["git", *args], cwd=ROOT, text=True).strip()


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def publish(archive_path: Path) -> None:
    archive_path = archive_path.resolve()
    if not archive_path.is_relative_to(ROOT / "scripts" / "releases"):
        raise RuntimeError("Publish only packages from scripts/releases/.")
    manifest_path = archive_path.with_suffix(".manifest.json")
    checksum_path = archive_path.with_suffix(".sha256.txt")
    notes_path = archive_path.with_suffix(".notes.txt")
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    version = manifest["version"]
    commit = manifest["source_commit"]
    if not re.fullmatch(r"\d+\.\d+\.\d+-rc\.\d+", version) or not re.fullmatch(r"[0-9a-f]{40}", commit):
        raise RuntimeError("Invalid release version or commit.")
    if manifest["includes_uncommitted_workspace_changes"] or git("rev-parse", "HEAD") != commit:
        raise RuntimeError("Package must match the current committed source.")
    if git("remote", "get-url", "origin").removesuffix(".git") != f"https://github.com/{REPOSITORY}":
        raise RuntimeError("Unexpected repository remote.")
    archive_bytes = archive_path.read_bytes()
    if checksum_path.read_text(encoding="utf-8").strip() != f"{digest(archive_bytes)}  {archive_path.name}":
        raise RuntimeError("Archive checksum does not match.")
    with zipfile.ZipFile(archive_path) as archive:
        if archive.testzip() or json.loads(archive.read("RELEASE-MANIFEST.json")) != manifest:
            raise RuntimeError("Archive integrity or embedded manifest check failed.")
        expected = {entry["path"] for entry in manifest["files"]} | {"RELEASE-MANIFEST.json"}
        members = archive.namelist()
        if len(members) != len(set(members)) or {name for name in members if not name.endswith("/")} != expected:
            raise RuntimeError("Archive entries do not match the manifest.")
        for entry in manifest["files"]:
            data = archive.read(entry["path"])
            if len(data) != entry["bytes"] or digest(data) != entry["sha256"]:
                raise RuntimeError(f"Archive file does not match manifest: {entry['path']}")
        if archive.read("RELEASE-NOTES.txt") != notes_path.read_bytes():
            raise RuntimeError("Release notes do not match the package.")

    environment = os.environ.copy()
    environment.update(GIT_TERMINAL_PROMPT="0", GCM_INTERACTIVE="Never")
    credentials = subprocess.run(
        ["git", "credential", "fill"], cwd=ROOT, env=environment,
        input=f"protocol=https\nhost=github.com\npath={REPOSITORY}.git\n\n",
        capture_output=True, text=True, check=False,
    )
    if credentials.returncode:
        raise RuntimeError("Configured Git credentials are unavailable.")
    values = dict(line.split("=", 1) for line in credentials.stdout.splitlines() if "=" in line)
    token = values.get("password")
    if not token:
        raise RuntimeError("No GitHub credential was returned by Git.")
    headers = {
        "Authorization": f"Bearer {token}", "Accept": "application/vnd.github+json",
        "X-GitHub-Api-Version": "2022-11-28", "User-Agent": "mycollection-release",
    }

    def request(method: str, url: str, body: dict | bytes | None = None,
                content_type: str = "application/json") -> dict | list:
        data = json.dumps(body).encode("utf-8") if isinstance(body, dict) else body
        request_headers = {**headers, "Content-Type": content_type}
        try:
            with urlopen(Request(url, data=data, headers=request_headers, method=method), timeout=60) as response:
                return json.load(response)
        except HTTPError as error:
            if error.code == 404 and method == "GET":
                return {}
            raise RuntimeError(f"GitHub {method} failed with HTTP {error.code}.") from None

    remote_main = request("GET", f"{API}/commits/main")
    if remote_main.get("sha") != commit:
        raise RuntimeError("Push the packaged commit to GitHub main before publishing.")
    tag = "v" + version
    if request("GET", f"{API}/releases/tags/{tag}"):
        raise RuntimeError(f"Release {tag} already exists; refusing to overwrite it.")
    if request("GET", f"{API}/git/ref/tags/{tag}"):
        raise RuntimeError(f"Tag {tag} already exists; refusing to repoint it.")
    draft = request("POST", f"{API}/releases", {
        "tag_name": tag, "target_commitish": commit,
        "name": f"My Collection {version}", "body": notes_path.read_text(encoding="utf-8"),
        "draft": True, "prerelease": True, "make_latest": "false",
    })
    release_id = draft["id"]
    print(json.dumps({"draft_release_id": release_id, "tag": tag}), flush=True)
    upload_url = draft["upload_url"].split("{", 1)[0]
    if not upload_url.startswith(f"https://uploads.github.com/repos/{REPOSITORY}/releases/"):
        raise RuntimeError("Unexpected upload destination; draft left unpublished.")
    uploaded = []
    for path in (archive_path, manifest_path, checksum_path, notes_path):
        data = path.read_bytes()
        asset = request("POST", f"{upload_url}?name={quote(path.name)}", data,
                        "application/zip" if path.suffix == ".zip" else "application/octet-stream")
        remote_digest = asset.get("digest")
        if (asset.get("state") != "uploaded" or asset.get("size") != len(data)
                or remote_digest != "sha256:" + digest(data)):
            raise RuntimeError(f"Uploaded asset failed verification: {path.name}; draft left unpublished.")
        uploaded.append(path.name)
        print(json.dumps({"uploaded": path.name, "bytes": len(data), "sha256_verified": True}), flush=True)
    result = request("PATCH", f"{API}/releases/{release_id}", {
        "draft": False, "prerelease": True, "make_latest": "false",
    })
    if result.get("draft") or not result.get("prerelease"):
        raise RuntimeError("GitHub did not confirm publication as a prerelease.")
    remote_tag = request("GET", f"{API}/commits/{tag}")
    assets = request("GET", f"{API}/releases/{release_id}/assets")
    if remote_tag.get("sha") != commit or {item["name"] for item in assets} != set(uploaded):
        raise RuntimeError("Published release tag or assets failed final verification.")
    print(json.dumps({"published": result["html_url"], "tag": tag,
                      "source_commit": commit, "prerelease": True,
                      "assets": uploaded, "archive_sha256": digest(archive_bytes)}))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("archive", type=Path, help="Verified ZIP under scripts/releases/")
    arguments = parser.parse_args()
    try:
        publish(arguments.archive)
    except Exception as error:
        print(f"Release publishing stopped: {error}", file=sys.stderr)
        sys.exit(1)
