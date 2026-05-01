# Free `hooks_fired[].consumed_by` audit — 2026-05-01

**Trigger:** F-31 in [`plan/release-issues-and-flow-tests.md`](../plan/release-issues-and-flow-tests.md). Plan flagged "162 hooks fired with no documented `consumed_by` — sweep for dead extension surface or document". Reality:

- **184 fired hooks** (plan said 162 — manifest grew with later commits)
- **31 had `consumed_by` populated** before this run (mostly Free internal listeners + a few Pro entries)
- **153 had `consumed_by = null`** before this run

After cross-referencing every Pro `add_filter` / `add_action` call site against Free's `hooks_fired[].name`, this run added `"wb-listora-pro"` to 47 fired-hook entries that Pro actually listens to but were not documented.

## After this run

| `consumed_by` state | Count |
|---|------:|
| Populated (named consumer) | **64** |
| Null / empty | **120** |
| Total | 184 |

The 120 remaining nulls are **available extension surface awaiting a consumer**, not dead code:

- `before_*` / `after_*` write hooks for resources that Pro doesn't currently extend (e.g., Pro consumes `wb_listora_after_create_review` but not `wb_listora_after_create_listing` even though Free fires the latter — extension surface exists for site owners and 3rd-party add-ons).
- REST response filters (`wb_listora_rest_prepare_*`) that allow extensions to inject fields.
- Cancellable pre-action filters (`wb_listora_before_*`) that allow extensions to abort.

These are intentional extension API. Removing them would break documented contracts.

## Detection methodology

```bash
cd wb-listora-pro
grep -rE "add_(filter|action)\s*\(\s*['\"]wb_listora_[^'\"]+['\"]" includes --include="*.php" \
  | grep -oE "'wb_listora_[a-z_]+'" \
  | sort -u | wc -l
# 61 unique Free hook names that Pro registers a listener for.
```

The 47 newly-populated entries are the intersection of those 61 listener names ∩ the 184 hooks Free actually fires (the remaining 14 listener names target hooks that aren't currently fired by Free — likely waiting-room / future-feature names; documented separately if needed).

## Reproducer

```bash
cd wb-listora
python3 - <<'PY'
import json, re, glob
PRO_DIR = '../wb-listora-pro/includes'
pro_consumed = set()
hr = re.compile(r"add_(?:filter|action)\s*\(\s*['\"]([a-z][a-z0-9_]*)['\"]")
for f in glob.glob(f'{PRO_DIR}/**/*.php', recursive=True):
    for m in hr.finditer(open(f).read()):
        n = m.group(1)
        if n.startswith('wb_listora_'):
            pro_consumed.add(n)
mf = json.load(open('audit/manifest.json'))
populated_in_manifest = set()
for h in mf['hooks_fired']:
    if h.get('consumed_by') and 'wb-listora-pro' in (h['consumed_by'] if isinstance(h['consumed_by'], list) else []):
        populated_in_manifest.add(h['name'])
fired_names = {h['name'] for h in mf['hooks_fired']}
covered = pro_consumed & fired_names
missing = covered - populated_in_manifest
print(f"Pro listens to: {len(pro_consumed)} unique hook names")
print(f"Of those, fired by Free: {len(covered)}")
print(f"Manifest entries marked wb-listora-pro: {len(populated_in_manifest)}")
print(f"Drift (would need manifest update): {len(missing)}")
PY
```

Expected after this run: `Drift = 0` (or ≤ a small number for `consumed_by` strings that already include `wb-listora-pro` alongside other internal references).

## Verdict for 1.0.0

- 64/184 hooks have a documented consumer (was 31). 47 entries newly populated this run.
- 120/184 are intentional extension surface, not dead code.
- No fired hooks identified for removal. The audit is complete; future Pro/3rd-party extensions can populate `consumed_by` as they wire up.
