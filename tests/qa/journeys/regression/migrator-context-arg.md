---
journey: migrator-context-arg
plugin: wb-listora
priority: high
roles: [admin, system]
covers: [wb_listora_listing_submitted-context-arg, notifications-skip-on-migration, buddypress-skip-on-migration, pricing-plans-skip-on-migration, audit-log-still-runs-on-migration]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WP_DEBUG + WP_DEBUG_LOG enabled"
  - "Both wb-listora and wb-listora-pro active at 1.1.0+"
  - "Pro feature toggles: pricing_plans=ON, buddypress_integration=ON (if BP installed), audit_log=ON, moderator=ON"
estimated_runtime_minutes: 4
---

# Bulk-importer fires of `wb_listora_listing_submitted` carry migration context

Pro's `Base_Migrator::import_listing()` fires Free's `wb_listora_listing_submitted` action with a `context` array containing `'source' => 'migration'`. Free's `Notifications::listing_submitted()` listener and Pro's `BuddyPress_Integration::activity_listing_published()` + `Pricing_Plans::handle_plan_on_submit()` listeners must short-circuit on that context — otherwise a 2,000-listing competitor migration sends 2,000 admin emails, posts 2,000 activity items, and tries to deduct credits 2,000 times.

Pro's `Audit_Log::on_listing_submitted()` listener and `Moderator::assign_listing()` listener INTENTIONALLY still fire — migrated listings belong in the audit log + need moderator assignment.

Pre-fix discovered 2026-05-18 during the migrator-consolidation Phase 1 audit (`bin/cleanup-duplicate-detect.php` surfaced the bare fire).

## Setup

- Site: `$SITE_URL`
- Truncate debug.log + clear notification + audit log fixtures:
  ```bash
  > /Users/varundubey/Local\ Sites/directory/app/public/wp-content/debug.log
  wp eval 'global $wpdb; $p = $wpdb->prefix . "listora_"; $wpdb->query("DELETE FROM {$p}email_log WHERE event = \"listing_submitted\""); $wpdb->query("DELETE FROM {$p}audit_log WHERE event_type = \"listing.submitted\"");'
  ```
- Seed a Directorist fixture (5 listings):
  ```bash
  wp eval-file wp-content/plugins/wb-listora/bin/seed-demo.php
  # Then manually create 5 wpbdp/at_biz_dir source listings via WP-CLI insert-post
  ```
- Capture pre-test counts:
  ```bash
  EMAILS_BEFORE=$(wp eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}listora_email_log WHERE event = \"listing_submitted\"");')
  AUDIT_BEFORE=$(wp eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}listora_audit_log WHERE event_type = \"listing.submitted\"");')
  ```

## Steps

### 1. Run Pro's bulk migrator against the seeded fixture
- **Action**:
  ```bash
  wp eval '
  $migrator = wb_listora_get_migrator( "directorist" );
  foreach ( get_posts( array( "post_type" => "at_biz_dir", "posts_per_page" => 5 ) ) as $p ) {
      $migrator->migrate_listing( $p->ID );
  }
  '
  ```
- **Expected**: 5 new `listora_listing` rows in `wp_posts`, post_status = publish.
- **Assert**:
  ```bash
  wp eval 'echo (int) wp_count_posts( "listora_listing" )->publish;'
  # should be >= 5
  ```

### 2. Verify Notifications listener SHORT-CIRCUITED on migration context
- **Action**:
  ```bash
  EMAILS_AFTER=$(wp eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}listora_email_log WHERE event = \"listing_submitted\"");')
  echo "Before=$EMAILS_BEFORE  After=$EMAILS_AFTER  (delta should be 0)"
  ```
- **Expected**: `EMAILS_AFTER - EMAILS_BEFORE == 0`. No admin notification emails sent for the 5 migrated listings.
- **Fail if**: delta > 0 — Notifications listener didn't gate on `$context['source'] === 'migration'`. Check `class-notifications.php:212-217`.

### 3. Verify BuddyPress activity listener SHORT-CIRCUITED (skip if BP not active)
- **Action**:
  ```bash
  wp eval 'if ( function_exists( "bp_activity_get" ) ) { echo (int) bp_activity_get( array( "filter" => array( "action" => "wb_listora_listing_published" ), "per_page" => 100 ) )["total"]; }'
  ```
- **Expected**: BP activity count BEFORE = AFTER for migration window.
- **Fail if**: 5 new activity items appeared — `BuddyPress_Integration::activity_listing_published()` didn't gate on migration context.

### 4. Verify Pricing_Plans listener SHORT-CIRCUITED
- **Action**: Check no `_listora_plan_credits` meta was set on the 5 migrated listings:
  ```bash
  wp eval '
  $latest = get_posts( array( "post_type" => "listora_listing", "posts_per_page" => 5, "orderby" => "ID", "order" => "DESC" ) );
  foreach ( $latest as $p ) {
      $plan = get_post_meta( $p->ID, "_listora_plan_credits", true );
      echo $p->ID . ":plan=" . ($plan ?: "(unset)") . PHP_EOL;
  }
  '
  ```
- **Expected**: every listing prints `plan=(unset)` — Pricing_Plans did not run, did not deduct credits.
- **Fail if**: any listing has `_listora_plan_credits` set — `handle_plan_on_submit()` ran when it should have been gated.

### 5. Verify Audit_Log listener INTENTIONALLY ran (5 new rows)
- **Action**:
  ```bash
  AUDIT_AFTER=$(wp eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}listora_audit_log WHERE event_type = \"listing.submitted\"");')
  echo "Audit log delta: $(( AUDIT_AFTER - AUDIT_BEFORE ))  (expected 5)"
  ```
- **Expected**: audit_log gained 5 rows. The migration IS auditable — that's the design.
- **Fail if**: delta != 5 — Audit_Log was wrongly gated on migration context. Migration trail must remain in the audit log.

### 6. Verify Moderator assigned listings (5 assignments)
- **Action**:
  ```bash
  wp eval '
  $latest = get_posts( array( "post_type" => "listora_listing", "posts_per_page" => 5, "orderby" => "ID", "order" => "DESC" ) );
  foreach ( $latest as $p ) {
      echo $p->ID . ":moderator=" . get_post_meta( $p->ID, "_listora_pro_moderator_id", true ) . PHP_EOL;
  }
  '
  ```
- **Expected**: every listing has a non-empty `_listora_pro_moderator_id`. Migrated listings still need a real human reviewer.
- **Fail if**: any listing missing moderator assignment — `Moderator::assign_listing()` was wrongly gated.

### 7. Debug log clean
- **Action**:
  ```bash
  tail -200 /Users/varundubey/Local\ Sites/directory/app/public/wp-content/debug.log | grep -E "PHP (Fatal|Warning|Notice|Deprecated)" | grep -v "wp-includes\|wp-admin"
  ```
- **Expected**: empty. No new fatals / warnings / notices from plugin code during the migration run.

## Teardown

```bash
wp eval '
$latest = get_posts( array( "post_type" => "listora_listing", "posts_per_page" => 5, "orderby" => "ID", "order" => "DESC", "fields" => "ids" ) );
foreach ( $latest as $id ) {
    wp_delete_post( $id, true );
}
'
> /Users/varundubey/Local\ Sites/directory/app/public/wp-content/debug.log
```

## Pass criteria

- Step 1: 5 listings created
- Step 2: ZERO notification emails
- Step 3: ZERO new BP activity items (or BP inactive — skip)
- Step 4: ZERO `_listora_plan_credits` meta writes
- Step 5: EXACTLY 5 audit_log rows added (migration IS auditable)
- Step 6: ALL 5 listings have moderator assigned
- Step 7: clean debug.log

Fails any → fix at: `includes/workflow/class-notifications.php:212-217` (Free), `wb-listora-pro/includes/features/class-pricing-plans.php:373-380` (Pro), `wb-listora-pro/includes/features/class-buddy-press-integration.php:124-132` (Pro).
