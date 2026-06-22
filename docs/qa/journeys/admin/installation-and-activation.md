---
journey: installation-and-activation
plugin: wb-listora
priority: critical
roles: [administrator]
covers: [activator, db-schema-creation, capability-grant, default-options, setup-wizard-redirect, page-registry-creation]
prerequisites:
  - "Site reachable at $SITE_URL with WordPress 6.4+ and PHP 7.4+"
  - "Admin user logged in (or autologin available)"
  - "WB Listora plugin file available on disk (not necessarily active yet for the full reproduction)"
estimated_runtime_minutes: 8
covers_doc: getting-started/installation
---

# Fresh install / activation creates schema + caps + canonical pages + redirects to wizard

Functional sentinel for the install + activate path documented in `docs/website/getting-started/installation.md`. A clean activation of WB Listora Free must produce a runnable site without manual setup beyond the wizard. This journey verifies the activator's contract.

## Setup

- Site: `$SITE_URL`
- The plugin can be deactivated + reactivated to test the activator from a fresh state. The journey assumes a SAFE deactivate-reactivate cycle in a staging environment (NOT production).

## Steps

### 1. Pre-activation baseline
- **Action**: `wp plugin deactivate wb-listora --path=$WP_PATH; wp plugin list --path=$WP_PATH | grep wb-listora`
- **Expect**: status = `inactive`.

### 2. Activate
- **Action**: `wp plugin activate wb-listora --path=$WP_PATH`
- **Expect**: success message, no PHP fatal/warning in stderr.

### 3. Database schema created
- **Action**:
  ```
  wp eval "
  global \$wpdb;
  \$prefix = \$wpdb->prefix . 'listora_';
  \$expected = ['geo','search_index','field_index','reviews','review_votes','favorites','claims','hours','analytics','payments','services'];
  foreach (\$expected as \$t) {
    \$tbl = \$prefix.\$t;
    echo \$t.':'.(\$wpdb->get_var(\"SHOW TABLES LIKE '\$tbl'\") === \$tbl ? '1' : '0').PHP_EOL;
  }
  "
  ```
- **Expect**: every table reports `:1`.

### 4. Custom post types registered
- **Action**: `wp eval "echo post_type_exists('listora_listing') ? '1' : '0';"`
- **Expect**: `1`.

### 5. Taxonomies registered
- **Action**:
  ```
  wp eval "
  foreach (['listora_listing_cat','listora_listing_type','listora_listing_location','listora_listing_feature','listora_service_cat'] as \$tx) {
    echo \$tx.':'.(taxonomy_exists(\$tx) ? '1' : '0').PHP_EOL;
  }
  "
  ```
- **Expect**: every taxonomy reports `:1`.

### 6. Capabilities granted to admin role
- **Action**:
  ```
  wp eval "
  \$admin = get_role('administrator');
  foreach (['manage_listora_settings','manage_listora_types','moderate_listora_reviews','manage_listora_claims','submit_listora_listing'] as \$cap) {
    echo \$cap.':'.(\$admin->has_cap(\$cap) ? '1' : '0').PHP_EOL;
  }
  "
  ```
- **Expect**: every cap reports `:1`.

### 7. Default options seeded
- **Action**:
  ```
  wp eval "
  \$settings = get_option('wb_listora_settings');
  echo 'settings-present:'.(\$settings ? '1' : '0').PHP_EOL;
  echo 'db-version:'.get_option('wb_listora_db_version','').PHP_EOL;
  "
  ```
- **Expect**: `settings-present:1`, `db-version:` non-empty (matches `Activator::DB_VERSION` constant).

### 8. Setup wizard redirect fires on next admin page-load
- **Action**: visit `$SITE_URL/wp-admin/index.php?autologin=1`.
- **Expect**: redirected to `?page=listora-setup` (the setup wizard) — UNLESS setup is already complete (`wb_listora_is_setup_complete()` returns truthy). This is the one-shot redirect path in `Activation_Redirect::maybe_redirect()` (gated on the `wb_listora_show_wizard_redirect` transient — the single canonical redirect handler). With Pro also active it defers to Free: Pro never opens its own wizard until Free setup is complete, so the admin is never bounced between two wizards.

### 9. Frontend not broken — visit the homepage
- **Action**: visit `$SITE_URL/` in anonymous browser.
- **Expect**: HTTP 200, no PHP error in page body.

### 10. REST API namespace registered
- **Action**:
  ```
  curl -s "$SITE_URL/wp-json/" | python3 -c "import sys,json; d=json.load(sys.stdin); print('listora-namespace:'+('1' if 'listora/v1' in d.get('namespaces',[]) else '0'))"
  ```
- **Expect**: `listora-namespace:1`.

### 11. Re-activate idempotency
- **Action**: deactivate + reactivate again. Re-run steps 3, 4, 5, 6.
- **Expect**: all checks still pass. No duplicate table creation errors. No duplicate row inserts in `wp_options`.

## Notes

- The activator runs schema upgrades on every load via `Activator::maybe_upgrade()` against `Activator::DB_VERSION`. Bumping the constant + running `dbDelta` should be the ONLY path that mutates schema.
- Setup wizard redirect should fire ONCE (after first activation) and never block subsequent admin requests. If admins are repeatedly redirected, check `wb_listora_setup_complete` option logic.
- Pro activation has its own journey (`pro-activation` in Pro's `docs/qa/journeys/system/`). This journey is Free-side only.
