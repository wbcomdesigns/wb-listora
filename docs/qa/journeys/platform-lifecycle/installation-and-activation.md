---
journey: installation-and-activation
group: platform-lifecycle
actor: admin
mode: mixed
plugin: wb-listora
priority: critical
roles: [administrator, editor, anonymous]
covers: [activator, db-schema-creation, capability-grant, default-options, setup-wizard-redirect, page-registry-creation, activation-idempotent, rest-namespace]
prerequisites:
  - "Site reachable at $SITE_URL with WordPress 6.4+ and PHP 7.4+; WP-CLI at $WP_PATH"
  - "{admin} logged in (or autologin)"
  - "WB Listora on disk. SAFE deactivate-reactivate cycle on a staging/scratch site only"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
supersedes: []
covers_doc: getting-started/installation
site_profile: required
---

# A fresh install or activation creates schema, caps, default options and pages, then sends the admin to the wizard once

## Why
A clean activation of WB Listora Free must produce a runnable site with no manual setup beyond the wizard (`docs/website/getting-started/installation.md`). A missing table, cap or option is invisible until a feature fails for a customer.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Schema and data upgrades run from `Migrator::maybe_migrate()` at `init` priority 0 against `WB_LISTORA_DB_VERSION` (so no "translation loading was triggered too early" notice). Bumping the constant AND adding a migration is the only path that changes schema.

## Steps

### 1. Pre-activation baseline
- **Action**: `wp plugin deactivate wb-listora --path=$WP_PATH; wp plugin list --path=$WP_PATH | grep wb-listora`
- **Expect**: status `inactive`.

### 2. Activate
- **Action**: `wp plugin activate wb-listora --path=$WP_PATH`
- **Expect**: success, no PHP fatal/warning in stderr, and `debug.log` has no `_load_textdomain_just_in_time` notice.

### 3. Database schema created
- **Action**: `wp eval "global \$wpdb; \$prefix=\$wpdb->prefix.'listora_'; \$expected=['geo','search_index','field_index','reviews','review_votes','favorites','claims','space_listings','hours','analytics','payments','services','email_log','leads']; foreach(\$expected as \$t){ \$tbl=\$prefix.\$t; echo \$t.':'.(\$wpdb->get_var(\"SHOW TABLES LIKE '\$tbl'\")===\$tbl?'1':'0').PHP_EOL; }"`
- **Expect**: every table `:1` (14 core tables; the Credits SDK adds its own `listora_credit_*` tables). The Health Check table card lists fewer: `platform-lifecycle/health-check-and-reindex.md` step 3.

### 4. Post types and taxonomies registered
- **Action**: `wp eval "echo post_type_exists('listora_listing')?'1':'0'; foreach(['listora_listing_cat','listora_listing_type','listora_listing_location','listora_listing_feature','listora_service_cat'] as \$tx) echo ' '.\$tx.':'.(taxonomy_exists(\$tx)?'1':'0');"`
- **Expect**: `1` then every taxonomy `:1`.

### 5. Capabilities granted to the administrator role
- **Action**: `wp eval "\$a=get_role('administrator'); foreach(['manage_listora_settings','manage_listora_types','moderate_listora_reviews','manage_listora_claims','submit_listora_listing'] as \$c) echo \$c.':'.(\$a->has_cap(\$c)?'1':'0').PHP_EOL;"`
- **Expect**: every cap `:1`; other roles' grants: `access-security/role-cap-matrix.md`.

### 6. Default options seeded
- **Action**: `wp eval "\$s=get_option('wb_listora_settings'); echo 'settings-present:'.(\$s?'1':'0').PHP_EOL.'db-version:'.get_option('wb_listora_db_version','').PHP_EOL;"`
- **Expect**: `settings-present:1`; `db-version` non-empty and equal to `Activator::DB_VERSION`.

### 7. The setup wizard redirect fires once
- **Action**: open `$SITE_URL/wp-admin/index.php` as {admin}; then again.
- **Expect**: first load redirects to `?page=listora-setup` (unless `wb_listora_is_setup_complete()` is already truthy), driven by `Activation_Redirect::maybe_redirect()` on the `wb_listora_show_wizard_redirect` transient; the second load does not redirect (never blocks subsequent requests; if repeated, check `wb_listora_setup_complete`). With Pro active it defers to Free: Pro never opens its own wizard until Free setup is complete.

### 8. Front end and REST namespace
- **Action**: `curl -s -o /dev/null -w '%{http_code}' $SITE_URL/` as {guest}; `curl -s $SITE_URL/wp-json/ | jq '.namespaces | index("listora/v1")'`.
- **Expect**: HTTP 200 with no PHP error in the body; the namespace index is non-null.

### 9. Re-activation is idempotent
- **Action**: deactivate and activate again; repeat steps 3-6.
- **Expect**: all checks pass; no duplicate-table errors; no duplicate `wp_options` rows; no duplicated role rows.

### 10. Upgrade from the previous version (added)
- **Action**: set `wb_listora_db_version` to the previous release's value and load any admin page.
- **Expect**: migrations run once, `wb_listora_db_version` returns to `Activator::DB_VERSION`, no data loss. Not verified live.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-10 | pass; completes the wizard redirect |
| {editor} | `admin.php?page=listora-setup` | refused |
| {guest} | wp-admin | never reaches it (login redirect); step 8 front end renders |

## Pass criteria
Every table, post type, taxonomy, cap and default option exists after activation; the wizard redirect fires exactly once; re-activation changes nothing; the front end returns 200.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Table missing | DDL | `includes/class-activator.php`, `includes/db/` |
| Admin repeatedly redirected | redirect flag not cleared | `Activation_Redirect::maybe_redirect()`, `wb_listora_setup_complete` |
| Cap missing | grant | `includes/core/class-capabilities.php` `get_caps_map()` |
| Duplicate rows on reactivate | non-idempotent insert | `Activator` |
| textdomain notice | migrator ran too late | `Migrator::maybe_migrate()` priority |

## Not verified
Step 10. Pro activation has its own journey in Pro's `docs/qa/journeys/`.

## Cleanup
Leave the plugin active; reset `wb_listora_db_version` if step 10 changed it.
