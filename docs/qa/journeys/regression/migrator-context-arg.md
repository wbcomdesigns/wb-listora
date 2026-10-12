---
journey: migrator-context-arg
plugin: wb-listora
priority: high
roles: [admin, system]
covers: [wb_listora_listing_submitted-context-arg, notifications-skip-on-migration, buddypress-skip-on-migration, pricing-plans-skip-on-migration, audit-log-still-runs-on-migration]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI against this site's path"
  - "Both wb-listora and wb-listora-pro active (2.0.0)"
  - "Pro toggles: pricing_plans ON, buddypress_integration ON (if BuddyPress installed), audit_log ON, moderator ON with >=1 active moderator for step 6"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
changed: true
---

# Bulk-importer fires of `wb_listora_listing_submitted` carry migration context

The competitor migrators are FREE classes (`includes/import-export/class-{directorist,listingpro,geodirectory,bdp,hivepress}-migrator.php`; e.g. Directorist fires at `:162`). Each fires `do_action( 'wb_listora_listing_submitted', $post_id, 'publish', null, array( 'source' => 'migration', 'migrator' => static::class ) )`. Listeners that must stay quiet: Free `Notifications::listing_submitted()` (`includes/workflow/class-notifications.php:255`), Pro `Pricing_Plans::handle_plan_on_submit()` (`wb-listora-pro/includes/features/class-pricing-plans.php:1186`), Pro `BuddyPress_Integration::activity_listing_published()` (`class-buddy-press-integration.php:141`). Listeners that intentionally still run: Pro `Audit_Log::on_listing_submitted()` (`class-audit-log.php:683`) and `Moderator::assign_listing()` (`class-moderator.php:327`, writes `_listora_assigned_moderator`).

Verified via: `wp eval` firing the hook for a QA-J1 listing once with the migration context and once without (positive control), counting `wp_listora_email_log` (`event_key`) and `wp_listora_audit_log` (`action`). A full Directorist migration and the BuddyPress / moderator halves were not run (no source plugin, no BuddyPress, feature `moderator` is off with 0 active moderators on this site).

## Setup
- `pre_wp_mail` filter in the eval script so no mail leaves; seed rows prefixed `QA-J1-mig-`.
- Counters: `SELECT COUNT(*) FROM wp_listora_email_log WHERE event_key='listing_submitted'` and `SELECT COUNT(*) FROM wp_listora_audit_log WHERE action='listing_submitted'`.

## Steps

### 1. Frontend-style fire (positive control)
- **Action**: create a published `listora_listing` (post_author 6), `do_action( 'wb_listora_listing_submitted', $id, 'publish', null, array() )`.
- **Expect**: email_log +1 (mail sent via `pre_wp_mail`), audit_log +1.

### 2. Migration fire is quiet
- **Action**: same with `array( 'source' => 'migration', 'migrator' => 'X' )` on another listing.
- **Expect**: email_log +0, mails sent 0 (observed 0/0), audit_log +1 (the migration IS auditable).

### 3. BuddyPress activity (skip if BP inactive)
- **Expect**: no new `wb_listora_listing_published` activity items for the migrated listings.

### 4. Pricing plans
- **Expect**: no plan credits held/deducted for migrated listings (`wp_listora_credit_ledger` gains no rows for them).

### 5. Audit log intentionally ran
- **Expect**: exactly one `listing_submitted` row per migrated listing (observed +1).

### 6. Moderator assigned (needs an active moderator)
- **Expect**: `get_post_meta( $id, '_listora_assigned_moderator', true )` non-empty for each migrated listing. Not exercised here: feature off.

### 7. A real migration (optional, needs a source plugin)
- **Action**: `wp eval '$m = wb_listora_get_migrator( "directorist" ); foreach ( get_posts( array( "post_type" => "at_biz_dir", "numberposts" => 5 ) ) as $p ) { $m->migrate_listing( $p->ID ); }'`.
- **Expect**: 5 new published listings and steps 2-6 hold. (`wb_listora_get_migrators()` lists the registered keys.)

### 8. Debug log clean
- **Expect**: no new PHP fatal/warning/notice in `wp-content/debug.log` from plugin code.

### 9. Role ladder
System/admin operation. Admin (control) runs it; a member cannot start or poll a migration (the background-import REST routes are `manage_options`-gated: 403 `listora_forbidden`, `includes/import-export/class-background-import.php:833,858,1008`); logged-out gets 401. Another member's own submissions fire the same hook with an EMPTY context, i.e. step 1 behaviour.

## Pass criteria
Step 2 emails 0; step 5 audit +1 per listing; step 1 control shows the listeners work (otherwise steps 2-4 are vacuous).

## Teardown
Delete `QA-J1-mig-*` listings and their email_log/audit_log rows.
