#!/usr/bin/env python3
"""Validate source/version agreement and exact distributable ZIP contents."""
from pathlib import Path
import json
import re
import zipfile

root = Path(__file__).resolve().parent.parent
plugin = root / 'persona26'
main = (plugin / 'persona26.php').read_text()
version = re.search(r'\* Version:\s*(\S+)', main)[1]
assert re.search(r"define\('P26_VERSION', '([^']+)'\)", main)[1] == version
assert json.loads((root / 'update.json').read_text())['version'] == version
assert re.search(r'^Stable tag:\s*(\S+)', (plugin / 'readme.txt').read_text(), re.M)[1] == version
assert f'## {version} -' in (root / 'CHANGELOG.md').read_text()
assert ' * Plugin URI:' not in main
assert ' * License: GPL v2 or later' in main
assert (plugin / 'LICENSE').is_file()
expected = {str(p.relative_to(root)) for p in plugin.rglob('*') if p.is_file()}
with zipfile.ZipFile(root / 'persona26.zip') as archive:
    assert archive.testzip() is None
    names = {i.filename for i in archive.infolist() if not i.is_dir()}
    assert names == expected, (names - expected, expected - names)
    for name in names:
        assert archive.read(name) == (root / name).read_bytes(), name
        assert not any(part.startswith('.') or part in {'node_modules', '__pycache__'} for part in Path(name).parts), name
        assert not name.endswith(('.zip','.log')), name
assert (root/'persona26.zip').read_bytes() == (root/'dist/persona26.zip').read_bytes()
print(f'PASS: version {version}, required metadata, exact package source match, clean ZIP root and identical delivery artifacts')
