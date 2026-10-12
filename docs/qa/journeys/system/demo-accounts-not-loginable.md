---
journey: demo-accounts-not-loginable
plugin: wb-listora
priority: critical
roles: [guest, admin]
covers: [10379561541, demo-import, security, migration-1.9.1]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI against $WP_PATH"
  - "Demo content imported at least once (setup wizard, Settings > Re-run Demo Import, or `wp listora demo seed`)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
site_profile: required
supersedes:
  - ora:regression/demo-accounts-not-loginable.md
---

# Demo accounts cannot be signed in to

## Why
Demo import creates member accounts flagged `_listora_demo_user` (`demo/class-demo-seeder.php:1040-1055`) with `wp_generate_password( 24 )`. They once shipped with the shared password `password`, so anyone could log in as a seeded member on a live site. Migration `1.9.1` (`includes/db/class-migrator.php:526` `migrate_1_9_1()`) re-randomises any flagged account still on that password.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- List flagged accounts: `SELECT user_id FROM {prefix}usermeta WHERE meta_key='_listora_demo_user'`.

## Steps

### 1. Existing accounts do not accept the shipped password
- **Action**: `wp eval-file` over that list calling `wp_check_password( 'password', $user->user_pass, $id )`.
- **Expect**: false for every one.
- **Read-back**: the count of flagged accounts is > 0 (positive control: create `QA-J1-stuck` with password `password` and the flag; the check is true before step 2).

### 2. The migration resets only flagged accounts on the shipped password
- **Action**: create `QA-J1-stuck` (pass `password`, flagged) and `QA-J1-changed` (pass `owner-chose-this`, flagged); run `\WBListora\DB\Migrator::migrate_1_9_1();`; `clean_user_cache`; re-check. Do NOT set `wb_listora_db_version` back to 1.9.0 (it would re-run every later migration).
- **Expect**: stuck no longer accepts `password`; changed still accepts `owner-chose-this`.
- **Read-back**: `wp_check_password` for both; migration is idempotent (run it twice; `changed` still logs in).

### 3. Fresh import generates random passwords
- **Action**: run a demo import on a scratch site (or `wp listora demo seed`) and repeat step 1.
- **Expect**: every new flagged account fails `password`.
- **Read-back**: `user_pass` hashes differ between accounts. Not verified live.

### 4. Real login form
- **Action**: POST `/wp-login.php` with `log={other}`'s demo login (a flagged persona) and `pwd=password`.
- **Expect**: `#login_error` reads "Error: The password you entered for the username <login> is incorrect."
- **Read-back**: no auth cookie set; REST `GET /wp-json/wp/v2/users/me` with the same Basic credentials is 401.

## Role ladder
| Rung | Expect |
|---|---|
| {guest} (attacker) | steps 1-4 |
| {admin} | control: can still reset a demo password from Users |
| any member | cannot log in as another member |

## Pass criteria
1. No demo account accepts `password` after import or upgrade.
2. An owner-changed demo password is untouched.
3. `tests/integration/MigratorTest.php:65` `test_1_9_1_resets_only_demo_accounts_on_the_shipped_password` passes.

## Fail diagnostics
| Symptom | File |
|---|---|
| Fresh import uses `password` | `demo/class-demo-seeder.php:1040` user creation |
| Existing account still logs in | `includes/db/class-migrator.php:526` `migrate_1_9_1()` and its map entry at line 59 |

## Not verified
Step 3 (fresh import) and the REST Basic-auth read-back.

## Cleanup
Delete `QA-J1-stuck` and `QA-J1-changed`.
