# Handoff — RFT sweep completed, and one defect class behind three of the bounces (2026-08-11)

State at handoff: **Free pushed and clean on `1.4.2`. Pro unchanged. App unchanged.**

| Repo | Branch | Head | Note |
|---|---|---|---|
| `wb-listora` | `1.4.2` | `54b2506` | 1 new commit this session, pushed, local-CI green |
| `wb-listora-pro` | `1.4.2` | `5c2f394` | untouched |
| `listora-app` | `main` | `0cf713c` | untouched |

Nothing to pull on any of the three — checked at the start of the session, all already current.

---

## The one thing to carry forward

**Rendered is not displayed, and a passing grep is not a passing screen.**

Three cards were bounced this session for the *same* root cause, and all three had been signed
off with the words "browser-verified":

```css
/* Free — assets/css/admin.css */
.wb-listora-admin .notice:not(.listora-notice) { display: none !important; }
```

That rule exists to suppress **third-party** admin notices on Listora screens. Listora's own
notices do not carry `listora-notice`, so it hides them too. On one settings screen, three
owner-facing messages are all present in the DOM at `display: none`.

It is unrecoverable rather than cosmetic, because these notices are deliberately scoped to
Listora screens (`if ( 0 !== strpos( $page, 'listora' ) ) return;`). **The only screens they render
on are the only screens that hide them.** Verified both directions: absent entirely on the WP
Dashboard, present-and-hidden on a Listora page.

Every one of those fixes was verified by reading the markup, where the string genuinely *is*
present. `innerText` on an ancestor returns it too. Only computed style **on the element itself**
reveals it. Any journey asserting these must assert `getComputedStyle(el).display`, not presence.

### The same lesson, eight more times

The 2026-08-10 handoff led with "if a verification result looks like a defect, find the second
signal". It earned its place again — **eight** checks were wrong rather than the code. Every one
would have been a false bounce, and four of them are environment traps specific to this site:

| Looked like | Actually was |
|---|---|
| Prices overstating (`$1,4K` for 1,499) | de_DE comma decimal separator; my parser then read it as a thousands separator |
| `FormData` dropping every social field | I had grabbed the header search form, not `.listora-submission__form` |
| Duplicate `name` attributes clobbering on save | `view.js:261` disables inactive type blocks; `FormData` skips disabled |
| Suspension gate missing on reviews | 404 was my wrong route, then 400 was WP validating params *before* the gate |
| Footer fix working at priority 25, then failing at 15 | `opcache.revalidate_freq=2` — the probe I curled was the *previous* file |
| Default-type guard ignoring `submission_enabled` | `Listing_Type_Registry` is an in-process singleton; `wp_cache_flush()` does not reset it. Correct in a fresh process |
| Type metabox missing its explanatory description | It is there, in English, on a German-locale site |
| One-type submission form rendering nothing at all | `curl` with no session — the page was asking me to log in |

The last one is worth its own warning: **rewriting an mu-plugin and immediately requesting the page
executes the old version.** Any hook-priority test that rewrites a file in a loop needs a ≥4s settle
or the results are noise. The tell was markup rendering while assets were absent.

And one "blocked" that was not: card 10154242212 was carried as *"blocked here — needs BuddyX"*.
**BuddyX and buddyx-pro were already installed, just inactive.** Activating, testing and switching
back took two minutes. The block had been assumed, never checked.

---

## RFT sweep — complete

**Ready for Testing now holds exactly one card**, and it is not a fix awaiting verification —
it is `10183618407`, waiting on an owner decision.

All 16 sweep cards are resolved, **and the four fixes from the 2026-08-10 session were then
verified too**. That session correctly declined to sign off its own work; this one is a different
session, so verifying them was legitimate. All four passed:

| Card | Independently confirmed |
|---|---|
| `10185645412` | **CRITICAL data loss closed.** Real block-editor save of listing 10 — `post_modified` moved, all 7 address keys compared equal to snapshot, `wp_listora_geo` row intact. The six phantom flat keys (`meta_city`, `meta_latitude`…) return **zero** hits; the metabox's duplicate composite case is deleted, not corrected |
| `10185646312` | Default type **pre-selects but never hides the Type step** — `restaurant` pre-checked with all 10 radios still available. Both resolver guards fire; single site option makes "only one default" true by construction |
| `10185647775` | Type selector renders on the edit screen with all 10 types; save at priority 20 after the field save at 15; re-index follows via `set_object_terms` |
| `10185647006` | One-type site: step skipped **with `listing_type=restaurant` applied**, 16 categories, restaurant-only fields. Zero-type: message, no form, owner hint shown to admin and **withheld from a subscriber**. Multi-type regression clean. 390px clean |

### Verified → Done (10)

| Card | What was proven |
|---|---|
| `10184284690` | Prices: 5,350 amounts fuzzed per locale, **0 overstatements**, no `$1,000K` |
| `10180373117` | Recreated the vanished data condition, all 5 steps, restored byte-identical |
| `9871176148` | social_links UI, all 6 steps incl. edit-mode pre-fill |
| `9895778531` | Date picker: full OS × theme truth table, #9919496983 guard intact |
| `10184284563` | Suspension: 403 on every write over a real **Application Password**, browse still 200 |
| `10154927387` | Umbrella; its done-criterion proven. Deny-by-default gate is stronger than the route map asked for |
| `10184284825` | Payments screen, filters, filtered empty state, 390px; `ledger_id` + `idx_ledger` present |
| `10154242212` | BuddyX **0 → 1** H1, Reign **1 → 1**, no duplicate |
| `10167888235` | Footer late-print: bug reproduced with the filter OFF, fixed with it ON, limit at prio 20 confirmed real |
| `10154072308` | Badges on all 3 surfaces; sitemap toggle strips all 5 Listora taxonomies, spares others |

### Bounced → Bugs (5)

| Card | Why |
|---|---|
| `10168060274` | `class-schema-generator.php:198` iterates stored meta, so a removed platform still publishes to JSON-LD `sameAs`. Only reproduces by writing straight to `wp_postmeta` — `update_post_meta` is sanitised, which gives a false pass |
| `10154189084` | Half A passes. Half B's owner message is the hidden-notice defect |
| `10184284834` | Text is correct; never displays. Same cause |
| `10154198434` | Items 1-4 pass. Item D fails — and finding #4 predicted it verbatim: *"not only raw `.notice` if theme/admin CSS hides notices"* |
| `10184284933` | **Blocking does not stop contact on the paid configuration** — see below |

### Awaiting your decision (1)

`10183618407` — left in Ready for Testing with evidence added, no verdict. It needs a decision,
not a pass/fail.

---

## The most serious finding — Apple Guideline 1.2

**A blocked member can still message the person who blocked them, on any Pro site with
`lead_form` enabled** — which is the paid default and what this site runs.

| Route | Result, same blocked pair |
|---|---|
| Free `POST /listings/{id}/contact-form` | **403** `listora_contact_blocked` |
| Pro `POST /listings/{id}/contact` | **200** `{"sent":true}` |

`Member_Blocks::can_contact()` has three call sites, **all in Free**. Zero in Pro's
`class-lead-form.php`. Pro's Needs already does this correctly at
`class-need-response-manager.php:146` via `wb_listora_can_members_contact()` — the lead form simply
never got the same guard, so the fix is one call mirroring that one.

Review hiding works exactly as specified (3 → 2 for the blocker with the total dropping, everyone
else still sees 3). It is only contact that is unenforced.

**This is the cost of `10183618407` staying open.** The two contact routes have now diverged in
*behaviour*, not just in which one renders. Whichever way that decision goes, **both routes need
the guard** — that part is not a judgement call.

---

## Open items

1. **The notice-class sweep.** Add `listora-notice` to Listora's own admin notices — exactly what
   the `:not(.listora-notice)` exclusion exists to permit. **30** `class="notice` sites across
   Free + Pro lack it; the exposed subset is those scoped to or echoed inside Listora pages.
   Unblocks two bounced cards. Pair it with a journey asserting **computed visibility**, since
   presence is what let both cards through.
2. **Pro's lead form needs the block guard** (above). Apple-relevant.
3. **`sameAs` leaks removed social platforms.** One line: key the loop at
   `class-schema-generator.php:200` on `Field::social_link_platforms()`, as the sidebar already does.
4. **`listora_need` leaks into the sitemap** when the sitemap feature is off. `filter_sitemap_taxonomies`
   sweeps the `listora_` prefix; `filter_sitemap_post_types` unsets one hardcoded name. Pro registers
   no sitemap filter. Not filed as a card yet.
5. **Release is still gated.** `docs/qa/.last-smoke-pass.json` reads `1.4.1` against a `1.5.0`
   `WB_LISTORA_VERSION`, so `bin/build-release.sh` refuses to package. `/wp-plugin-smoke combo`
   has not been run. Release is not imminent regardless — 4 fixes still await QA.
6. **`is_default` → `is_builtin` CHANGELOG entry still unwritten**, and the removal plan
   (≥2 minors then a major) is not recorded anywhere but the manifest note.
7. **Competitor migrator hours mappings** — still open on BC 10184420962. Do not drop
   `_listora_migrated_hours_raw` before that backfill ships.
8. **No admin warning when a submission-enabled type has zero categories** (from 10180373117).
   The dead end is fixed; an owner can still create the configuration with no signal.
9. **App-side steps never run.** Cards 10184284933 (steps 7-9), 10184284563 (8-10) and
   10184284834 (3-4) all need a simulator pass. The *transport* is proven for suspension —
   a suspended member is refused over an Application Password — but the app's own presentation is not.
10. **i18n gaps on the payments screen** — "Buyer" column header and the filtered empty state
    render in English on a de_DE site.
11. **Quick Edit / bulk edit still cannot set a listing type**, so a bad import is repaired one
    listing at a time. The per-listing metabox shipped as `c827cb8`; this is the bulk path.
12. **The app is release-blocked by its own gate.** `listora-app/docs/FEATURE-COVERAGE.md` shows
    **35 ✅ / 2 ❌ Missing** and the plugin CLAUDE.md requires zero ❌. The two rows have still not
    been identified. Start at `listora-app/CLAUDE.md`, `docs/RESUME-HERE.md`, then
    `docs/BASECAMP-RFT-1.5.0.md`. The app is cloned at `Local Sites/directory/listora-app`,
    deliberately outside `app/public`. Testing is simulator-based, not Playwright.
13. **No backfill for locations erased before `4dad883`** (the wp-admin `map_location` data loss).
    Overwritten meta is unrecoverable, but `wp_listora_geo` may still hold coordinates for listings
    not re-saved since the fix. **Needs a decision** — carried from the 2026-08-10 handoff, still open.

## Closed this session (were open items 2, 3, 5 on the previous handoff)

- **Manifest delta applied** (`54b2506`) — both waves. Also caught two things the handoff didn't
  list: `GET /search/map-clusters` shipped in 1.5.0 and was **never recorded** (the whole reason
  manifest read 62 endpoints against summary/CLAUDE.md's 63), and `hooks_fired_count` read 270
  against a 304-entry array. Both recomputed from the array rather than by delta arithmetic,
  which is what let them drift.
- **DELETE-route sweep** — 137 `register_rest_route` call sites across Free + Pro, 135 distinct
  routes, **zero same-method collisions**. The two apparent duplicates are method-split
  registrations that both dispatch correctly. Nothing to fix.
- **`wb_listora_listing_type_changed` needs no wiring.** The metabox switches the term with
  `wp_set_object_terms()`, so `Search_Indexer::on_terms_changed()` already re-indexes — verified
  live (`business → restaurant → business`). Nothing else caches per-type data.

---

## Environment

- Site: `http://directory.local` — Free + Pro both active at **1.5.0**, Reign 8.0.6, **German admin
  locale**. Match on IDs and computed values, never on visible English text — it cost three false
  readings this session.
- **BuddyX 5.1.5 and buddyx-pro are installed and inactive.** Theme-specific cards are testable here.
- `opcache.revalidate_freq=2`. Settle ≥4s after writing an mu-plugin before requesting a page.
- Auto-login: `?autologin=1`.
- Test members: `qa_vendor_01` (uid 11), `qa_vendor_02` (uid 12), `qa_vendor_03` (uid 13).
- Reviews carry **orphan author IDs** (201-210) with no matching WP user — blocking a reviewer
  fails with "That member no longer exists" until you pick one who exists. Listing 17 has a real
  reviewer (uid 11). This is also the condition behind Possible-Bugs card 10185681930.

**Site left clean.** Every probe restored and compared against a snapshot: `business` categories
(10), listing 530 `_listora_social_links`, `wb_listora_settings`, `wb_listora_features`,
suspension state, member blocks, active theme. All probe application passwords deleted, probe
listing deleted, probe mu-plugins removed, no probe listings or reviews left behind.

---

## Board reference

Plugin QA project **47045113**, table `9827892288`:

| Column | ID | Count at handoff |
|---|---|---|
| Bugs | `9827892296` | 12 |
| Ready for Testing | `9827892302` | 5 |
| Done | `9827892300` | 324 |
| Possible Bugs | `10155008092` | 6 |
| Suggestion | `9827892305` | 20 |

**App Basecamp is a separate project: `48338688`.**

New in Possible Bugs since the last handoff: `10190192688` (BP activity feed item not created when
a listing is approved under moderation), plus `10185681658` — *"Member blocking has no web UI, only
REST `/me/blocks`"*, which overlaps the blocking work above.
