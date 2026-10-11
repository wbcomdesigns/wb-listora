#!/usr/bin/env python3
"""Rebuild audit/derived/cross-plugin-coupling.json (Free and Pro copies): Free-fired hooks Pro listens to.

Run from the Free plugin root with Pro checked out alongside (../wb-listora-pro):
    python3 bin/derive-coupling.py
Only literal hook names are seen; a hook fired through a variable is not paired.
"""
import datetime, glob, hashlib, json, os, re

FREE = '.'
PRO = '../wb-listora-pro'
FIRE = re.compile(r"\b(do_action|apply_filters)(?:_ref_array)?\(\s*'(wb_listora_[a-z0-9_]+)'")
LISTEN = re.compile(r"\badd_(action|filter)\(\s*'(wb_listora_[a-z0-9_]+)'")


def php_files(root, globs):
    out = []
    for g in globs:
        out += [p for p in glob.glob(os.path.join(root, g), recursive=True) if '/vendor/' not in p and '/libs/' not in p and '/tests/' not in p]
    return sorted(set(out))


def scan(files, rx, root):
    found = {}
    digest = hashlib.sha1()
    for path in files:
        text = open(path, encoding='utf-8', errors='replace').read()
        digest.update(text.encode('utf-8', 'replace'))
        for m in rx.finditer(text):
            line = text.count('\n', 0, m.start()) + 1
            found.setdefault(m.group(2), []).append((m.group(1), os.path.relpath(path, root) + ':' + str(line)))
    return found, digest


def main():
    free_files = php_files(FREE, ['wb-listora.php', 'includes/**/*.php', 'blocks/**/*.php', 'templates/**/*.php'])
    pro_files = php_files(PRO, ['wb-listora-pro.php', 'includes/**/*.php', 'blocks/**/*.php', 'templates/**/*.php'])
    fired, d1 = scan(free_files, FIRE, FREE)
    heard, d2 = scan(pro_files, LISTEN, PRO)

    pairs = []
    for hook in sorted(set(fired) & set(heard)):
        kind = 'filter' if fired[hook][0][0] == 'apply_filters' else 'action'
        pairs.append({
            'hook': hook,
            'kind': kind,
            'fired_at': [where for _, where in fired[hook]],
            'listeners': [{'plugin': 'wb-listora-pro', 'where': where} for _, where in heard[hook]],
        })

    out = {
        'name': 'cross-plugin-coupling',
        'computed_at': datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
        'generator': 'bin/derive-coupling.py',
        'input_hash': 'sha1:' + hashlib.sha1(d1.digest() + d2.digest()).hexdigest(),
        '_doc': 'Free-fired wb_listora_* hooks with Pro listeners. Regenerate with bin/derive-coupling.py; never edit by hand.',
        'result': {'free_fires_pro_consumes': pairs, 'summary': {'pair_count': len(pairs)}},
    }
    # Pro keeps the same file for its INV-3 sentinel; both are written from one scan.
    for target in ('audit/derived/cross-plugin-coupling.json', os.path.join(PRO, 'audit/derived/cross-plugin-coupling.json')):
        with open(target, 'w') as fh:
            json.dump(out, fh, indent=2)
            fh.write('\n')
    print('pairs:', len(pairs))


if __name__ == '__main__':
    main()
