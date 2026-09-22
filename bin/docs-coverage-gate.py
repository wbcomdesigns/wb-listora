#!/usr/bin/env python3
"""Docs coverage gate - code -> docs.

Drift checks ask "does this doc reference something that still exists?" and pass
cleanly while a hook ships with no documentation at all, because there is no doc
to be wrong. This is the other direction.

Fails when a hook fired in the plugin is named nowhere in docs/website/.
Run from the plugin root.
"""
import os, re, sys

HOOK = re.compile(r"(?:apply_filters|do_action)(?:_ref_array)?\(\s*'(wb_listora[a-z0-9_]*)'")
SKIP = {'node_modules', 'vendor', 'build', 'dist', 'libs', 'tests', '.git', 'docs'}

# Hooks deliberately left out of the customer docs. Each needs a reason.
ALLOWLIST = {
    # e.g. 'wb_listora_internal_thing': 'internal, no extension contract',
}


def main() -> int:
    code = set()
    for root, dirs, files in os.walk('.'):
        dirs[:] = [d for d in dirs if d not in SKIP]
        for f in files:
            if f.endswith('.php'):
                try:
                    code |= set(HOOK.findall(open(os.path.join(root, f), encoding='utf-8', errors='ignore').read()))
                except OSError:
                    pass

    if not code:
        print('docs-coverage: no hooks found - the scanner is broken, not the docs')
        return 1

    docs = ''
    for root, _, files in os.walk('docs/website'):
        for f in files:
            if f.endswith('.md'):
                docs += open(os.path.join(root, f), encoding='utf-8', errors='ignore').read()

    missing = sorted(h for h in code if h not in docs and h not in ALLOWLIST)
    covered = len(code) - len(missing)
    print(f'docs-coverage: {covered}/{len(code)} hooks documented ({100 * covered // len(code)}%)')

    if missing:
        print(f'\n{len(missing)} hook(s) fired in code and named nowhere in docs/website/:')
        for h in missing:
            print('  -', h)
        print('\nAdd each to docs/website/developer-guide/hooks-reference.md, or to')
        print('ALLOWLIST in this script with a reason.')
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
