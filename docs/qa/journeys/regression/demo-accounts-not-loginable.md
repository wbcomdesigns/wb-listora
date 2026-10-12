---
journey: demo-accounts-not-loginable
plugin: wb-listora
priority: critical
roles: [anonymous, admin]
covers: [10379561541, demo-import, security]
prerequisites:
  - "Site reachable at http://wb-listora.local"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# Demo accounts cannot be signed in to

Demo import (setup wizard, Settings > Re-run Demo Import, `wp listora demo seed`) creates member accounts flagged `_listora_demo_user` (`demo/class-demo-seeder.php:1040-1055`) with `wp_generate_password( 24 )`. Migration `1.9.1` (`includes/db/class-migrator.php:526` `migrate_1_9_1()`) re-randomises any flagged account still on the old shared password `password`. Verified via: `wp eval` + curl against wp-login.php on 2026-10-12.

## Steps

### 1. Existing accounts
- **Action**: `wp eval-file` over `SELECT user_id FROM wp_usermeta WHERE meta_key='_listora_demo_user'` calling `wp_check_password( 'password', $user->user_pass, $id )`.
- **Expect**: false for every one (this site: contributor1, author1, subscriber2, subscriber3).
- **POSITIVE CONTROL**: create `QA-J1-stuck` with password `password` and the flag; step 2 shows the check being true before the migration.

### 2. Migration resets only flagged accounts on the shipped password
- **Action**: create `QA-J1-stuck` (pass `password`, flagged) and `QA-J1-changed` (pass `owner-chose-this`, flagged); run `\WBListora\DB\Migrator::migrate_1_9_1();`; `clean_user_cache`; re-check. Delete both users. (Do not set `wb_listora_db_version` back to 1.9.0: it is `1.9.1.14` now and that would re-run every later migration.)
- **Expect**: stuck no longer accepts `password`; changed still accepts `owner-chose-this`. Verified live 2026-10-12.

### 3. Real login form
- **Action**: POST `/wp-login.php` with `log=author1&pwd=password`.
- **Expect**: `#login_error` reads "Error: The password you entered for the username author1 is incorrect." Verified live.

### 4. Role ladder
- Anonymous is the attacker (steps 1-3). Admin only as the control that can still reset a demo password from Users. No member can log in as another member.

## Pass criteria

1. No demo account accepts `password` after import or upgrade.
2. An owner-changed demo password is untouched.
3. `tests/integration/MigratorTest.php:65` `test_1_9_1_resets_only_demo_accounts_on_the_shipped_password` passes.

## Fail diagnostics

| Symptom | File |
|---|---|
| Fresh import uses `password` | `demo/class-demo-seeder.php:1040` user creation |
| Existing account still logs in | `includes/db/class-migrator.php:526` `migrate_1_9_1()` and its map entry at line 59 |
