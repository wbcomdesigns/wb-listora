---
journey: demo-accounts-not-loginable
plugin: wb-listora
priority: critical
roles: [anon, admin]
covers: [10379561541]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
---

# Demo accounts cannot be signed in to

Demo import (wizard, settings, `wp listora demo seed`) created ten member accounts, one an author, all with the password `password`. 1.9.1 creates them with random passwords and the 1.9.1 migration resets any demo account still on the shipped password.

## Steps

### 1. Fresh import
- **Action**: Setup wizard -> All packs, or `wp listora demo seed`.
- **Expect**: for every user with `_listora_demo_user`, `wp_check_password( 'password', ... )` is false.

### 2. Upgrade from 1.9.0
- **Action**: `wp_set_password( 'password', <author1 id> )`; `update_option( 'wb_listora_db_version', '1.9.0' )`; load any page.
- **Expect**: db version 1.9.1 and author1 no longer accepts `password`. A demo account whose password was changed keeps it.

### 3. Real login form
- **Action**: `/wp-login.php`, `author1` / `password`.
- **Expect**: "The password you entered ... is incorrect".

## Pass criteria

ALL of the following hold:
1. No demo account accepts `password` after import or upgrade.
2. Owner-changed demo passwords are untouched.
3. `MigratorTest::test_1_9_1_resets_only_demo_accounts_on_the_shipped_password` passes.
