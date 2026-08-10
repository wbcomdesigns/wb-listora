# Handoff — Ready-for-Testing sweep + 4 owner-reported bugs (2026-08-10)

State at handoff: **Free pushed and clean on `1.4.2`. Pro unchanged. App repo newly cloned.**

| Repo | Branch | Head | Note |
|---|---|---|---|
| `wb-listora` | `1.4.2` | `b74b239` | 4 new commits this session, all pushed, local-CI green |
| `wb-listora-pro` | `1.4.2` | `5c2f394` | untouched this session |
| `listora-app` | `main` | `0cf713c` | cloned to `Local Sites/directory/listora-app` (outside `app/public`) |

---

## The one thing to carry forward

**A "duplicate", a "zero result", and a "wrong value" each looked like a bug and were not.** Three
times this session the obvious read of a verification result was wrong, and each needed a second,
differently-shaped check to resolve:

| Looked like | Actually was | How it resolved |
|---|---|---|
| `wb_listora_pro_deliver_webhook = 2` — duplicate cron | Two distinct webhook deliveries | The rows carry different `args` (`[7]`, `[8]`) — recurring-hook dedupe does not apply to one-off actions |
| `/search/map-clusters` at zoom 12 → `clusters: 0` | Correct cluster-out / pin-in behaviour | A sibling key `points: 86` held the individual markers |
| Removing the middle hours range kept `01:00, 02:00` | I clicked the wrong button | Remove controls are aria-labelled per range and range 1 has none; DOM index 1 was "time 3" |

If a verification result looks like a defect, find the second signal before filing or bouncing.
Every one of these would have been a false bounce.

---

## What shipped (all Ready for Testing, none self-signed-off)

Four owner-reported issues, all confirmed real, all fixed with browser verification and pushed.

| Commit | Card | Fix |
|---|---|---|
| `4dad883` | 10185645412 | wp-admin save no longer erases a listing's location + geo row |
| `0fec359` | 10185647006 | One-type directory gets a working Add Listing form |
| `c827cb8` | 10185647775 | Admin can change a listing's type from the edit screen |
| `b74b239` | 10185646312 | The **Default** listing type the docs promised now exists |

### The critical one — read this before touching `map_location`

`4dad883` was silent data loss: **every** wp-admin listing save wiped address, coordinates, city,
state and country, and deleted the `wp_listora_geo` row, so the listing dropped off the map and out
of distance search.

The renderer posts `map_location` as ONE nested array (`meta_address[address]`, `[lat]`, `[lng]`,
`[city]`, `[state]`, `[country]`, `[postal_code]`) — which is what `get_rest_schema()` declares. The
admin save handler read seven FLAT keys (`meta_city`, `meta_region`, `meta_postal`, `meta_latitude`…)
that no renderer has ever emitted. Six were always absent; the seventh, `meta_address`, was an array
`(string)`-cast to `"Array"`, which since 1.4.1 `sanitize_json()` correctly refuses — so it landed as
`[]`. The 1.4.1 hardening turned "stores garbage" into "silently erases".

Fixed at the layer that owns the shape: new `Field::sanitize_map_location()` is the field's sanitize
callback, and **the metabox's composite special case is deleted** so it flows the generic path.
Duplicated shape knowledge caused it, so the duplicate is gone rather than corrected.

**Not done:** no backfill for locations erased before this fix. Overwritten meta is unrecoverable,
though `wp_listora_geo` may still hold coordinates for listings not re-saved since. Needs a decision.

### Design decisions worth not re-litigating

- **Default listing type is a single site option** (`default_listing_type`), not per-type meta. "Only
  one can be default" is then true by construction; as term meta the invariant needs re-enforcing on
  every save and can drift to two defaults or none.
- **The default pre-selects but never hides the Type step.** `$listing_type` is what hides it, so
  folding the default into it would lock every submitter on a multi-type site into one type.
- **`is_default` → `is_builtin`, additively.** `is_default()` stays as a deprecated alias and REST
  emits BOTH, so the app keeps working. CLI column renamed `Default` → `Built-in`. Needs a removal
  plan (≥2 minors then a major) and a CHANGELOG entry — **not written**.
- **Type selector saves at priority 20**, after the field save at 15, so on-screen fields persist
  against the type they were rendered for.

---

## RFT sweep — 18 of 32 closed

Moved to **Done** with evidence on each card:

`10155289906` `10155289782` `10155290003` `10155289690` `10156869701` `10156782139` `10162700303`
`10171941201` `10182473304` `10184285025` `10154927648` `10176080621` `10176143671` `10172069880`
`10180685898` `10163072337`

**`10155289690` was hiding a live bug the card missed.** It assumed the dead DELETE permission
callback was harmless because "core's check is itself reasonable". It was not — core's mapped meta
caps refuse a subscriber deleting their OWN listing (401 `rest_cannot_delete`). The card also asked
for a **sweep** for the same shape (any controller calling `parent::register_routes()` then
re-registering a path core owns). **That sweep was never done** and now looks worth doing.

### 14 cards still to verify

Six are app-facing (LST-*) and their content lives **only in comments** — not read yet.

| Card | Title | Note |
|---|---|---|
| 10154242212 | SEO landing pages — no H1 on BuddyX | **Blocked here** — needs BuddyX; site runs Reign 8.0.6 |
| 10154927387 | Ban/suspend gate | pairs with 10184284563 / 10184284933 |
| 10183618407 | Which contact route when Pro lead_form is ON? | **A question, not a bug** — needs a decision, not a verdict |
| 10184284933 | Member blocking — Apple Guideline 1.2 | app UI needs device pass |
| 10184284834 | Unlicensed sites say the app stops working (LST-P-22) | |
| 10184284825 | Payments visible + gateway refunds reconcile (LST-P-12) | |
| 10184284690 | Abbreviated prices wrong — `$1,000K` (LST-F-20) | |
| 10184284563 | Owners can suspend an abusive member (LST-F-10) | |
| 10180373117 | Add Listing unsubmittable when type has no categories | note: `business` now has 10 cats, so the DATA condition is gone — verify the CODE guard |
| 9895778531 | Date picker dark/theme mismatch | |
| 10168060274 | Social Links — no way to remove platforms | wants a filter on `Field::social_link_platforms()` |
| 10167888235 | Footer-rendered blocks lose JS/CSS | needs a builder-rendered footer to reproduce |
| 9871176148 | social_links submission UI | UI **is present** (7 platforms) — verify detail-page render too |
| 10154072308 | Custom badges on cards + sitemap taxonomy leak | two independent halves |
| 10154198434 | DRY/organize standards | review-shaped, not a repro |
| 10154189084 | Buy Credits self-loop + Maps feature≠live | |

---

## Board reference

Plugin QA project **47045113**, table `9827892288`:

| Column | ID |
|---|---|
| Bugs | `9827892296` |
| Ready for Testing | `9827892302` |
| Done | `9827892300` |
| Suggestion | `9827892305` |

**App Basecamp is a separate project: `48338688`** — https://app.basecamp.com/5798509/projects/48338688

---

## App workstream (not started)

Cloned at `Local Sites/directory/listora-app` — deliberately a sibling of `app/`, **outside
`app/public`**, so WordPress never serves or scans a React Native tree.

Expo ~52, React Native 0.76.9, NativeWind. Testing is **simulator-based**, not Playwright — see
`docs/QA-TESTPLAN.md` (boot an iPhone 16 / iOS 18.x by UDID, install build, test accounts in §2).

**The app is release-blocked by its own gate:** `docs/FEATURE-COVERAGE.md` shows **35 ✅ / 2 ❌
Missing**, and the plugin CLAUDE.md requires zero ❌. The two rows have not been identified.

Start with `listora-app/CLAUDE.md`, `docs/RESUME-HERE.md`, then `docs/BASECAMP-RFT-1.5.0.md` — that
last one almost certainly maps onto the six LST-* cards and is the cheapest way to close them.

---

## Open items

1. **Release is gated.** `docs/qa/.last-smoke-pass.json` reads `1.4.1` against a `1.5.0`
   `WB_LISTORA_VERSION`, so `bin/build-release.sh` will refuse to package. `/wp-plugin-smoke combo`
   has not been run for the business-hours wave or for this session's four commits.
2. **Manifest delta still not applied** from the 2026-08-09 wave — `wb_listora_normalize_hours`,
   `wb_listora_max_hours_slots`, `wb_listora_migrated_hours_unreadable`,
   `_listora_migrated_hours_raw` all return 0 hits in `audit/manifest.json`. This session adds more:
   `wb_listora_get_default_listing_type()`, `Field::sanitize_map_location()`,
   `wb_listora_default_listing_type` (filter), `wb_listora_listing_type_changed` (action),
   `is_builtin` (REST field), `default_listing_type` (setting).
3. **`wb_listora_listing_type_changed` has no consumer** — search re-indexing after a type switch
   may need wiring.
4. **Competitor migrator hours mappings** — still open on BC 10184420962. Do not drop
   `_listora_migrated_hours_raw` before that backfill ships.
5. **DELETE-route sweep** from card 10155289690 — never done.
6. **Quick Edit / bulk edit cannot set listing type**, so a bad import is repaired one at a time.

---

## Environment

- Site: `http://directory.local` — Free + Pro both active at **1.5.0**, Reign 8.0.6, **German admin
  locale** (expect "Einträge", "Speichern"; match on IDs not visible text).
- Auto-login: `?autologin=1` via `wp-content/mu-plugins/dev-auto-login.php`.
- WP **block editor** for `listora_listing`, so metaboxes post separately and the Gutenberg welcome
  modal intercepts clicks — remove `.components-modal__screen-overlay` before clicking Save.
- Site left clean: no probe listings, `submission_enabled` restored 10/10, no `default_listing_type`
  set, column prefs reset, pages-review notice dismissed.
