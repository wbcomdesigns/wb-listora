---
journey: admin-setup-wizard-first-run
plugin: wb-listora
priority: critical
roles: [administrator]
covers: [setup-wizard, headers-already-sent, demo-seed, essential-pages-idempotent]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1)"
  - "Plugin freshly activated (or wb_listora_setup_complete option deleted)"
estimated_runtime_minutes: 5
---

# Setup wizard first-run + headers regression sentinel

Admin runs the setup wizard end-to-end on a fresh install. Picks listing types, optionally seeds a demo pack, completes wizard, clicks "Go to Dashboard". Sentinel for #9867159785 (post-submission caused `Cannot modify header information - headers already sent` then a blank page when the user lacked `edit_listora_listings` cap).

## Setup

- Site: `$SITE_URL`
- Admin: `?autologin=1`
- Reset wizard state:
  ```bash
  wp option delete wb_listora_setup_complete
  wp option delete wb_listora_show_wizard_redirect
  ```
- Baseline debug.log byte count.

## Steps

### 1. Land on dashboard — wizard auto-redirect fires
- **Action**: `playwright_navigate $SITE_URL/wp-admin/?autologin=1`
- **Expect**: 302 (or follow-up navigation) to `admin.php?page=listora-setup`. URL settles on the wizard.
- **On fail**: `wb_listora_show_wizard_redirect` transient logic in `class-activator.php` or admin_init redirect handler

### 2. Step 1 — pick listing types
- **Action**: tick at least 2 listing types (Business + Restaurant) → click Next
- **Expect**: wizard advances to step 2 without page-reload jump back to step 1

### 3. Step 2 — optionally seed demo content
- **Action**: pick "Restaurant" demo pack → click "Seed demo content"
- **Expect**:
  - Network: `POST /wp-json/listora/v1/setup/seed-demo` returns 200 with seeded counts
  - DB:
    ```sql
    SELECT COUNT(*) FROM wp_posts WHERE post_type='listora_listing' AND post_title LIKE '%Demo%';
    ```
    ≥ 3 rows

### 4. Step 3+ — complete remaining wizard steps
- **Action**: walk through any remaining steps (Settings defaults / Email config) → click "Done" or "Finish"
- **Expect**: success message, `wb_listora_setup_complete` option = `1`

### 5. CRITICAL — Click "Go to Dashboard" (regression sentinel)
- **Action**: from the Done step, click the "Go to Dashboard" button
- **Expect**:
  - Lands on `admin.php?page=listora` (NOT a blank white page)
  - Debug.log diff has ZERO `Cannot modify header information - headers already sent` entries
- **On fail**: regression of #9867159785. The setup wizard's POST handler must run on `admin_init` priority 1 BEFORE any output. See `Setup_Wizard::init()` + `handle_post_submission()` static pair (commit 5b4840f follow-up).

### 6. Verify essential pages auto-created
- **Action**:
  ```bash
  wp post list --post_type=page --pagename=listings --field=ID
  wp post list --post_type=page --pagename=add-listing --field=ID
  wp post list --post_type=page --pagename=dashboard --field=ID
  ```
- **Expect**: each returns a single ID (not duplicate, not zero). Activator auto-creates these idempotently.

### 7. Re-run wizard (idempotency)
- **Action**: navigate back to `admin.php?page=listora-setup` (without resetting options)
- **Expect**: wizard renders cleanly, doesn't duplicate seeded listings or pages on save

## Pass criteria

1. First admin pageload after activation auto-redirects to wizard
2. Walking through all steps completes without a blank page
3. "Go to Dashboard" button lands on Listora dashboard, NOT a blank page
4. Debug.log has zero `headers-already-sent` entries during the walk
5. Essential pages created idempotently (no duplicates on re-run)
6. Demo seed creates real listings if selected

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Wizard never auto-redirects | transient not set on activate | `class-activator.php` |
| Wizard step submits and page goes white | regression of #9867159785 (headers-already-sent) | `Setup_Wizard::handle_post_submission` — must run at `admin_init` priority 1 BEFORE any output |
| "Go to Dashboard" → blank page | same regression class — different code path | `Setup_Wizard::handle_done_redirect` capability gate (#9867159785 also covered users with `manage_listora_settings` but NOT `edit_listora_listings`) |
| Demo seed POST 500 | `Demo_Seeder` crash | `class-demo-seeder.php`, debug.log |
| Essential pages duplicated on re-run | activator idempotency broken | check page slug + content match before creating |
