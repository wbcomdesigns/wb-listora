# Migrator Consolidation — 1.1.0 Plan

**Date:** 2026-05-18
**Target release:** 1.1.0
**Owner:** Varun
**Status:** APPROVED — implementation in progress

---

## Why now

The 2026-05-18 onboard-skill scan (`bin/cleanup-duplicate-detect.php` + `bin/cleanup-boundary-check.sh`) surfaced that Listora is repeating the MediaVerse mistake at the migrator layer: Free and Pro both ship implementations of the same competitor migrators (Directorist, GeoDirectory, WPBDP). Pro also re-fires Free's `wb_listora_listing_submitted` from its bulk-importer, which trips Free's notification listeners for every migrated listing.

Engineering cost of leaving this alone: every customer-reported migrator bug becomes 2 PRs (one per repo), bug-for-bug parity drifts within a quarter, the third-party migrator (HivePress, Pro-only) becomes a precedent for divergence. Quality bar is 100K-site readiness — duplicate codepaths are how 100K-site readiness fails.

## North star (long-term posture)

| Concern | Owner | Why |
|---|---|---|
| Universal file formats (CSV / JSON / GeoJSON) | **Free** | The on-ramp. Wins the evaluation. Any prospect with a spreadsheet can import without paying. |
| Competitor-specific migrators (Directorist / GeoDirectory / WPBDP / HivePress / ListingPro / BDP) | **Pro** | The premium escalator. High-effort, low-frequency, one-time. Customers pay $$ to avoid two weeks of manual data entry. |
| Auto-detect / visual mapper / preview / templates | **Pro** | Premium UX layer on top of the data pipeline. |
| Cross-cutting helpers (term setting, html-to-text, etc.) | **Free** | Single source of truth. Both Free's universal importers AND Pro's competitor migrators consume. |

**Rule:** every new competitor migrator → one PR to Pro, Free untouched. Every new universal file format → Free. No duplicate codepaths between repos.

## Phases (in dependency order)

Each phase is a separate commit on `main`. Phases 1–4 are additive (production-safe). Phase 5 is the only deletion phase and ships with `@deprecated` shims that survive until 1.2.0.

### Phase 1 — Audit duplicate pairs (READ-ONLY, no code change)

For each of the 3 duplicate pairs (Directorist, GeoDirectory, WPBDP):

1. Diff Free's version against Pro's version.
2. Score: schema coverage, code quality, UI integration, test coverage, error handling.
3. Pick the winner — Pro's is more recent (post-INV-3 cleanup, post-architecture-contract). Pro is also the only side with admin UI wiring (`Competitor_Migration::register_routes()`).
4. Document the diff summary in this plan's "Audit findings" section below.

**Output:** "Audit findings" section in this doc, no code changes. Commit message: `docs(plan): audit migrator-pair diffs for 1.1.0 consolidation`.

### Phase 2 — Extract shared helpers into Free (additive)

Build the two cross-cutting helpers BEFORE moving any migrator code, so Phase 4 has somewhere to land:

#### 2a. `\WBListora\Import\Term_Helper` (new file `includes/import-export/class-term-helper.php`)

```php
<?php
namespace WBListora\Import;

defined( 'ABSPATH' ) || exit;

final class Term_Helper {
    /**
     * Set taxonomy terms on a listing, creating terms if they don't exist.
     *
     * Used by Free's CSV/JSON/GeoJSON importers AND by Pro's competitor
     * migrators (via the documented surface — Pro requires Free, so
     * \WBListora\Import\Term_Helper is callable from Pro at runtime).
     *
     * @param int      $post_id  Listing post ID.
     * @param string[] $terms    Array of term names (will be sanitised).
     * @param string   $taxonomy Taxonomy slug.
     * @return int[] Resolved term IDs (post-creation).
     */
    public static function set_terms( int $post_id, array $terms, string $taxonomy ): array {
        // [body extracted from class-geojson-importer.php:478 — the
        // canonical version, identical to class-json-importer.php:373
        // and Pro's class-visual-importer.php:546]
        $term_ids = array();
        foreach ( $terms as $term_name ) {
            $term_name = sanitize_text_field( $term_name );
            if ( empty( $term_name ) ) {
                continue;
            }
            $existing = term_exists( $term_name, $taxonomy );
            if ( ! $existing ) {
                $existing = wp_insert_term( $term_name, $taxonomy );
            }
            if ( ! is_wp_error( $existing ) ) {
                $term_ids[] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
            }
        }
        if ( ! empty( $term_ids ) ) {
            wp_set_object_terms( $post_id, $term_ids, $taxonomy );
        }
        return $term_ids;
    }
}
```

Updated 3 Free call-sites to use `Term_Helper::set_terms()`:
- `includes/import-export/class-geojson-importer.php:478` — body becomes `return Term_Helper::set_terms(...)` (private method retained as `@deprecated` shim for 1.1.0)
- `includes/import-export/class-json-importer.php:373` — same shim
- `includes/import-export/class-csv-importer.php` — if it has the same pattern, also routes through Term_Helper

Updated 1 Pro call-site:
- `wb-listora-pro/includes/importexport/class-visual-importer.php:546` — body becomes `return \WBListora\Import\Term_Helper::set_terms(...)`. Pro's private method retained as `@deprecated` shim.

#### 2b. `\WBListora\Workflow\Email_Body_Formatter` (new file `includes/workflow/class-email-body-formatter.php`)

```php
<?php
namespace WBListora\Workflow;

defined( 'ABSPATH' ) || exit;

final class Email_Body_Formatter {
    /**
     * Strip HTML to plain text for the text/plain mail alternative.
     * Keeps link URLs visible so screen-reader / Gmail text-only
     * clients still get the CTA.
     *
     * @param string $html Rendered HTML body.
     * @return string Plain-text body.
     */
    public static function html_to_text( string $html ): string {
        // [body extracted from class-notifications.php:1359 — byte-identical
        // to Pro's class-email-helpers.php:110]
        $with_links = preg_replace( '#<a[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>(.*?)</a>#is', '$2 ($1)', $html );
        $text       = wp_strip_all_tags( (string) $with_links );
        $text       = preg_replace( "/[ \t]+/", ' ', $text );
        $text       = preg_replace( "/\n{3,}/", "\n\n", (string) $text );
        return trim( (string) $text );
    }
}
```

Updated 1 Free call-site:
- `includes/workflow/class-notifications.php:1116` — `$this->html_to_text( $body )` becomes `Email_Body_Formatter::html_to_text( $body )`. Free's private method retained as `@deprecated` shim.

Updated 1 Pro call-site:
- `wb-listora-pro/includes/class-email-helpers.php:31` — `self::html_to_text( $html )` becomes `\WBListora\Workflow\Email_Body_Formatter::html_to_text( $html )`. Pro's static retained as `@deprecated` shim.

**Output:** 2 new helper classes in Free; 5 call-sites routed through them; 4 deprecated shims left in place for 1.1.0. Commit: `feat(import): extract Term_Helper + Email_Body_Formatter — shared by Free + Pro`.

### Phase 3 — Add migration-context arg to `wb_listora_listing_submitted` (additive)

This phase prevents Pro's bulk-importer from triggering Free's listener side-effects (emails, status transitions, search-reindex) for every migrated listing.

#### 3a. Free fire-sites — add optional 4th arg

4 fire-sites in Free, all using 3 args `(int $post_id, string $new_status, WP_REST_Request|null $request)`:

- `includes/admin/class-listing-columns.php:492`
- `includes/workflow/class-email-verification.php:369`
- `includes/rest/class-submission-controller.php:521`
- `includes/rest/class-submission-controller.php:1057`

Update all 4 to pass an empty `array $context = array()` as the 4th arg. Default context means "submission from a normal user flow" (current behaviour, unchanged).

#### 3b. Pro fire-site — pass migration context

- `wb-listora-pro/includes/migration/class-base-migrator.php:290` — change:
  ```php
  do_action( 'wb_listora_listing_submitted', $new_id, 'publish', null );
  // becomes:
  do_action( 'wb_listora_listing_submitted', $new_id, 'publish', null, array( 'source' => 'migration', 'migrator' => static::class ) );
  ```

#### 3c. Free listeners — skip when context is migration

3 Free listener call-sites:
- `includes/workflow/class-status-manager.php:50` — `on_after_submission()` — keep running (status transition still needed for migrated listings; they're already 'publish' so it's a no-op anyway, but harmless)
- `includes/workflow/class-notifications.php:118` — `listing_submitted()` — **skip when migration context** (don't email vendors when bulk-importing legacy data)

Listener signature changes from 3 args to 4 args (`int $post_id, string $new_status, WP_REST_Request|null $request, array $context = array()`). Default arg keeps back-compat with any 3rd-party listener (Pro's own listeners also need updating).

#### 3d. Pro listeners — review each

4 Pro listener call-sites:
- `wb-listora-pro/includes/features/class-pricing-plans.php:35` — `handle_plan_on_submit` — keep running (migrated listings still need plan logic? Decide per audit — likely SKIP since migrated listings shouldn't trigger payment flows)
- `wb-listora-pro/includes/features/class-buddy-press-integration.php:68` — `activity_listing_published` — **skip when migration** (don't post 1000 activity items to BuddyPress feeds for bulk-migration)
- `wb-listora-pro/includes/features/class-audit-log.php:214` — `on_listing_submitted` — **keep running** (audit log of migration is valuable)
- `wb-listora-pro/includes/features/class-moderator.php:87` — `assign_listing` — keep running (migrated listings still need moderator assignment)

**Output:** Fire-site signature updated to 4 args (back-compat via default empty array); 2 listeners (Free Notifications + Pro BuddyPress) gated on context. Commit: `feat(migration): context-aware wb_listora_listing_submitted fires`.

### Phase 4 — Move competitor migrators: canonical Pro version, delete Free copies

For each of the 3 duplicate pairs:

1. Take Pro's version as canonical (Pro has the UI, the REST routes, the auto-detect / preview / template manager all wired around them).
2. Cross-check Free's version for any schema-handling code missing in Pro. Port that knowledge into Pro's version.
3. Delete Free's competitor migrator file.
4. Update Free's `Migration_Base::get_migrators()` at `class-migration-base.php:495` (the registration list) to remove the deleted classes.

**Move ListingPro from Free → Pro:**
- Read `includes/import-export/class-listingpro-migrator.php` (410 LOC)
- Adapt to Pro's `\WBListoraPro\Migration\Base_Migrator` interface
- Create `wb-listora-pro/includes/migration/class-listing-pro-migrator.php`
- Register in `wb-listora-pro/includes/features/class-competitor-migration.php` `$migrators` map
- Delete Free's copy

**Delete Free's `Migration_Base`:**
- After all 4 competitor migrators are removed, Free's `class-migration-base.php` (501 LOC) has zero consumers. Delete.

**Files deleted from Free in this phase (~1,800 LOC total):**
- `includes/import-export/class-directorist-migrator.php` (321 LOC)
- `includes/import-export/class-geodirectory-migrator.php` (410 LOC)
- `includes/import-export/class-bdp-migrator.php` (346 LOC)
- `includes/import-export/class-listingpro-migrator.php` (410 LOC)
- `includes/import-export/class-migration-base.php` (501 LOC)

**Net result:** Pro has 5 competitor migrators (Directorist, GeoDirectory, WPBDP, HivePress, ListingPro). Free retains only universal file importers (CSV, JSON, GeoJSON) + CSV exporter. BDP/Business Directory: re-check; if Pro's WPBDP version handles both BDP variants, BDP migrator collapses into WPBDP. Otherwise add BDP as 6th Pro migrator.

**Output:** ~1,800 LOC removed from Free. Pro owns all competitor migrators with one canonical implementation each. Commit: `refactor(migrator): consolidate competitor migrators into Pro (Free retains universal formats)`.

### Phase 5 — Drop the deprecated shims (deferred to 1.2.0)

Per the production rules (`CLAUDE.md` section "Production rules"), public symbols deprecated in X.Y.Z are removed in X.(Y+2).0 minimum. The shims from Phase 2 ship in 1.1.0; deletion happens in 1.2.0 or later, gated by:

- Zero customer reports of integration breakage
- All known consumers (Pro's other features, mu-plugins) audited and migrated

This phase is OUT OF SCOPE for 1.1.0 but documented here so it isn't forgotten.

### Phase 6 — Regression sentinels (mandatory per QA self-growth contract)

Two new journeys under `tests/qa/journeys/regression/`:

#### `migrator-context-arg.md`
- **Priority:** high
- **Covers:** Phase 3 — `wb_listora_listing_submitted` context arg + Notifications listener skip
- **Test:** seed a Directorist fixture (5 listings) → run Pro's Directorist migrator from admin UI → assert: (a) all 5 listings created with `post_status='publish'`, (b) zero rows in `wp_options` with key matching `wb_listora_email_log` having `event='listing_submitted'` for the 5 listings, (c) `audit_log` table HAS 5 'listing_submitted' rows (Pro's Audit_Log listener still runs — that's correct)

#### `term-helper-consolidation.md`
- **Priority:** high
- **Covers:** Phase 2 — shared `Term_Helper`
- **Test:** import 10 listings via Free's CSV importer with category terms → import same 10 listings via Pro's Visual_Importer → assert: both produce identical `wp_term_taxonomy` + `wp_term_relationships` rows; no duplicate terms created; both call sites end up at `\WBListora\Import\Term_Helper::set_terms()` (verified by adding a debug listener and asserting call count)

### Phase 7 — Document the split in CLAUDE.md (post-merge polish)

Add a "Migrator ownership" section to Free's CLAUDE.md (after the "Free → Pro upscale-journey contract" section) that codifies:

- Universal file formats live in Free's `includes/import-export/`
- Competitor-specific migrators live in Pro's `includes/migration/`
- Cross-cutting helpers (Term_Helper, Email_Body_Formatter) live in Free
- Adding a new competitor → PR to Pro only
- Adding a new universal format → PR to Free only

This prevents future Claude sessions (or any contributor) from accidentally re-introducing the duplication.

## Audit findings (Phase 1 results)

*To be filled in during Phase 1 execution.*

### Directorist migrator pair

- **Free version** (`class-directorist-migrator.php`, 321 LOC): *score TBD*
- **Pro version** (`class-directorist-migrator.php`, 179 LOC): *score TBD*
- **Decision:** *TBD*

### GeoDirectory migrator pair

- **Free version** (`class-geodirectory-migrator.php`, 410 LOC): *score TBD*
- **Pro version** (`class-geo-directory-migrator.php`, 202 LOC): *score TBD*
- **Decision:** *TBD*

### WPBDP migrator pair

- **Free version** (`class-bdp-migrator.php`, 346 LOC): *score TBD*
- **Pro version** (`class-wpbdp-migrator.php`, 195 LOC): *score TBD*
- **Decision:** *TBD*

## Risk register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Phase 4 deletes a class some customer mu-plugin extends | Low | High (fatal on plugin load) | (a) `@deprecated` shim NOT deleted until 1.2.0; (b) class delete is preceded by 1 release cycle with shim emitting `_doing_it_wrong()`; (c) release-notes call out the rename + migration path |
| Phase 3 listener signature change breaks 3rd-party listeners | Low | Medium (PHP warning + skipped behaviour) | Default empty array on the 4th arg — old 3-arg signatures still work |
| Phase 2 helpers depend on Pro being able to `use` Free's namespace | None | None | Pro already requires Free at runtime (`Requires Plugins: wb-listora` + activation guard); Pro can safely call `\WBListora\Import\Term_Helper` |
| Pro's competitor-migration test fixtures (if any) reference Free's deleted classes | Low | Medium (test failures) | Phase 4 audit includes test-fixture grep; any test calling Free's deleted class gets ported to Pro's version |
| `wb_listora_listing_submitted` context arg accidentally introduces an infinite loop (listener fires the hook) | Low | High (server-side fatal) | None of the 6 listeners (2 Free + 4 Pro) fire the action they're listening to. Sanity-check during Phase 3 implementation. |

## Commit sequence

1. **`docs(plan): write migrator-consolidation-1.1.0 plan`** — this file (no code).
2. **`docs(plan): audit migrator-pair diffs for 1.1.0 consolidation`** — Phase 1 audit findings filled in.
3. **`feat(import): extract Term_Helper + Email_Body_Formatter — shared by Free + Pro`** — Phase 2.
4. **`feat(migration): context-aware wb_listora_listing_submitted fires`** — Phase 3 (Free + Pro).
5. **`refactor(migrator): consolidate competitor migrators into Pro (Free retains universal formats)`** — Phase 4 (Free + Pro).
6. **`test(qa): regression sentinels for migrator context + term-helper consolidation`** — Phase 6.
7. **`docs(claude): document Free vs Pro migrator ownership`** — Phase 7.

Each commit gates on: PHPStan L7 = 0 errors, WPCS = 0 errors, `bin/architecture-checks.sh` passes 14/14, JS builds cleanly.

After all 7 commits land, run `/wp-plugin-smoke combo` to lock 1.1.0. The two new regression journeys (Phase 6) are part of the smoke walk.

## Definition of done

- [ ] Free root: zero competitor-migrator files (only universal format importers)
- [ ] Pro: 5+ competitor migrators (Directorist, GeoDirectory, WPBDP, HivePress, ListingPro, optional BDP)
- [ ] Zero cross-plugin duplicate methods per `php bin/cleanup-duplicate-detect.php` rerun (was 5 real + false-positives → target 0 real)
- [ ] Zero boundary violations per `bash bin/cleanup-boundary-check.sh` (already at 0; must stay there)
- [ ] `audit/derived/cross-plugin-coupling.json` refreshed; Free→Pro pair count includes new `Term_Helper` + `Email_Body_Formatter` consumption
- [ ] Two new regression journeys pass `composer journeys`
- [ ] CLAUDE.md documents the ownership split
- [ ] `/wp-plugin-smoke combo` returns green with `release_version: "1.1.0"`
