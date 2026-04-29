# WB Listora — Code Flow Maps

**Generated:** 2026-04-29

Each flow shows the complete path: URL/Trigger → Router → Template → PHP → REST/AJAX → JS → CSS → Output. Use this when you need to trace where to make a change without re-grepping the codebase.

---

## Flow 1: Frontend Listing Submission

**Trigger:** User on a page with `listora/listing-submission` block clicks "Submit Listing"
**Roles:** `submit_listora_listing` capability (incl. subscriber); guest registration supported

```
[1] Page renders block listora/listing-submission
    └─→ blocks/listing-submission/render.php
        └─→ Template: templates/blocks/listing-submission/submission.php
            ├─→ Login buttons (do_action wb_listora_submission_login_buttons)
            └─→ Pro plan picker (do_action wb_listora_submission_plan_step) [Pro]

[2] User fills multi-step form (type → fields → location → media → review)
    └─→ Interactivity store: src/interactivity/store.js (namespace listora/directory)
        └─→ JS: dynamic field renderer (conditional fields)

[3] User clicks Submit
    └─→ POST /listora/v1/submit (REST)
        └─→ Submission_Controller::submit_listing
            ├─→ Rate-limit gate (Rate_Limiter::check('submission'))
            │   per-user + per-IP transient counters; ADR-001
            ├─→ Captcha gate (class-captcha.php — reCAPTCHA v3 / Turnstile)
            ├─→ Duplicate check (Submission_Controller::check_duplicate)
            ├─→ apply_filters wb_listora_before_create_listing → can return WP_Error
            ├─→ wp_insert_post + meta + taxonomy
            ├─→ Search_Indexer::index_listing → wp_listora_search_index
            ├─→ Geo upsert → wp_listora_geo
            ├─→ do_action wb_listora_after_create_listing
            ├─→ do_action wb_listora_listing_submitted ($post_id, $author_id, $context)
            │   └─→ Pro: Verification, Lead_Form workflows kick in
            └─→ Email verification (if guest) → Email_Verification::send_token

[4] Response
    └─→ apply_filters wb_listora_rest_prepare_listing → Pro adds badges/credits info
        └─→ Frontend redirects to dashboard or pending screen
```

**Key files:**
| File | Role |
|---|---|
| `blocks/listing-submission/render.php` | Server render |
| `templates/blocks/listing-submission/submission.php` | UI |
| `src/interactivity/store.js` | Multi-step state |
| `includes/rest/class-submission-controller.php` | REST handler |
| `includes/class-captcha.php` | Spam gate |
| `includes/search/class-search-indexer.php` | Index update |
| `includes/workflow/class-email-verification.php` | Verification token |

---

## Flow 2: Faceted Search

**Trigger:** User types in search bar / changes filter on `listora/listing-search` or submits the form.

There are two paths — **live AJAX** (typing for suggestions / facet preview) and **server-rendered SSR** (form submit / shareable URLs / SEO).

```
[A] Live AJAX path
[1] User types query
    └─→ debounced GET /listora/v1/search/suggest → Search_Controller::suggest

[2] Filter change in store.js
    └─→ GET /listora/v1/search?keyword=&type=&category=&location=&features=&lat=&lng=&radius=&sort=
        └─→ Search_Controller::search

[B] SSR path (clicked Search button or shared URL)
[1] searchImmediate() navigates to ?keyword=…&type=…&category=…&location=…&sort=
    └─→ Page reloads — listing-grid render reads $_GET and calls Search_Engine directly,
        so the cards arrive already filtered (no flash of unfiltered content).
    └─→ search/render.php seeds state.searchQuery/selectedType/etc back from $_GET via
        wp_interactivity_state so the inputs reflect what's in the URL after reload.

[Both paths converge on Search_Engine]
    Search_Engine::search
    ├─→ apply_filters wb_listora_search_args
    │   └─→ Pro Advanced_Search injects saved-search params
    ├─→ Search_Engine::build_boolean_keyword
    │   Rewrites raw input to MySQL FULLTEXT BOOLEAN AND mode
    │   ("amalfi coast italian" → +amalfi* +coast* +italian* "amalfi coast italian")
    │   so multi-word queries require all terms instead of OR-ing them.
    ├─→ Phase 1 candidate query on listora_search_index (FULLTEXT MATCH)
    │   meta_text now indexes type + location term names AND the full address
    │   (city/region/country/postal) so 'italian restaurant' / 'manhattan italian' work.
    ├─→ Phase 1.5 Open-now filter / Phase 1.55 date filters
    ├─→ Phase 2 field_index filter (Phase_2_field_filter)
    ├─→ Phase 3 geo distance via Geo_Query (Haversine) — when lat/lng supplied
    ├─→ Phase 4 facet aggregations (Facets class)
    └─→ apply_filters wb_listora_rest_prepare_search_result (per row)

[3] JS receives JSON (AJAX path) or template renders (SSR path)
    └─→ Interactivity API hydrates with wp_interactivity_state
```

**Key files:**
| File | Role |
|---|---|
| `includes/rest/class-search-controller.php` | REST entry |
| `includes/search/class-search-engine.php` | Query builder |
| `includes/search/class-facets.php` | Facets |
| `includes/search/class-geo-query.php` | Distance |
| `assets/js/blocks/directory.js` | Interactivity store |

---

## Flow 3: Review Submission + Helpful Vote

```
[1] User clicks "Write a review" on listora/listing-detail
    └─→ Tab template: templates/blocks/listing-detail/tabs.php
        └─→ Review form rendered (apply_filters wb_listora_review_criteria)
            └─→ Pro Multi_Criteria_Reviews injects per-type criteria fields

[2] Submit review
    └─→ POST /listora/v1/listings/{id}/reviews
        └─→ Reviews_Controller::create_review
            ├─→ apply_filters wb_listora_before_create_review (can abort)
            ├─→ INSERT INTO wp_listora_reviews
            ├─→ Update aggregate avg_rating in wp_listora_search_index
            ├─→ do_action wb_listora_after_create_review
            └─→ do_action wb_listora_review_submitted
                └─→ Pro Photo_Reviews / Multi_Criteria_Reviews persist extras
                └─→ Notifications::send (review_submitted event)

[3] Helpful vote
    └─→ POST /listora/v1/reviews/{id}/helpful
        └─→ Reviews_Controller::vote_helpful
            ├─→ INSERT INTO wp_listora_review_votes (UNIQUE on user_id+review_id)
            ├─→ UPDATE helpful_count on review row
            └─→ do_action wb_listora_review_helpful_milestone (10, 50, 100)
```

---

## Flow 4: Business Claim

```
[1] User clicks "Claim this business" on listing-detail
    └─→ Modal renders claim form (proof text + file upload)

[2] POST /listora/v1/claims (multipart)
    └─→ Claims_Controller::submit_claim
        ├─→ apply_filters wb_listora_before_submit_claim
        ├─→ wp_handle_upload for proof files (uploads gate)
        ├─→ INSERT INTO wp_listora_claims (status=pending)
        ├─→ do_action wb_listora_after_submit_claim
        └─→ Notification to admin (claim_submitted event)

[3] Admin reviews in admin/Claims page (slug=listora-claims)
    └─→ PUT /listora/v1/claims/{id} {status: approved|rejected}
        └─→ Claims_Controller::update_claim
            ├─→ apply_filters wb_listora_before_update_claim
            ├─→ UPDATE listora_claims
            ├─→ If approved: transfer post_author of listora_listing
            ├─→ do_action wb_listora_after_update_claim
            └─→ Notification to claimant (claim_approved | claim_rejected event)
```

---

## Flow 5: Listing Expiration & Renewal

```
[Cron] wb_listora_check_expirations (twicedaily)
    └─→ Expiration_Cron::check_expirations
        ├─→ Find listings expiring in 7 days → do_action wb_listora_listing_expiring (7d email)
        ├─→ Find listings expiring in 1 day → do_action wb_listora_listing_expiring (1d email)
        └─→ Find listings past expiry
            ├─→ Status_Manager::set_status('listora_expired')
            ├─→ Search_Indexer::reindex (status filter)
            └─→ do_action wb_listora_listing_expired

[User flow] Owner sees expired listing in dashboard, clicks Renew
    └─→ GET /listora/v1/listings/{id}/renewal-quote
        └─→ Returns: cost (credits), new expiry date
    └─→ POST /listora/v1/listings/{id}/renew
        └─→ Listings_Controller::renew_listing
            ├─→ Pro Credit_System::deduct (if credits-based)
            ├─→ Update post_meta _listora_expires_at
            ├─→ Status_Manager::set_status('publish')
            └─→ Search_Indexer::reindex
```

---

## Flow 6: Settings Save (Admin)

```
[1] Admin → Listora → Settings (slug=listora-settings)
    └─→ Settings_Page renders tabs (apply_filters wb_listora_settings_tabs)
        └─→ Pro adds: License, Pro Features, White Label, Visibility, SEO

[2] PUT /listora/v1/settings
    └─→ Settings_Controller::update_settings
        ├─→ Cap check: manage_listora_settings (returns WP_Error 403)
        ├─→ Validate per-tab schema
        ├─→ update_option wb_listora_settings
        └─→ Triggers cache invalidation hooks

[3] Test notification button
    └─→ POST /listora/v1/settings/notifications/test
        └─→ Settings_Controller::send_test_notification
            └─→ Notifications::send → wp_mail
                ├─→ apply_filters wb_listora_email_subject_test
                └─→ apply_filters wb_listora_email_content_test
```

---

## Flow 7: Block Per-Instance CSS

Used to prove "no global block class CSS, no theme bleed" rule.

```
[Editor] User adds block listora/listing-grid → sets gap=24, columns=3
    └─→ Saved attributes: { uniqueId: "abc123", gap: 24, columns: 3, ... }

[Render] blocks/listing-grid/render.php
    └─→ WBListora\Block_CSS::render($attributes, $block_name)
        ├─→ Reads 20 standard attrs (responsive padding/margin/border/shadow)
        ├─→ Generates scoped CSS:
        │   .wp-block-listora-listing-grid[data-id="abc123"] { gap: 24px; ... }
        │   @media (max-width: 1024px) { ... } @media (max-width: 767px) { ... }
        └─→ Inline <style> emitted before block markup

[Frontend] Block_CSS deduplicates so each unique attr-hash emits once
```

**Why it matters:** Editing a listing's grid gap doesn't affect any other grid on the page; theme overrides via `{theme}/wb-listora/blocks/listing-grid/...` still work because CSS is scoped to instance.

---

## Flow 8: Background Search Reindex (post-upgrade)

**Trigger:** A version upgrade where the indexer's output schema has changed (new field added to `meta_text`, a new taxonomy indexed, etc.). Running `wp listora reindex` by hand is not 1.0-grade UX, so the migrator schedules a background chain.

```
[1] Plugins loaded → Migrator::maybe_migrate
    └─→ Detects WB_LISTORA_DB_VERSION > stored option
        └─→ migrate_1_2_0 (and any future schema-touching migrations)
            └─→ Search_Indexer::schedule_full_reindex
                ├─→ delete_option wb_listora_reindex_offset
                └─→ wp_schedule_single_event(time()+30, 'wb_listora_search_reindex')

[2] WP-Cron fires the event
    └─→ Search_Indexer::process_scheduled_reindex
        ├─→ offset = get_option wb_listora_reindex_offset (0 on first tick)
        ├─→ Search_Indexer::reindex_chunk(offset, REINDEX_CHUNK_SIZE = 200)
        │   └─→ WP_Query post_type=listora_listing, offset, posts_per_page=200
        │       └─→ index_listing() per row → REPLACE INTO listora_search_index
        ├─→ if processed >= 200 → update offset, schedule next tick (+60s)
        └─→ else → delete option (chain done)
```

**Why it matters:** Users upgrading to a new version with indexer schema changes get accurate search results without intervention. Live writes stay accurate via the existing event-driven hooks (`save_post_listora_listing`, `set_object_terms`, `wb_listora_after_create_listing`, `wb_listora_after_update_listing`) while the background chain catches up the older rows. The option-stored offset means progress survives a tick crash — the next tick resumes from where it failed instead of restarting at zero.
