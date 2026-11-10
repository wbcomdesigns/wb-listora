#!/usr/bin/env python3
"""Translation gate: the .pot covers every string in the code, every .po matches the .pot,
and every locale is at least MIN_PERCENT translated. Run from local CI and before a release.

  python3 bin/check-i18n-catalogue.py            # check
  python3 bin/check-i18n-catalogue.py --min=95   # change the floor
"""
import glob, os, re, subprocess, sys, tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SLUG = os.path.basename(ROOT)
MIN = 95
for a in sys.argv[1:]:
    if a.startswith('--min='):
        MIN = int(a[6:])
EXCLUDE = 'node_modules,vendor,libs,tests,build,dist,demo'


def keys(path):
    """Set of (context, msgid, plural) in a catalogue; location and date noise ignored."""
    out = subprocess.run(['msgcat', '--no-location', '--no-wrap', path], capture_output=True, text=True, check=True).stdout
    found, cur = set(), {}
    def flush():
        if 'msgid' in cur and cur['msgid'] != '':
            found.add((cur.get('msgctxt', ''), cur['msgid'], cur.get('msgid_plural', '')))
        cur.clear()
    field = None
    for line in out.split('\n'):
        m = re.match(r'(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?) (".*")$', line)
        if m:
            k = m.group(1)
            if k == 'msgctxt' or (k == 'msgid' and 'msgid' in cur):
                flush()
            if k.startswith('msgstr'):
                field = None
            else:
                field = k
                cur[k] = m.group(2)
        elif line.startswith('"') and field:
            cur[field] += line
        elif not line.strip():
            field = None
    flush()
    return found


fails = []
pot_path = os.path.join(ROOT, 'languages', SLUG + '.pot')
with tempfile.TemporaryDirectory() as tmp:
    fresh = os.path.join(tmp, 'fresh.pot')
    r = subprocess.run(['wp', 'i18n', 'make-pot', ROOT, fresh, '--slug=' + SLUG, '--domain=' + SLUG,
                        '--exclude=' + EXCLUDE, '--skip-audit'], capture_output=True, text=True)
    if r.returncode or not os.path.exists(fresh):
        print('make-pot failed:', r.stderr.strip()[-300:])
        sys.exit(2)
    code, pot = keys(fresh), keys(pot_path)
    for label, diff in (('in code but not in the .pot (run wp i18n make-pot)', code - pot),
                        ('in the .pot but gone from the code (run wp i18n make-pot)', pot - code)):
        if diff:
            fails.append('%d string(s) %s' % (len(diff), label))
            for c, i, p in sorted(diff)[:5]:
                print('   ', i[:100])

for po in sorted(glob.glob(os.path.join(ROOT, 'languages', SLUG + '-*.po'))):
    name = os.path.basename(po)
    if keys(po) != pot:
        fails.append(name + ': msgids differ from the .pot')
    st = subprocess.run(['msgfmt', '--statistics', '-o', '/dev/null', po], capture_output=True, text=True).stderr
    nums = [int(n) for n in re.findall(r'(\d+) (?:translated|fuzzy|untranslated)', st)]
    tr = int((re.search(r'(\d+) translated', st) or [0, 0])[1])
    total = sum(nums)
    pct = 100.0 * tr / total if total else 0
    print('  %-34s %5.1f%%  (%d/%d)' % (name, pct, tr, total))
    if pct < MIN:
        fails.append('%s is %.1f%% translated, floor is %d%%' % (name, pct, MIN))

def bag_keys():
    """Top-level keys of every PHP array localized as `listoraI18n`, or None when the plugin has none."""
    found = None
    for path in glob.glob(os.path.join(ROOT, 'includes', '**', '*.php'), recursive=True):
        src = open(path, encoding='utf-8').read()
        at = src.find("'listoraI18n',")
        if at < 0:
            continue
        start = src.index('array(', at) + len('array')  # the opening parenthesis
        depth, end = 0, start
        while True:
            depth += (src[end] == '(') - (src[end] == ')')
            if depth == 0 and end > start:
                break
            end += 1
        found = found or set()
        depth = 0
        for line in src[start:end].split('\n'):
            m = re.match(r"\s*'([A-Za-z0-9_]+)'\s*=>", line)
            if m and depth == 1:
                found.add(m.group(1))
            depth += line.count('(') - line.count(')')
    return found


# A script that reads window.listoraI18n.<key> falls back to English when PHP never sends the key,
# and make-pot cannot see a JS fallback, so the string would stay English on every translated site.
provided = bag_keys()
if provided is not None:
    used = {}
    for path in glob.glob(os.path.join(ROOT, 'src', '**', '*.js'), recursive=True):
        rel = os.path.relpath(path, ROOT)
        if rel.startswith(os.path.join('src', 'admin')):
            continue
        js = open(path, encoding='utf-8').read()
        for rx in (r"listoraI18n\??\.([A-Za-z0-9_]+)", r"\bt\(\s*'([A-Za-z0-9_]+)'\s*,", r"\btf\(\s*'([A-Za-z0-9_]+)'"):
            for key in re.findall(rx, js):
                used.setdefault(key, rel)
    unsent = sorted(set(used) - provided)
    if unsent:
        fails.append('%d listoraI18n key(s) used in src but never sent by PHP: %s' % (len(unsent), ', '.join(unsent[:8])))
        for key in unsent[:8]:
            print('   ', key, '<-', used[key])

q = subprocess.run(['node', os.path.join(ROOT, 'bin', 'check-i18n-quality.mjs')], capture_output=True, text=True, cwd=ROOT)
if q.returncode:
    fails.append('check-i18n-quality.mjs failed')
    print(q.stdout[-600:])

if fails:
    print('\nFAIL')
    for f in fails:
        print(' -', f)
    sys.exit(1)
print('i18n catalogue gate: ok (floor %d%%)' % MIN)
