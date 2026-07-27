---
journey: search-hours-normalised-additive
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [business_hours-shape-parity, search-hours-additive, Business_Hours, open-now-computability, meta.business_hours-back-compat, overnight-span]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active"
  - "At least one published listing with rows in {prefix}listora_hours (seeded: 43 listings, 301 rows)"
  - "At least one listing with an overnight span (open 06:00 → close 01:00) — present in seeded data"
estimated_runtime_minutes: 4
---

# `/search` carries hours the app can actually compute "Open now" from — without breaking the old key

`business_hours` was emitted in two incompatible shapes by two producers:

| Endpoint | Source | Shape |
|---|---|---|
| `/search` → `meta.business_hours` | post meta | `[{day:1, open:"06:00", close:"01:00"}]` |
| `/listings/{id}/detail` → `business_hours` | the `hours` table | `[{day:0, day_name, open_time:"06:00:00", close_time:"01:00:00", is_closed, is_24h, timezone}]` |

Different keys, precision and day-base — and only `/detail` carried `timezone` / `is_closed` /
`is_24h`. **"Open now" was therefore not honestly computable from a search card**: seeded data has
overnight spans (06:00→01:00) needing midnight-wrap logic evaluated in the *listing's* timezone,
not the device's.

1.2.3 **adds** `hours` + `timezone` to `/search` alongside the untouched `meta.business_hours`,
both produced by `\WBListora\Core\Business_Hours` — the single normaliser `/detail` now also uses,
so the two endpoints cannot drift apart again.

**This is additive only.** `meta.business_hours` is a public, shipped key: it must keep working
byte-identically (production rules 1–2). This journey fails if it changes **or** if the new block
diverges from `/detail`.

## Setup

```bash
SITE=http://listora.local
# A listing that actually has hours rows:
LISTING=$(wp eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT h.listing_id FROM {$wpdb->prefix}listora_hours h JOIN {$wpdb->posts} p ON p.ID=h.listing_id WHERE p.post_status=\"publish\" LIMIT 1");')
echo "listing with hours: $LISTING"
```

## Steps

### 1. Legacy `meta.business_hours` is UNCHANGED (back-compat)
- **Action**:
  ```bash
  curl -s "$SITE/wp-json/listora/v1/search?per_page=20" | python3 -c "
  import sys,json
  for it in json.load(sys.stdin)['listings']:
      bh = it.get('meta',{}).get('business_hours')
      if bh:
          print(json.dumps(bh[0])); break"
  ```
- **Expect**: the original meta shape, e.g. `{"day": 1, "open": "06:00", "close": "01:00"}` —
  keys `day` / `open` / `close`, `HH:MM` precision. **No new keys, none removed, no re-typing.**
- **On fail**: **STOP — release blocker.** A public key was restructured in a patch release.
  Suspect: anything writing to `$listing['meta']` in `class-search-controller.php`.

### 2. `/search` gained `hours` and `timezone` (additive)
- **Action**:
  ```bash
  curl -s "$SITE/wp-json/listora/v1/search?per_page=20" | python3 -c "
  import sys,json
  for it in json.load(sys.stdin)['listings']:
      if it.get('hours'):
          print('timezone:', json.dumps(it['timezone']))
          print('hours[0]:', json.dumps(it['hours'][0])); break"
  ```
- **Expect**: `timezone` is a non-empty IANA id (e.g. `"America/New_York"`), and `hours[0]` has
  ALL of `day`, `day_name`, `open_time`, `close_time`, `is_closed`, `is_24h`, `timezone`.
- **On fail**: suspect `Business_Hours::get()` / `get_timezone()` and the `prime()` call in
  `hydrate_listings()`.

### 3. `/search` `hours` is byte-identical to `/detail` `business_hours` (one parser)
- **Action**:
  ```bash
  python3 - <<PY
  import json, urllib.request
  g=lambda u: json.load(urllib.request.urlopen(u))
  s=g("$SITE/wp-json/listora/v1/search?per_page=20")
  t=next(i for i in s['listings'] if i.get('hours'))
  d=g(f"$SITE/wp-json/listora/v1/listings/{t['id']}/detail")
  print("identical:", d['business_hours'] == t['hours'])
  PY
  ```
- **Expect**: `identical: True`.
- **Why**: this is the whole point — one client parser for both endpoints. Both must come from
  `\WBListora\Core\Business_Hours::get()`.
- **On fail**: the two producers drifted; `/detail` must not hand-roll its own normalisation.

### 4. "Open now" is computable from `/search` alone
- **Action**: from step 2's item, assert `timezone` is non-empty and each `hours` row exposes
  `is_closed` and `is_24h`.
- **Expect**: all present. An overnight span exists (`close_time < open_time`, e.g.
  `06:00:00` → `01:00:00`), so a client can apply midnight-wrap logic **in the listing's timezone**.
- **On fail**: the app is forced back to `/detail` per card — the P5 gap has regressed.

### 5. Hours are batched — ONE query per page, not one per card
- **Action**:
  ```bash
  wp eval '$GLOBALS["hq"]=array(); add_filter("query", function($q){ if(false!==stripos($q,"listora_hours")){$GLOBALS["hq"][]=$q;} return $q; });
  $r = new WP_REST_Request("GET","/listora/v1/search"); $r->set_param("per_page",20);
  $d = rest_do_request($r)->get_data();
  echo "items=".count($d["listings"])." hours_queries=".count($GLOBALS["hq"])."\n";'
  ```
- **Expect**: `items=20 hours_queries=1`.
- **On fail (`hours_queries=20`)**: `Business_Hours::prime()` is not called before the hydrate
  loop, so each `get()` falls back to its single-ID lookup — a new N+1 introduced by this feature.

### 6. A listing with NO hours degrades honestly
- **Action**: request a listing with zero `listora_hours` rows via `/search` (or
  `Business_Hours::get()` directly).
- **Expect**: `hours` is `[]` and `timezone` is `""` — we do **not** substitute the site or device
  timezone, which would silently produce wrong "Open now" answers for out-of-region listings.
- **On fail**: an invented timezone is worse than an absent one.

## Pass criteria

ALL must hold:
- `meta.business_hours` byte-identical to pre-1.2.3 (`day` / `open` / `close`, `HH:MM`).
- `/search` items expose `hours` + `timezone`.
- `/search` `hours` == `/detail` `business_hours` exactly.
- `timezone`, `is_closed`, `is_24h` all present; overnight spans representable.
- `per_page=20` → exactly **1** hours query.
- No-hours listing → `hours: []`, `timezone: ""`.
- No new notices in `wp-content/debug.log`.

## Fail diagnostics

| Symptom | Suspect |
|---|---|
| `meta.business_hours` changed | **Release blocker** — public key restructured; `class-search-controller.php` |
| `hours` / `timezone` absent | `hydrate_listings()` not adding the additive block |
| step 3 not identical | `/detail` bypassing `Business_Hours::get()` — two producers again |
| `hours_queries=20` | `Business_Hours::prime()` missing before the loop |
| timezone invented on empty hours | `Business_Hours::get_timezone()` fallback |

## State restored

Read-only journey — no writes, nothing to restore.
