"""Build a clean source archive without local credentials or generated data."""
from pathlib import Path
import re
import shutil
from zipfile import ZIP_DEFLATED, ZipFile

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / 'dist/cinescope-php.zip'
RELEASE = ROOT / 'dist/cinescope-php'
EXCLUDED = {'gruppe3', '.git', '.agents', '.codex', '.aws', '.idea', '.vscode',
            '__pycache__', 'dist', 'backups', 'logs', 'exports', 'demo'}
ALLOWED = {'.php', '.css', '.js', '.sql', '.json', '.xml', '.md', '.txt', '.svg',
           '.png', '.jpg', '.jpeg', '.webp', '.drawio', '.html', '.bat', '.py', '.yml'}


def release_files():
    for path in sorted(ROOT.rglob('*')):
        relative = path.relative_to(ROOT)
        if path.is_symlink() or any(part in EXCLUDED for part in relative.parts):
            continue
        if not path.is_file() or path.name.startswith('.env') or path.name == 'config.local.php':
            continue
        if path.suffix.lower() in ALLOWED or path.name in ('.gitignore', '.htaccess'):
            yield path, relative


def main():
    files = list(release_files())
    # Reject literal credentials in PHP source. Never print matching secret values.
    credential = re.compile(r"\$(?:apiKey|adminPasswort|dbPass)\s*=\s*['\"][^'\"]+['\"]")
    for path, relative in files:
        if path.suffix == '.php' and credential.search(path.read_text(encoding='utf-8-sig')):
            raise RuntimeError('Literal credential found in: ' + str(relative))
    ARCHIVE.parent.mkdir(exist_ok=True)
    expected = {relative.as_posix() for _, relative in files}
    if RELEASE.exists():
        existing = {p.relative_to(RELEASE).as_posix() for p in RELEASE.rglob('*') if p.is_file()}
        if existing - expected:
            raise RuntimeError('Old release folder contains unexpected files; archive or remove it before rebuilding.')
    for path, relative in files:
        destination = RELEASE / relative
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(path, destination)
    with ZipFile(ARCHIVE, 'w', ZIP_DEFLATED) as archive:
        for path, relative in files:
            archive.write(path, 'cinescope-php/' + relative.as_posix())
    print(f'Created {ARCHIVE.name}: {len(files)} files, {ARCHIVE.stat().st_size:,} bytes')


if __name__ == '__main__':
    main()
