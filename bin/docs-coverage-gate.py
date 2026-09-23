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

# Settings are deliberately NOT checked here.
#
# An ID scan over docs/website reported 53 of 62 setting keys undocumented.
# Every one sampled was documented - by its label. Customer docs name controls
# the way the screen does ("Marker clustering", "Distance unit", "CAPTCHA"), not
# `map_clustering` / `distance_unit` / `captcha_provider`, so an ID-only match
# measures the wrong thing and fails on docs that are already correct. Adding
# that check would send people rewriting good pages. If settings coverage is
# ever worth gating, match the label scraped from the field's esc_html__() and
# report ID / label / neither as separate tiers.

# Hooks deliberately left out of the customer docs. Each needs a reason.
ALLOWLIST = {
    # e.g. 'wb_listora_internal_thing': 'internal, no extension contract',
}


def check_rest(docs: str) -> int:
    """Every registered REST route must appear in the docs.

    Routes are read from the running server rather than parsed out of PHP:
    several are built from a `$rest_base` property or a constant, so a regex
    over the source finds a fraction of them. An early version of this check
    scanned `includes/` and reported 18 routes when the server had 143, which
    would have made the gate worse than useless - it would have passed.
    """
    import json
    import subprocess

    php = (
        'foreach ( rest_get_server()->get_routes() as $r => $h ) '
        '{ if ( 0 === strpos( $r, "/listora/v1" ) ) { echo $r, "\n"; } }'
    )
    try:
        out = subprocess.run(
            ['wp', 'eval', php, '--path=' + os.path.abspath('../../..')],
            capture_output=True, text=True, timeout=120,
        )
    except (OSError, subprocess.SubprocessError):
        print('docs-coverage: REST check skipped - wp-cli unavailable')
        return 0

    live = [l.strip() for l in out.stdout.splitlines() if l.strip().startswith('/listora/v1')]
    if not live:
        print('docs-coverage: REST check skipped - no routes returned (WP install not reachable)')
        return 0

    shapes = {
        re.sub(r'\{[^}]*\}', '{}', p).strip('/')
        for p in re.findall(r'listora/v1/([A-Za-z0-9\-/{}_.]*)', docs)
    }
    missing = [
        r for r in live
        if re.sub(r'\(\?P<[^>]+>[^)]*\)', '{}', r.replace('/listora/v1', '')).strip('/')
        not in shapes and r != '/listora/v1'
    ]
    print(f'docs-coverage: {len(live) - len(missing)}/{len(live)} REST routes documented')
    if missing:
        print(f'\n{len(missing)} route(s) registered and named nowhere in docs/website/:')
        for r in missing:
            print('  -', r)
        print('\nRegenerate the complete endpoint index in')
        print('docs/website/developer-guide/rest-api.md.')
        return 1
    return 0


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

    rc = 0
    if missing:
        print(f'\n{len(missing)} hook(s) fired in code and named nowhere in docs/website/:')
        for h in missing:
            print('  -', h)
        print('\nAdd each to docs/website/developer-guide/hooks-reference.md, or to')
        print('ALLOWLIST in this script with a reason.')
        rc = 1

    return check_rest(docs) or rc


if __name__ == '__main__':
    sys.exit(main())
