---
journey: admin-notices-are-actually-visible
plugin: wb-listora
priority: critical
roles: [administrator, editor]
covers: [BC-10190572606, BC-10154189084, BC-10184284834, BC-10154198434, admin-notices, computed-style]
prerequisites:
  - "Site reachable at $SITE_URL with Free + Pro active"
  - "Auto-login mu-plugin present (?autologin=1)"
  - "For the Maps notice: settings map_provider = google with google_maps_key empty (restore afterwards)"
  - "For the licence notice: Pro running without an active licence (the reference site is licensed, so this notice is not shown there)"
estimated_runtime_minutes: 6
covers_card: 10190572606
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
changed: true
---

# An admin notice must be VISIBLE, not merely present

`assets/css/admin.css:586-590` hides `.wb-listora-admin .notice:not(.listora-notice)`, `.update-nag` and `.updated:not(.listora-notice)` with `display: none !important`, so third-party notices do not clutter Listora screens. Every `class="notice` Listora renders inside a `.wb-listora-admin` wrapper must therefore carry `listora-notice`, or it hides itself.

This is unrecoverable rather than cosmetic: our notices are scoped to Listora screens, so the only screens they render on are the only screens that hide them.

## Why this journey asserts computed style

Three cards were each fixed correctly, signed off as "browser-verified", and shipped invisible. The markup was present every time. `innerText` returns the sentence whether or not the element is `display: none`, so reading markup, grepping HTML or calling `innerText` on an ancestor all report success while the owner sees nothing. Only `getComputedStyle` on the element itself catches it. Any journey that covers an admin notice must assert computed visibility.

## Steps

### 1. The Maps notice renders and is visible
- **Setup**: set `map_provider` to `google` and leave `google_maps_key` empty (`wp eval '$s=get_option("wb_listora_settings"); $s["map_provider"]="google"; $s["google_maps_key"]=""; update_option("wb_listora_settings",$s);'`; save the original option first and restore it).
- **Action**: open `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=maps`.
- **Assert** (in the browser, not on the HTML source):

```js
[...document.querySelectorAll('.wb-listora-admin .notice')].map(el => ({
  cls: el.className,
  display: getComputedStyle(el).display,
  visibility: getComputedStyle(el).visibility,
}))
```

- **Expect**: every row reports `display` other than `none` and `visibility: visible`; a notice reading "OpenStreetMap is still live. You selected Google Maps above, but no API key is saved yet - add a key below to switch the frontend." (`.notice.listora-notice.notice-info.inline`) is among them. Source: `wb-listora-pro/includes/features/class-google-maps.php:330`. Markup verified live 2026-10-12 with provider set to google and then restored.
- **POSITIVE CONTROL (provider back on OSM)**: the same tab shows the different notice "Maps use the tile source above. To use Google Maps, choose Google as the provider and save an API key below." and NOT the "still live" one.

### 2. The licence notice on a Listora screen
- **Expect** (unlicensed Pro only): "WB Listora Pro is running, but plugin updates are paused until this site has an active license. Your website features are unaffected." with an "Enter License Key" link to `page=listora-settings&tab=license` and a "Dismiss" link, `.notice.listora-notice.notice-warning`, visible by the same measurement. Always shown on the Plugins screen and Settings > License; elsewhere in Listora until each user dismisses it. Source: `wb-listora-pro/includes/class-feature-manager.php:165-198`. The brand name follows White Label.
- **Not verified**: the reference site is licensed, so this notice could not be rendered; the wording above comes from source.

### 3. The counterfactual proves the assertion has teeth
```js
const el = document.querySelector('.wb-listora-admin .notice.listora-notice');
const withClass = getComputedStyle(el).display;
el.classList.remove('listora-notice');
const without = getComputedStyle(el).display;
el.classList.add('listora-notice');
({ withClass, without });
```
- **Expect**: `{ withClass: "block", without: "none" }`. If `without` is not `"none"`, the suppression rule has changed and this journey needs rewriting.

### 4. Static rule - the class, not the instance
`bin/coding-rules-check.sh` Rule 10 fails the build if any `class="notice` in `includes/`, `blocks/` or `templates/` omits `listora-notice`:

```bash
bash bin/coding-rules-check.sh   # "Rule 10 - every Listora admin notice carries listora-notice" (passes today)
```

Rule 10 was verified to FAIL on a deliberately regressed notice before landing, so a green result is meaningful.

### 5. Role ladder
- **Administrator (control)**: sees the notices above.
- **Editor**: has no `manage_listora_settings`, so Settings is a 403 and its notices are unreachable by design; on screens they can open (Reviews, Claims) the page notices ("N reviews approved...") use `.notice.listora-notice` and are visible (same computed-style check).
- **Subscriber / logged out**: cannot reach any Listora admin screen.

## Note for Pro (open finding)
Pro now has its own `bin/coding-rules-check.sh`, but it carries no notice rule, and at least one Pro notice inside a `.wrap.wb-listora-admin` page still omits the class: `wb-listora-pro/includes/features/class-outgoing-webhooks.php:2651` (`<div class="notice notice-error">` shown for `listora_webhook_error=registry_unavailable`) is hidden by the rule above. `class-user-credits.php:203` renders on core screens (no `.wb-listora-admin` wrapper) and is fine; `class-pricing-plans.php:2867` (`notice listora-screen-intro`) renders on the plain `.wrap` of the plans list and is visible. Port Rule 10 to Pro and fix the webhook notice.

## Fail diagnostics
| Symptom | File |
|---|---|
| Notice present but `display: none` | missing `listora-notice` on the element; CSS at `assets/css/admin.css:586` |
| Rule 10 silent | `bin/coding-rules-check.sh:279-303` |
| Webhook registry error invisible | `wb-listora-pro/includes/features/class-outgoing-webhooks.php:2651` |
