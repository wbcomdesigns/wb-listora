---
journey: search-hours-normalised-additive
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [business_hours-shape-parity, search-hours-additive, Business_Hours, open-now-computability, meta.business_hours-back-compat, overnight-span]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one published listing with rows in {prefix}listora_hours (verified: 774 and 17, 06:00 -> 01:00 overnight)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# `/search` carries hours the app can compute "Open now" from, without breaking the old key

Read-only REST journey (no UI; no theme list). Public data, identical for every role: the ladder below only confirms that. Verified via: curl of `/search` and `/detail` and wp eval (all steps observed).

`/search` items carry `meta.business_hours` (legacy shape `[{day, open, close}]`, `HH:MM`) AND additive `hours` + `timezone` produced by `\WBListora\Core\Business_Hours`, the same normaliser `/listings/{id}/detail` uses (`business_hours`). `timezone` is the EFFECTIVE zone (`get_effective_timezone()`; the `UTC`/unset sentinel resolves to the site zone, `includes/rest/class-search-controller.php:719`).

## Setup
```bash
SITE=http://wb-listora.local
curl -s "$SITE/wp-json/listora/v1/search?per_page=20" | python3 -c "import sys,json;d=json.load(sys.stdin);print(list(d))"   # ['listings','total','pages','has_more']
```

## Steps

### 1. Legacy `meta.business_hours` is UNCHANGED
- **Action**: read `listings[i].meta.business_hours[0]` for an item that has hours.
- **Expect**: exactly `{"day":1,"open":"06:00","close":"01:00"}` shape: keys `day`/`open`/`close`, `HH:MM`, nothing added or removed. **On fail**: release blocker, `class-search-controller.php:696` (`'meta' => $all_meta`).

### 2. `hours` and `timezone` are present (additive)
- **Expect**: `timezone` is a non-empty IANA id (`America/New_York`) and `hours[0]` has ALL of `day, day_name, open_time, close_time, is_closed, is_24h, timezone` (observed `{"day":0,"day_name":"Sunday","open_time":"06:00:00","close_time":"01:00:00","is_closed":false,"is_24h":false,"timezone":"America/New_York"}`).

### 3. `/search` `hours` equals `/detail` `business_hours`
- **Action**: take the first listing with `hours`, fetch `/listings/{id}/detail`.
- **Expect**: `detail.business_hours == hours` exactly (one parser).

### 4. "Open now" is computable from `/search` alone
- **Expect**: `timezone`, `is_closed`, `is_24h` present; an overnight span exists (`close_time < open_time`, 06:00:00 -> 01:00:00) so a client can apply midnight-wrap in the listing's zone.

### 5. Hours are batched: one query per page
- **Action**:
  ```bash
  wp eval '$GLOBALS["hq"]=[]; add_filter("query",function($q){ if(false!==stripos($q,"listora_hours")){$GLOBALS["hq"][]=$q;} return $q; });
  $r=new WP_REST_Request("GET","/listora/v1/search"); $r->set_param("per_page",20); $d=rest_do_request($r)->get_data();
  echo "items=".count($d["listings"])." hours_queries=".count($GLOBALS["hq"]);'
  ```
- **Expect**: `items=20 hours_queries=1` (observed).

### 6. No hours degrades honestly
- **Expect**: a listing with no `listora_hours` rows returns `hours: []` and the SITE zone from `get_effective_timezone()`, never a device zone (observed listing 367: `hours: []`, `timezone: "+00:00"` because this site's timezone is a UTC offset). The old `timezone: ""` contract no longer holds.

### 7. Large set
- `per_page=20` over `pages` (5 here): page 5 has a short last page, `has_more` false; `hours_queries` stays 1 per page.

## Fail diagnostics
| Symptom | File |
|---|---|
| `meta.business_hours` changed | `includes/rest/class-search-controller.php:696` |
| `hours`/`timezone` absent | `class-search-controller.php:714-719` |
| Not identical to `/detail` | `Business_Hours::get()` in `includes/core/` (one producer) |
| `hours_queries=20` | `Business_Hours::prime()` before the loop |
