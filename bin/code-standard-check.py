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

SKIP_DIRS = {'vendor', 'libs', 'node_modules', 'tests', 'build', 'languages', 'bin', 'demo'}
TICKET = re.compile(r'(card|Card|BC|Basecamp|Zoho|QA)[ #]*\d{8,}')
COMMENT = re.compile(r'\s*(//|\*|/\*)')


def measure_lines(path, lines):
    """Metric counts for one PHP file's lines."""
    m = dict.fromkeys(
        ['ticket_refs', 'long_comment_blocks', 'files_over_1500', 'wpdb_in_views',
         'function_exists_guards', 'phpcs_ignore_without_reason', 'error_log_calls'], 0)
    run = 0
    for line in lines + ['']:
        if COMMENT.match(line):
            run += 1
            if TICKET.search(line):
                m['ticket_refs'] += 1
            continue
        if run > 15:
            m['long_comment_blocks'] += 1
        run = 0
        if "function_exists( 'wb_listora" in line:
            m['function_exists_guards'] += 1
        if 'phpcs:ignore' in line and '--' not in line.split('phpcs:ignore', 1)[1]:
            m['phpcs_ignore_without_reason'] += 1
        if 'error_log(' in line:
            m['error_log_calls'] += 1
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
    ])
    assert m['long_comment_blocks'] == 1, m
    assert m['ticket_refs'] == 1, m
    assert m['function_exists_guards'] == 1, m
    assert m['wpdb_in_views'] == 2, m
    assert m['phpcs_ignore_without_reason'] == 1, m
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
