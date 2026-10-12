---
journey: app-password-sign-in
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber, administrator]
covers: [wb_listora_app_password_login, wb_listora_app_password_login_enabled, wb_listora_app_credential_issued, auth-app-password-route, credential-exchange-rate-limit, account-enumeration-guard, app-id-reconnect-replacement, owner-switch-settings-control]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active, setup complete"
  - "A member account whose password you know (note $USER and $PASS). Use the REAL user_login, not 'admin'."
  - "Settings -> Advanced reachable as an administrator"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---
<!-- Single-role system/API journey: the route is anonymous by design (it trades a password for a credential) and there is no object owner, so the owner/other/logged-out ladder does not apply; the roles that matter are anonymous caller (steps 1-5, 8) and administrator (step 6, the switch). Verified via curl + wp-cli against http://wb-listora.local with a throwaway subscriber; the settings-screen toggle (step 6) was verified in source and by flipping the option, not by clicking the checkbox in a browser. -->

# A member can sign in to the app with their WordPress password, and the owner can turn that off

`POST /listora/v1/auth/app-password` trades a member's ordinary WordPress password for a core
Application Password. WordPress core will not do this exchange itself — its Basic auth handler
validates against stored application passwords ONLY, so the core route that mints one already
requires one, and every core path to a member's FIRST credential goes through a wp-admin screen
under cookie auth. This route is what lets a member type the password they already have.

It is NOT a second authentication system. `wp_authenticate()` does the authentication, exactly
as wp-login.php does, so every `authenticate` filter still runs — security plugins, membership
gates, 2FA all get their say.

**The risk this journey exists to police:** the route accepts a real account password, which
makes it a brute-force oracle in a way wp-login.php is not (that page inherits whatever
protection the site has installed). So it is owner-switchable, rate-limited on two independent
buckets before any credential is read, and uniform in its failure message so it cannot be used
to enumerate accounts.

**The critical assertions are steps 4 and 5.** If a wrong username and a wrong password ever
return different answers, we have shipped an account-enumeration oracle. If the rate limit
stops firing, we have shipped a password-guessing endpoint.

## Setup

```bash
SITE=http://wb-listora.local   # or $SITE_URL
USER='REPLACE_WITH_REAL_user_login'
PASS='REPLACE_WITH_REAL_PASSWORD'
R="$SITE/wp-json/listora/v1/auth/app-password"

# Start from a clean slate: the switch ON and no lockout counters held over.
wp option update wb_listora_app_password_login 1
wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%wb_listora_apw_fail_%\"");'
wp eval 'delete_transient("listora_rl_app_password_ip_" . md5("127.0.0.1"));'   # the coarse 20/hour per-IP ceiling
wp user application-password delete "$USER" --all 2>/dev/null
```

Note the option is `wb_listora_app_password_login` and an UNSET option means ON (default `true`); the QA site had it stored as an empty string, which is OFF, so every call returned 403 until step 6 was reversed. Record the starting value (`wp option get wb_listora_app_password_login`) and restore it at the end.
Use a throwaway member (`wp user create qa-j1-app qa-j1-app@example.test --role=subscriber --user_pass=...`), never a real account: five wrong attempts lock that username and your IP for 15 minutes.

## Steps

### 1. Correct credentials mint a credential the app can actually use
- **Action**:
  ```bash
  curl -s -D /tmp/h.txt -X POST "$R" -H 'Content-Type: application/json' \
    -d "{\"username\":\"$USER\",\"password\":\"$PASS\",\"app_name\":\"QA probe\",\"app_id\":\"qa-install-1\"}"
  ```
- **Expect**: HTTP 200 and a body carrying exactly `user_login`, `password`, `app_id`. When `app_name` is omitted the credential is named "Listora app".
- **Assert the credential is real** — the minted password must authenticate a Basic-auth call:
  ```bash
  AP=$(… extract .password from the response …)
  curl -s -o /dev/null -w '%{http_code}\n' -u "$USER:$AP" "$SITE/wp-json/wp/v2/users/me"   # 200
  ```
- **Assert `Cache-Control: no-store`** is present in `/tmp/h.txt`. The body contains a live
  credential; nothing on the path may keep a copy.
- **Assert the account password is NOT echoed** anywhere in the response body.
- **Fail means**: the app cannot sign anyone in, or a credential is being cached by proxies.
- **Preconditions that return 403 `wb_listora_app_passwords_off` instead**: the switch is off ("This site has turned off app sign-in. Ask the site owner to enable it."), the site is not https and not a `local` environment ("This site cannot issue app passwords. It needs a secure (https) connection."), or the account may not use app passwords ("App sign-in is not available for this account.").

### 2. The account password never reaches a log
- **Action**: with `WP_DEBUG_LOG` on, repeat step 1, then `grep -c "$PASS" wp-content/debug.log`.
- **Expect**: `0`.
- **Fail means**: a plaintext account password is on disk. Release blocker.

### 3. Reconnecting the same install REPLACES its credential, never accumulates
- **Action**: run step 1 again with the **same** `app_id` (`qa-install-1`), then:
  ```bash
  wp user application-password list "$USER" --format=count
  ```
- **Expect**: still exactly **1** row (`qa-install-1`). `App_Connect::forget_older_installs()` (`includes/auth/class-app-connect.php:156`) prunes older rows
  carrying the same `app_id` on core's `wp_create_application_password` action.
- **Then** run it once more with a DIFFERENT `app_id` (`qa-install-2`) and expect **2** rows —
  a second device must not evict the first.
- **Fail means**: either a member's credential list grows without bound on every reconnect, or
  signing in on a phone silently signs them out on their tablet.

### 4. A wrong password and a wrong username answer IDENTICALLY (no enumeration)
- **Action**:
  ```bash
  curl -s -X POST "$R" -H 'Content-Type: application/json' \
    -d "{\"username\":\"$USER\",\"password\":\"definitely-wrong\"}"
  curl -s -X POST "$R" -H 'Content-Type: application/json' \
    -d '{"username":"no-such-user-here","password":"definitely-wrong"}'
  ```
- **Expect**: both return **401** with the SAME `code` (`wb_listora_login_failed`) and the SAME
  message ("The username or password you entered is incorrect."). Byte-identical bodies.
- **Fail means**: the route answers "does this account exist?" — an enumeration oracle.
  **Release blocker.**

### 5. Repeated failures lock out, on BOTH buckets, and success clears them
- **Action**: send 5 wrong-password attempts for `$USER`, then a 6th.
- **Expect**: the 6th returns **429** code `wb_listora_too_many_attempts`, "Too many sign-in attempts. Please wait a few minutes and try again.", with `data.retry_after` (about 899) and a `Retry-After` header. `MAX_FAILURES` is 5 over a `FAILURE_WINDOW` of 900s.
- **Assert the lockout beats a correct password**: while locked, the CORRECT password also returns 429 (observed). This is deliberate: an attacker who knows only a username can lock that member's app sign-in for 15 minutes. If that is unacceptable for a site, it is a product decision, not a regression.
- **Assert the coarse ceiling is separate**: 20 exchanges per hour per IP regardless of outcome returns 429 `listora_rate_limit` ("Too many requests from this network. Please try again later.") from `Rate_Limiter` action `app_password`.
- **Assert the username bucket is independent of the IP bucket**: the lockout must key on
  `user:<username>` as well as `ip:<addr>`, so a distributed run at one account is still stopped.
- **Assert only wrong CREDENTIALS count**: a 403 from the owner switch (step 6) or a 409 hand-off
  must NOT increment the counter — those are "your password was fine", and counting them would
  lock out members who did nothing wrong.
- **Assert success clears**: wait out the window (or clear the transients), sign in correctly,
  then confirm a subsequent wrong attempt starts from a fresh count.
- **Fail means**: the endpoint is a password-guessing oracle, or honest members get locked out.

### 6. The owner switch actually switches it off — from the settings screen
- **Action**: as an administrator, go to **Listora -> Settings -> Advanced -> App sign-in** and
  UNCHECK "Let members sign in to the app with their WordPress password". Save.
- **Expect**: `wp option get wb_listora_app_password_login` is empty/`0`, and the route now
  returns **403** `wb_listora_app_passwords_off` with "This site has turned off app sign-in. Ask the site owner to enable it."
- **POSITIVE CONTROL**: re-check the box, save, and the same request returns 200 (the OFF result in step 6 is only meaningful if ON demonstrably works, see step 1).
- Screen: Listora > Settings > Advanced (`admin.php?page=listora-settings&tab=advanced`), block "App sign-in", row "Password sign-in", description "When this is off, the app sends members to the site to sign in instead. Their existing app sign-ins keep working either way."
- **Assert the checkbox round-trips**: reload the settings page — the box must render UNCHECKED.
  The control pairs the checkbox with a hidden `0` precisely because an unchecked box posts
  nothing; without that pairing the switch saves ON but never OFF and silently looks like it
  worked. **Test the OFF direction explicitly — that is the one that regresses.**
- **Assert existing credentials still work** while the switch is off: the Basic-auth call from
  step 1 must still return 200 (observed; core validates the stored application password, not this route). Turning the exchange off stops NEW sign-ins; it must not sign
  out every member who already has the app.
- **Re-check the box and save**; the route must return to answering 401 `wb_listora_login_failed` for a wrong password.
- **Fail means**: the owner cannot turn off a route that accepts account passwords, or turning
  it off logs out the existing install base.

### 7. The filter escape hatch still overrides (production rule 3)
- **Action**: with the setting ON, add `add_filter('wb_listora_app_password_login_enabled','__return_false');`
  in a mu-plugin.
- **Expect**: **403** again. A site owner must be able to restore the old behaviour with one line.
- **Remove the mu-plugin afterwards.**

### 8. The site's own auth rules still get their say
- **Action**: install any plugin that hooks `authenticate` to refuse a login (a 2FA plugin, a
  membership gate), then attempt step 1 with correct credentials.
- **Expect**: the response is **NOT** a 200. A site that wanted a second factor must not be
  bypassed by this route: it answers **409** `wb_listora_second_factor` ("This site needs another step to sign you in. Use the WordPress approval screen instead.") and the app sends the member through the browser flow. Plain credential failures (`incorrect_password`, `invalid_username`, `invalid_email`, `authentication_failed`) map to the uniform 401 instead.
- **Not verified in this refresh** (needs a 2FA/membership plugin installed on the site).
- **Fail means**: the exchange is a 2FA bypass. **Release blocker.**

## Teardown

```bash
wp option update wb_listora_app_password_login 1
wp user application-password delete "$USER" --all
wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%wb_listora_apw_fail_%\"");'
wp user delete qa-j1-app --yes
```
Then restore `wb_listora_app_password_login` to the value you recorded in Setup.

## likely_files

- `includes/rest/class-auth-controller.php:63` route, `:112` `issue_app_password` (both lockout buckets, coarse ceiling, `no-store`)
- `includes/auth/class-app-credentials.php:64` `is_enabled`, `:88` `exchange`, `:34` `MAX_FAILURES`, `:39` `FAILURE_WINDOW`, `:262` `translate_auth_error` (uniform failure)
- `includes/auth/class-app-connect.php:156` `forget_older_installs`
- `includes/admin/class-advanced-settings-tab.php:49` the control; `includes/admin/class-settings-page.php:115` `sanitize_app_password_login`
