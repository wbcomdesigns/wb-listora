#!/usr/bin/env python3
"""Fail when a settings reader passes an inline default that differs from the central map.

wb_listora_get_setting( $key, $default ) uses wb_listora_get_default_settings() only when the caller
passes null, so an inline literal silently overrides the owner-visible default (the listing page opened
the map at zoom 15 while Settings said 12). Readers should pass no default. A deliberate difference carries `setting-default-ok: reason` on the same line. Usage: check-setting-defaults.py [plugin_dir ...]
"""
import re, sys, pathlib

def literal(v):
    v = v.strip().rstrip(',').strip()
    if re.fullmatch(r"-?\d+(\.\d+)?", v): return float(v)
    if v in ('true', 'false'): return v == 'true'
    if re.fullmatch(r"'[^'\\]*'|\"[^\"\\$]*\"", v): return v[1:-1]
    if re.fullmatch(r"array\(\s*\)|\[\s*\]", v): return []
    return None

def default_map(main):
    src = pathlib.Path(main).read_text()
    m = re.search(r"function wb_listora_get_default_settings\(\)\s*\{\s*return array\((.*?)\n\t\);", src, re.S)
    out = {}
    for line in (m.group(1) if m else '').splitlines():
        km = re.match(r"\s*'([a-z0-9_]+)'\s*=>\s*(.+?),?\s*$", line)
        if km and literal(km.group(2)) is not None:
            out[km.group(1)] = literal(km.group(2))
    return out

root = pathlib.Path(__file__).resolve().parent.parent
defaults = default_map(root / 'wb-listora.php')
roots = [root] + [pathlib.Path(a) for a in sys.argv[1:]]
bad = []
for base in roots:
    for f in base.rglob('*.php'):
        rel = str(f.relative_to(base))
        if rel.startswith(('vendor/', 'node_modules/', 'libs/', 'tests/', 'bin/', 'docs/', 'build/')):
            continue
        for n, line in enumerate(f.read_text(errors='ignore').splitlines(), 1):
            if 'setting-default-ok' in line:
                continue
            for m in re.finditer(r"wb_listora_get_setting\(\s*'([a-z0-9_]+)'\s*,\s*([^,()]+?)\s*\)", line):
                key, raw = m.group(1), m.group(2)
                val = literal(raw)
                if key in defaults and val is not None and val != defaults[key]:
                    bad.append(f"{base.name}/{rel}:{n}  {key}: inline {raw} but the map says {defaults[key]!r}")
for b in bad: print(b)
print(f"{len(bad)} inline default(s) differ from wb_listora_get_default_settings()")
sys.exit(1 if bad else 0)
