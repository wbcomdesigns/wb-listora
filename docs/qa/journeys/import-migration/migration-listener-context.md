---
journey: migration-listener-context
group: import-migration
actor: system
mode: cli
plugin: wb-listora
priority: high
roles: [administrator, system]
covers: [wb_listora_listing_submitted-context-arg, notifications-skip-on-migration, buddypress-skip-on-migration, pricing-plans-skip-on-migration, audit-log-still-runs-on-migration]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH"
  - "Free and Pro active; Pro toggles pricing_plans ON, buddypress_integration ON (if BuddyPress installed), audit_log ON, moderator ON with at least 1 active moderator for step 6"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes:
  - ora:regression/migrator-context-arg.md
fails_on: unknown (per-step sha not recorded; positive control in step 1 proves the listeners work)
extends: import-migration/migrate-from-competitor-plugin.md
site_profile: required
---

# Bulk-import fires of `wb_listora_listing_submitted` carry the migration context, and the right listeners stay quiet

## Why
Every competitor migrator fires `wb_listora_listing_submitted( $post_id, 'publish', null, [ 'source' => 'migration', 'migrator' => static::class ] )`. If a listener ignores the context, a migration emails every owner, burns plan credits or floods BuddyPress activity. Listeners that must stay quiet: Free `Notifications::listing_submitted()`, Pro `Pricing_Plans::handle_plan_on_submit()`, Pro `BuddyPress_Integration::activity_listing_published()`. Listeners that intentionally still run: Pro `Audit_Log::on_listing_submitted()` and `Moderator::assign_listing()`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- A `pre_wp_mail` filter in the eval script so no mail leaves; seed rows prefixed `QA-mig-`.
- Counters: `SELECT COUNT(*) FROM ${PREFIX}listora_email_log WHERE event_key='listing_submitted'` and `SELECT COUNT(*) FROM ${PREFIX}listora_audit_log WHERE action='listing_submitted'`.

## Steps

### 1. Frontend-style fire (positive control)
- **Action**: create a published `listora_listing` owned by {owner}; `do_action( 'wb_listora_listing_submitted', $id, 'publish', null, array() )`.
- **Expect**: email_log +1 (mail captured by `pre_wp_mail`), audit_log +1.

### 2. Migration fire is quiet
- **Action**: same on another listing with `array( 'source' => 'migration', 'migrator' => 'X' )`.
- **Expect**: email_log +0, mails sent 0, audit_log +1 (a migration is auditable).

### 3. BuddyPress activity (skip if BuddyPress inactive)
- **Expect**: no new `wb_listora_listing_published` activity items for migrated listings; step 1's listing does create one.

### 4. Pricing plans
- **Expect**: no credits held or deducted for migrated listings (`${PREFIX}listora_credit_ledger` gains no rows for them); step 1's listing under a plan does.

### 5. Audit log intentionally ran
- **Expect**: exactly one `listing_submitted` audit row per migrated listing.

### 6. Moderator assigned (needs an active moderator)
- **Expect**: `get_post_meta( $id, '_listora_assigned_moderator', true )` non-empty for each migrated listing. Not verified live: feature was off.

### 7. A real migration (needs a source plugin)
- **Action**: `wp eval '$m = wb_listora_get_migrator( "<source key>" ); foreach ( get_posts( array( "post_type" => "<source post type>", "numberposts" => 5 ) ) as $p ) { $m->migrate_listing( $p->ID ); }'`
- **Expect**: 5 new published listings and steps 2-6 hold.

### 8. Debug log clean
- **Expect**: no new PHP fatal, warning or notice from plugin code in `wp-content/debug.log`.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-8 | pass |
| {owner} | start or poll a background migration via REST | 403 `listora_forbidden` |
| {guest} | same | 401 |
| {owner} | own frontend submission | fires the hook with an EMPTY context, i.e. step 1 behaviour |

## Pass criteria
Step 2 emails 0; step 5 audit +1 per listing; step 1 shows the listeners work (otherwise steps 2-4 are vacuous).

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Migration sends approval mail | Notifications ignores context | `includes/workflow/class-notifications.php` (`listing_submitted`) |
| Credits deducted by a migration | plan listener ignores context | Pro `includes/features/class-pricing-plans.php` (`handle_plan_on_submit`) |
| Activity posted | BP listener ignores context | Pro `class-buddy-press-integration.php` (`activity_listing_published`) |
| Fire carries no context | migrator action call | `includes/import-export/class-<source>-migrator.php` |

## Not verified
Steps 3, 4 (plans half), 6 and 7 were not run: no source plugin, no BuddyPress, moderator feature off on the reference site.

## Cleanup
Delete `QA-mig-*` listings and their email_log and audit_log rows.
