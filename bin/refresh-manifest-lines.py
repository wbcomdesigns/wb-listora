#!/usr/bin/env python3
"""Refresh the file:line refs in audit/manifest.json so they point at the line
that really fires / listens to the named hook.

Usage:  python3 bin/refresh-manifest-lines.py [--check] [REPO_PATH]

Covers hooks_fired[].where (fire), hooks_fired[].consumed_by[].where (listen),
free_filters_hooked[].where (listen), shortcodes[].where, cron[].where and
free_services_consumed[].where. Looks in the cited file first, then the rest of
the plugin. Entries it cannot find are printed and left untouched.
--check writes nothing and exits 1 when any cited line does not contain the
name within +-2 lines (the drift test).
"""
import json, os, re, sys

# A job hook "fires" where it is scheduled: Action Scheduler / WP-Cron call it.
FIRE = re.compile(r'\b((do_action|apply_filters)(_ref_array|_deprecated)?|enqueue_async|schedule_recurring|schedule_single|as_(enqueue_async|schedule_single|schedule_recurring)_action|wp_schedule_(single_)?event)\s*\(')
LISTEN = re.compile(r'\b(add_action|add_filter|has_action|has_filter)\s*\(')
SKIP = ('vendor', 'node_modules', 'build', 'tests', '.git', 'languages')

check = '--check' in sys.argv
args = [a for a in sys.argv[1:] if not a.startswith('--')]
root = os.path.abspath(args[0] if args else os.path.join(os.path.dirname(__file__), '..'))
mpath = os.path.join(root, 'audit', 'manifest.json')
with open(mpath) as fh:
    raw = fh.read()
m = json.loads(raw)

cache = {}
def lines(rel):
    if rel not in cache:
        try:
            with open(os.path.join(root, rel), encoding='utf-8', errors='replace') as fh:
                cache[rel] = fh.read().split('\n')
        except OSError:
            cache[rel] = None
    return cache[rel]

php_files = []
for d, dirs, files in os.walk(root):
    dirs[:] = sorted(x for x in dirs if x not in SKIP)
    php_files += [os.path.relpath(os.path.join(d, f), root) for f in sorted(files) if f.endswith('.php')]

CONST = re.compile(r'\bconst\s+(\w+)\s*=\s*[\'"]([^\'"]+)[\'"]')

def spellings(rel, name):
    """Ways rel names a hook: the quoted string, or self::/static:: of a class
    constant holding it (const HOOK_BATCH = 'wb_listora_bg_import_batch')."""
    out = [r'[\'"]' + re.escape(name) + r'[\'"]']
    for l in lines(rel) or []:
        for const, value in CONST.findall(l):
            if value == name:
                out.append(r'\b(?:self|static)::' + re.escape(const) + r'\b')
    return re.compile('|'.join(out))

def hits(rel, name, mode):
    """1-based line numbers in rel where `name` is named and the call matches mode."""
    ls = lines(rel)
    if not ls:
        return []
    q = spellings(rel, name)
    out = []
    for i, l in enumerate(ls):
        if not q.search(l):
            continue
        ctx = ' '.join(ls[max(0, i - 2):i + 1])
        if mode == 'any' or (mode == 'fire' and FIRE.search(ctx)) or (mode == 'listen' and LISTEN.search(ctx)):
            out.append(i + 1)
    return out

def near(name, rel, ln):
    ls = lines(rel)
    q = spellings(rel, name)
    return bool(ls) and any(name in l or q.search(l) for l in ls[max(0, ln - 3):ln + 2])

stats = {'foreign': 0, 'total': 0, 'drift_before': 0, 'fixed': 0, 'unresolved': []}

def fix(entry, name, mode, label, key='where'):
    where = entry.get(key)
    if not name or not isinstance(where, str) or ':' not in where:
        return
    rel, _, ln = where.rpartition(':')
    if not ln.isdigit() or not rel.endswith('.php'):
        return
    if lines(rel) is None:  # cited file lives in the companion plugin: run the script there
        stats['foreign'] += 1
        return
    stats['total'] += 1
    ln = int(ln)
    if near(name, rel, ln):
        return
    stats['drift_before'] += 1
    found = None
    cands = hits(rel, name, mode)
    if cands:
        found = (rel, min(cands, key=lambda c: abs(c - ln)))
    else:
        for f in php_files:
            if f != rel and (c := hits(f, name, mode)):
                found = (f, c[0])
                break
    if found:
        entry[key] = '%s:%d' % found
        stats['fixed'] += 1
    else:
        stats['unresolved'].append('%s  %s  (%s)' % (label, name, where))

for h in m.get('hooks_fired', []):
    fix(h, h.get('name'), 'fire', 'hooks_fired')
    for c in h.get('consumed_by', []):
        if isinstance(c, dict):
            fix(c, h.get('name'), 'listen', 'consumed_by')
for e in m.get('free_filters_hooked', []):
    fix(e, e.get('filter'), 'listen', 'free_filters_hooked')
for e in m.get('shortcodes', []):
    if isinstance(e, dict):
        fix(e, e.get('tag'), 'any', 'shortcodes')
for e in m.get('cron', []):
    if isinstance(e, dict):
        fix(e, e.get('hook'), 'any', 'cron')
for e in m.get('free_services_consumed', []):
    fix(e, e.get('key'), 'any', 'free_services_consumed')

print('refs in other plugin skipped: %d' % stats['foreign'])
print('refs checked: %d | drifted before: %d | fixed: %d | unresolved: %d' % (
    stats['total'], stats['drift_before'], stats['fixed'], len(stats['unresolved'])))
for u in stats['unresolved']:
    print('  UNRESOLVED', u)
if check:
    sys.exit(1 if stats['drift_before'] else 0)
if stats['fixed']:
    with open(mpath, 'w') as fh:
        fh.write(json.dumps(m, indent=2, ensure_ascii=False) + '\n')
