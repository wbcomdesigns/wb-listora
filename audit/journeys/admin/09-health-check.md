---
journey: admin-health-check
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [health-check-page, cron-health, db-version-check]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1)"
estimated_runtime_minutes: 3
---

# Admin Health Check surfaces real warnings

Admin opens Health Check page. Verifies it lists actionable items and reflects current state. Then deactivates cron transport temporarily and confirms the warning surfaces with a fix CTA.

## Setup

- Site: `$SITE_URL`
- Admin: `?autologin=1`

## Steps

### 1. Open Health Check
- **Action**: `playwright_navigate $SITE_URL/wp-admin/admin.php?page=listora-health&autologin=1`
- **Expect**: page renders (may redirect to Settings → Advanced) with a list of checks: cron, DB version, search index population, FULLTEXT index, email verification

### 2. Baseline — all green
- **Action**: take a snapshot of check list
- **Expect**: each row has status badge (Pass / Warn / Fail) + "Last run" timestamp + Run-now CTA

### 3. Trigger a known warning — break Action Scheduler temporarily
- **Action**:
  ```bash
  wp action-scheduler unschedule wb_listora_expire_listings
  ```
  Then refresh the Health page.
- **Expect**: cron-health row turns Warn or Fail with message "Expire Listings cron not scheduled" + Run-now or Reschedule button

### 4. Click the fix CTA
- **Action**: click the Reschedule / Run-now button
- **Expect**:
  - REST request fires (or admin-post)
  - Page reloads, cron row back to Pass

### 5. Verify schedule restored
- **Action**:
  ```bash
  wp action-scheduler list --status=pending --hooks=wb_listora_expire_listings
  ```
- **Expect**: ≥1 row pending

### 6. Verify search-index check
- **Action**: artificially clear an index row:
  ```sql
  DELETE FROM wp_listora_search_index WHERE id=(SELECT id FROM wp_listora_search_index LIMIT 1);
  ```
  Refresh Health page.
- **Expect**: index row count mismatch warning surfaces with Reindex CTA. Click Reindex → search index repopulates.
- **On fail**: Health Check doesn't compare counts OR Reindex CTA missing

### 7. Verify FULLTEXT index check
- **Action**: temporarily drop the FULLTEXT index:
  ```sql
  ALTER TABLE wp_listora_search_index DROP INDEX searchable_fulltext;
  ```
  Refresh Health.
- **Expect**: warning surfaces with Re-add CTA. Click → index re-added.
- **On fail**: regression of 7606f8c FULLTEXT split fix.

## Pass criteria

1. Health Check page renders with real, actionable status rows
2. Each warning has an inline fix CTA that works
3. Cron disruption + repair round-trip works
4. Search index + FULLTEXT checks detect tampering

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Page is empty or redirects forever | health page not registered | `class-admin.php` admin_menu hooks |
| All checks always green even when broken | checks not actually running | `class-health-check.php` |
| Fix CTAs don't work | REST/admin-post handlers missing | health page action handlers |
