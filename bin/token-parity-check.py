#!/usr/bin/env python3
"""Every --listora-* token a stylesheet reads must be declared.

A var() of an undeclared token with no fallback makes the whole declaration
invalid; with a fallback it silently pins a hard-coded value that dark mode
and theme bridges never reach. The only undeclared names allowed are the
override knobs listed in KNOBS: a theme or site sets them, the code reads
them with a fallback.

Usage:
  python3 bin/token-parity-check.py [plugin_dir] [--with <dir> ...] [--self-test]
  --with adds another plugin's declarations (Pro reads Free's tokens).
"""

import os
import re
import sys

KNOBS = {
    'button-fg', 'button-fg-hover', 'card-fav-size', 'field-w', 'field-w-sm', 'font-mono',
    'migration-progress', 'overlay', 'settings-label-col', 'z-sticky',
}
SKIP_DIRS = {'node_modules', 'vendor', 'build', 'docs', 'tests', 'languages', 'libs'}
DECLARED = re.compile(r'(--listora-[\w-]+)\s*:')
EMITTED = re.compile(r'[\'"](--listora-[\w-]+)[\'"]\s*=>')
READ = re.compile(r'var\(\s*--listora-([\w-]+)')


def scan_text(text):
    declared = {m[len('--listora-'):] for m in DECLARED.findall(text) + EMITTED.findall(text)}
    return declared, set(READ.findall(text))


def scan(plugin_dir):
    declared, read = set(), {}
    for root, dirs, files in os.walk(plugin_dir):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS and not d.startswith('.')]
        for name in files:
            if not name.endswith(('.css', '.php', '.js')) or name.endswith(('-rtl.css', '.layer.css', '.min.js')):
                continue
            path = os.path.join(root, name)
            with open(path, encoding='utf-8', errors='ignore') as fh:
                d, r = scan_text(fh.read())
            declared |= d
            for token in r:
                read.setdefault(token, os.path.relpath(path, plugin_dir))
    return declared, read


def undeclared(declared, read):
    return sorted((t, where) for t, where in read.items() if t not in declared and t not in KNOBS)


def self_test():
    d, r = scan_text('.a{--listora-x:1;color:var(--listora-x);margin:var(--listora-y,2px);gap:var(--listora-field-w,1rem)}')
    assert undeclared(d, {t: 'f' for t in r}) == [('y', 'f')], undeclared(d, {t: 'f' for t in r})
    d, _ = scan_text("'--listora-primary' => '#000'")
    assert d == {'primary'}, d
    print('token-parity-check self-test: ok')


def main(argv):
    if '--self-test' in argv:
        self_test()
        return 0
    extra = [argv[i + 1] for i, a in enumerate(argv) if a == '--with']
    paths = [a for i, a in enumerate(argv) if not a.startswith('--') and (i == 0 or argv[i - 1] != '--with')]
    plugin = paths[0] if paths else '.'
    declared, read = scan(plugin)
    for other in extra:
        declared |= scan(other)[0]
    missing = undeclared(declared, read)
    for token, where in missing:
        print(f'UNDECLARED --listora-{token} (read in {where})')
    if missing:
        print('Declare it in src/variables, use an existing token, or add a documented knob to KNOBS.')
        return 1
    print(f'Token parity: {len(read)} tokens read, all declared or documented knobs.')
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
