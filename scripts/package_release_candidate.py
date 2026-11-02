"""Build and verify a deployment ZIP without copying installation-specific data."""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
from datetime import datetime, timezone
import zipfile

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "scripts" / "releases"
BUILD = OUTPUT / ".build-rc2"
STAGE = BUILD / "application"
VERSION = "1.0.0-rc.2"
NAME = f"mycollection-{VERSION}-2026-11-02"
RUNTIME_DIRS = ("admin", "assets", "bootstrap", "includes", "modules", "themes")
ROOT_FILES = (
    ".htaccess", ".env.example", "index.php", "ajax.php", "ajax_search.php",
    "update.php", "update.sql", "composer.json", "composer.lock",
    "package.json", "package-lock.json",
)
OPERATIONAL_SCRIPTS = (
    "scripts/update.php", "scripts/run_auto_backup.php",
    "scripts/rebuild_search_vocabulary.php",
)
EMPTY_DIRS = (
    "config", "includes/cache", "tmp", "uploads/branding", "uploads/categories",
    "uploads/thumbnails", "uploads/original", "uploads/pdfs", "uploads/panoramics",
)
EXCLUDED_PARTS = {".git", ".github", ".codex", ".agents", ".aws", "node_modules", "__pycache__"}
PRIVATE_SUFFIXES = {".pem", ".key", ".p12", ".pfx", ".bak", ".backup", ".log", ".tmp", ".temp", ".zip"}


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for block in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def source_allowed(path: Path) -> bool:
    relative = path.relative_to(ROOT)
    parts = relative.parts
    if set(parts) & EXCLUDED_PARTS:
        return False
    if relative.as_posix().startswith("includes/cache/"):
        return False
    if path.name.startswith(".env") and path.name != ".env.example":
        return False
    return path.suffix.lower() not in PRIVATE_SUFFIXES


def source_paths() -> list[Path]:
    files = [ROOT / name for name in ROOT_FILES + OPERATIONAL_SCRIPTS]
    files += [ROOT / "uploads/.htaccess", ROOT / "tmp/.htaccess"]
    for folder in RUNTIME_DIRS:
        files += [p for p in (ROOT / folder).rglob("*") if p.is_file() and source_allowed(p)]
    for path in files:
        if not path.is_file() or path.is_symlink():
            raise RuntimeError(f"Missing source or unexpected symbolic link: {path.relative_to(ROOT)}")
    return sorted(files)


def prepare() -> None:
    OUTPUT.mkdir(parents=True, exist_ok=True)
    if BUILD.exists() or (OUTPUT / f"{NAME}.zip").exists():
        raise RuntimeError("Release build/output already exists; refusing to overwrite it.")
    STAGE.mkdir(parents=True)
    copied = {}
    for source in source_paths():
        relative = source.relative_to(ROOT)
        target = STAGE / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, target)
        copied[relative.as_posix()] = sha256(target)
    for folder in EMPTY_DIRS:
        (STAGE / folder).mkdir(parents=True, exist_ok=True)
    changed_paths = set(subprocess.check_output(["git", "diff", "--name-only", "-z", "HEAD"], cwd=ROOT).decode().split("\0"))
    changed_paths.update(subprocess.check_output(["git", "ls-files", "--others", "--exclude-standard", "-z"], cwd=ROOT).decode().split("\0"))
    metadata = {
        "version": VERSION,
        "source_commit": subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip(),
        "includes_uncommitted_workspace_changes": bool(changed_paths.intersection(copied)),
        "source_sha256": copied,
    }
    (BUILD / "source-metadata.json").write_text(json.dumps(metadata, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"stage": str(STAGE), "source_files": len(copied), "config_excluded": not (STAGE / "config/config.php").exists()}))


def assert_archive_member(name: str) -> None:
    parts = Path(name).parts
    if name.startswith(("/", "\\")) or ".." in parts or ":" in name:
        raise RuntimeError(f"Unsafe archive path: {name}")
    if name == "config/config.php" or name.startswith("config/") and not name.endswith("/"):
        raise RuntimeError(f"Configuration included: {name}")
    if set(parts) & EXCLUDED_PARTS:
        raise RuntimeError(f"Development/private path included: {name}")
    if name.startswith("uploads/") and name != "uploads/.htaccess" and not name.endswith("/"):
        raise RuntimeError(f"Uploaded user data included: {name}")
    if name.startswith("includes/cache/") and not name.endswith("/"):
        raise RuntimeError(f"Runtime cache included: {name}")
    if name.startswith("tmp/") and name != "tmp/.htaccess" and not name.endswith("/"):
        raise RuntimeError(f"Temporary file included: {name}")
    if name.startswith("scripts/") and name not in OPERATIONAL_SCRIPTS and not name.endswith("/"):
        raise RuntimeError(f"Development/test script included: {name}")
    basename = Path(name).name
    if basename.startswith(".env") and basename != ".env.example":
        raise RuntimeError(f"Private environment file included: {name}")
    if Path(name).suffix.lower() in PRIVATE_SUFFIXES:
        raise RuntimeError(f"Private or temporary file included: {name}")


def inspect_stage() -> None:
    files = [path for path in STAGE.rglob("*") if path.is_file()]
    for path in files:
        assert_archive_member(path.relative_to(STAGE).as_posix())
    print(json.dumps({"staged_files": len(files), "exclusions_verified": True}))


def finalize() -> None:
    metadata = json.loads((BUILD / "source-metadata.json").read_text(encoding="utf-8"))
    for name, expected_hash in metadata["source_sha256"].items():
        if sha256(ROOT / name) != expected_hash or sha256(STAGE / name) != expected_hash:
            raise RuntimeError(f"Source changed during packaging: {name}")
    required = (
        "themes/custom/search.php", "themes/custom/archive.css", "themes/custom/archive.js",
        "themes/custom/archive-search.js", "themes/custom/partials/archive_helpers.php",
        "themes/custom/assets/archive-hero.webp", "themes/common/dist/tailwind.css",
        "vendor/autoload.php", "vendor/ralouphie/getallheaders/src/getallheaders.php",
    )
    for name in required:
        if not (STAGE / name).is_file():
            raise RuntimeError(f"Required release file missing: {name}")
    notes = f"""My Collection {VERSION}
Release candidate prepared on 2026-11-02.

Includes the current workspace's home, item detail and search UI changes for
Custom / Theme Studio, compiled CSS, application modules, and production PHP
dependencies installed from composer.lock. The source commit is
{metadata['source_commit']}.
{'Uncommitted application changes are included.' if metadata['includes_uncommitted_workspace_changes'] else 'The packaged application source matches this commit.'}
Custom / Theme Studio is light-only, regardless of the system color preference.

DEPLOYMENT
This is an update package for an existing installation. Extract the application
files into a staging copy of that installation, retaining config/config.php,
any private .env file, existing uploads and the existing database. The config
file and uploaded collection media are intentionally absent from this archive.
Ensure uploads/, includes/cache/ and tmp/ remain writable by the application.
The UI changes introduce no database schema or persistent settings migrations;
the existing cumulative update.sql and operational update runner are included.
Select Custom in Theme Studio to use the redesigned pages. Existing configured
hero image/text and display settings continue to take precedence where used.

VERIFICATION
The UI was checked on desktop/mobile using isolated sample data. Search sorting,
pagination, combined filters and the existing search regression checks passed.
Application PHP syntax and Composer autoload/platform requirements were checked
for this package using PHP 8.3.36. The locked dependencies require PHP 8.2 or
newer, plus the extensions listed by Composer and the application's PDO MySQL,
GD and fileinfo extensions. Final target-database integration is still to be
checked with the retained installation configuration.

EXCLUSIONS
config/config.php; private environment/credential files; uploads and caches;
Git/agent metadata; node_modules; local vendor contents; development, preview
and test scripts/artifacts. A clean production vendor directory is bundled.

INTEGRITY
RELEASE-MANIFEST.json records each included file's size and SHA-256 checksum,
excluding the manifest itself. A matching manifest and ZIP SHA-256 checksum
are also provided alongside the archive.
"""
    (STAGE / "RELEASE-NOTES.txt").write_text(notes, encoding="utf-8")
    files = sorted(p for p in STAGE.rglob("*") if p.is_file())
    manifest = {
        "name": "My Collection", "version": VERSION,
        "created_at_utc": datetime.now(timezone.utc).isoformat(),
        "source_commit": metadata["source_commit"],
        "includes_uncommitted_workspace_changes": metadata["includes_uncommitted_workspace_changes"],
        "config_excluded": "config/config.php", "production_dependencies_bundled": True,
        "manifest_excludes_itself": True,
        "files": [{"path": p.relative_to(STAGE).as_posix(), "bytes": p.stat().st_size, "sha256": sha256(p)} for p in files],
    }
    manifest_path = STAGE / "RELEASE-MANIFEST.json"
    manifest_path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    files.append(manifest_path)
    for path in files:
        assert_archive_member(path.relative_to(STAGE).as_posix())
    archive_path = OUTPUT / f"{NAME}.zip"
    with zipfile.ZipFile(archive_path, "x", compression=zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
        for folder in EMPTY_DIRS:
            archive.writestr(folder + "/", b"")
        for path in sorted(files):
            relative = path.relative_to(STAGE).as_posix()
            assert_archive_member(relative)
            archive.write(path, relative)
    with zipfile.ZipFile(archive_path) as archive:
        if archive.testzip() is not None:
            raise RuntimeError("ZIP integrity check failed.")
        members = archive.namelist()
        if len(members) != len(set(members)):
            raise RuntimeError("Duplicate ZIP entries detected.")
        for member in members:
            assert_archive_member(member)
        expected_members = {entry["path"] for entry in manifest["files"]} | {"RELEASE-MANIFEST.json"}
        if {name for name in members if not name.endswith("/")} != expected_members:
            raise RuntimeError("ZIP contents do not match the manifest.")
        for entry in manifest["files"]:
            data = archive.read(entry["path"])
            if len(data) != entry["bytes"] or hashlib.sha256(data).hexdigest() != entry["sha256"]:
                raise RuntimeError(f"ZIP checksum mismatch: {entry['path']}")
    digest = sha256(archive_path)
    shutil.copy2(manifest_path, OUTPUT / f"{NAME}.manifest.json")
    shutil.copy2(STAGE / "RELEASE-NOTES.txt", OUTPUT / f"{NAME}.notes.txt")
    (OUTPUT / f"{NAME}.sha256.txt").write_text(f"{digest}  {archive_path.name}\n", encoding="utf-8")
    print(json.dumps({"archive": str(archive_path), "files": len(files), "bytes": archive_path.stat().st_size, "sha256": digest, "integrity_verified": True, "config_excluded": True}))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("phase", choices=("prepare", "check", "finalize"))
    arguments = parser.parse_args()
    {"prepare": prepare, "check": inspect_stage, "finalize": finalize}[arguments.phase]()
