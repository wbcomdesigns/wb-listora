#!/usr/bin/env python3
"""Ratchet for docs/standards/code-standard.md.

Counts the measurable smells per plugin and fails when any number is higher
than docs/standards/code-standard-baseline.json. Lower numbers pass; record
them with --update so they cannot creep back.

Usage:
  python3 bin/code-standard-check.py [--plugin <dir>] [--update] [--self-test]
"""

import json
import os
import re
import sys

# bin/ and demo/ ship in the repo and are scanned; docs/ holds throwaway QA artefacts.
SKIP_DIRS = {'vendor', 'libs', 'node_modules', 'tests', 'build', 'languages', 'docs'}
TICKET = re.compile(r'(cards?|Cards?|BC|Basecamp|Zoho|QA)[ #:-]*\d{8,}|#\d{9,}')
DATED = re.compile(r'\b20\d\d-\d\d-\d\d\b')
COMMENT = re.compile(r'\s*(//|\*|/\*)')
# A comment that trails code or sits inside <?php // ... ?>; needs whitespace before it
# so URLs ("http://") and quoted "#fff" strings are not read as comments.
TRAILING = re.compile(r'\s(//|#(?!\[)|/\*)')


def comment_text(line):
    """The comment part of a line, or '' when the line has none."""
    if COMMENT.match(line):
        return line
    hit = TRAILING.search(line)
    return line[hit.start():] if hit else ''
SUPPRESS = re.compile(r'phpcs:(ignore|disable)(.*)')


def measure_lines(path, lines):
    """Metric counts for one PHP file's lines."""
    m = dict.fromkeys(
        ['ticket_refs', 'dated_comments', 'long_comment_blocks', 'files_over_1500', 'wpdb_in_views',
         'function_exists_guards', 'phpcs_ignore_without_reason', 'error_log_calls'], 0)
    # bin/ holds dev tooling: only the comment-history metrics apply there.
    tooling = path.startswith('bin/')
    run = 0
    for line in lines + ['']:
        # Checked before the comment skip: standalone suppressions need a reason too.
        sup = SUPPRESS.search(line)
        if sup and not tooling and '--' not in sup.group(2):
            m['phpcs_ignore_without_reason'] += 1
        note = comment_text(line)
        if note:
            if TICKET.search(note):
                m['ticket_refs'] += 1
            if DATED.search(note):
                m['dated_comments'] += 1
        if COMMENT.match(line):
            run += 1
            continue
        if tooling:
            continue
        if run > 15:
            m['long_comment_blocks'] += 1
        run = 0
        if "function_exists( 'wb_listora" in line:
            m['function_exists_guards'] += 1
        if 'error_log(' in line:
            m['error_log_calls'] += 1
    if tooling:
        return m
    if len(lines) > 1500:
        m['files_over_1500'] = 1
    if re.search(r'(^|/)(blocks|templates)/', path):
        m['wpdb_in_views'] = sum(1 for line in lines if '$wpdb->' in line)
    return m


def measure(plugin_dir):
    total = {}
    for root, dirs, files in os.walk(plugin_dir):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS and not d.startswith('.')]
        for name in files:
            if not name.endswith('.php'):
                continue
            path = os.path.join(root, name)
            with open(path, encoding='utf-8', errors='ignore') as fh:
                lines = fh.read().splitlines()
            for key, val in measure_lines(os.path.relpath(path, plugin_dir), lines).items():
                total[key] = total.get(key, 0) + val
    return total


def self_test():
    long_block = ['// x'] * 16 + ['$a = 1;']
    m = measure_lines('templates/x.php', long_block + [
        '// fixed in card 10379292712',
        "if ( function_exists( 'wb_listora_x' ) ) {",
        '$wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery',
        '$wpdb->query( $q ); // phpcs:ignore WordPress.DB.PreparedSQL -- trusted prefix',
        'error_log( "x" );',
        '$a = 1; // floor-guard (BC #9989784605 family)',
        '<?php // placeholder stays on one line (Card 10351338091 - round 2) ?>',
        '$b = 2; # see Basecamp 9842877199, fixed 2026-05-18',
        "$url = 'http://example.com/card 10379292712'; $c = '#fff';",
        '// phpcs:ignore WordPress.Security.NonceVerification.Recommended',
        '// phpcs:disable WordPress.DB.DirectDatabaseQuery',
        '// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom table',
        '// phpcs:enable WordPress.DB.DirectDatabaseQuery',
    ])
    assert m['long_comment_blocks'] == 1, m
    assert m['ticket_refs'] == 4, m
    assert m['dated_comments'] == 1, m
    assert m['function_exists_guards'] == 1, m
    assert m['wpdb_in_views'] == 2, m
    assert m['phpcs_ignore_without_reason'] == 3, m
    assert m['error_log_calls'] == 1, m
    assert measure_lines('includes/a.php', ['$wpdb->query();'])['wpdb_in_views'] == 0
    print('code-standard-check self-test: ok')


def main(argv):
    if '--self-test' in argv:
        self_test()
        return 0
    plugin = argv[argv.index('--plugin') + 1] if '--plugin' in argv else '.'
    baseline_path = os.path.join(plugin, 'docs', 'standards', 'code-standard-baseline.json')
    now = measure(plugin)

    if '--update' in argv or not os.path.exists(baseline_path):
        with open(baseline_path, 'w') as fh:
            json.dump(now, fh, indent=2, sort_keys=True)
            fh.write('\n')
        print('Baseline written:', json.dumps(now, sort_keys=True))
        return 0

    with open(baseline_path) as fh:
        base = json.load(fh)

    worse = [(k, base.get(k, 0), v) for k, v in sorted(now.items()) if v > base.get(k, 0)]
    better = [(k, base.get(k, 0), v) for k, v in sorted(now.items()) if v < base.get(k, 0)]
    for key, was, is_now in worse:
        print(f'WORSE  {key}: {was} -> {is_now}')
    for key, was, is_now in better:
        print(f'better {key}: {was} -> {is_now} (run with --update to lock it in)')
    if not worse:
        print('Code standard: no metric got worse.', json.dumps(now, sort_keys=True))
    return 1 if worse else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
