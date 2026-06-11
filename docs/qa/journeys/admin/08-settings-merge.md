---
journey: admin-settings-tabs-merge
plugin: wb-listora
priority: high
roles: [administrator]
covers: [settings-page, settings-tabs-merge, settings-reset, get-setting-helper]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1)"
estimated_runtime_minutes: 5
---

# Settings tabs save independently and reset cleanly

Admin walks every Settings tab, saves a value in each, verifies edits in one tab DON'T drop values from others (D.metabox-fields-merged sentinel), then resets all settings to defaults and verifies Pro options are also purged via the `wb_listora_after_reset_settings` action.

## Setup

- Site: `$SITE_URL`
- Admin: `?autologin=1`
- Snapshot baseline:
  ```bash
  wp option get wb_listora_settings --format=json > /tmp/baseline.json
  ```

## Steps

### 1. Open Settings → General
- **Action**: `playwright_navigate $SITE_URL/wp-admin/admin.php?page=listora-settings&tab=general&autologin=1`
- **Expect**: General tab renders with site name, default type, currency, etc.

### 2. Change General tab value + save
- **Action**: change "Default listing type" → save
- **Expect**: success notice, value persists on reload

### 3. Switch to Submission tab
- **Action**: click Submission tab
- **Expect**: tab renders with all Submission settings (guest submissions toggle, captcha, expiration, auto-publish, etc.)

### 4. Change Submission value + save
- **Action**: change "Days before a new listing expires" to `45` → save
- **Expect**: success notice

### 5. Switch back to General tab
- **Action**: click General tab
- **Expect**: General tab values intact (default listing type still shows the value from step 2). **D.metabox-fields-merged sentinel.**
- **On fail**: regression — `Settings::save()` is replacing the entire option instead of merging changed keys.

### 6. Walk every remaining tab
- **Action**: visit Search, Maps, Notifications, Advanced, Features, Import/Export tabs in turn. Save a known change in each.
- **Expect**: each tab renders without fatal. Values persist independently.

### 7. Verify get_setting() helper still resolves correctly
- **Action**:
  ```bash
  wp eval 'echo wb_listora_get_setting("expiration_days");'
  ```
- **Expect**: `45` (last saved value)

### 8. Reset Settings
- **Action**: Settings → Advanced (or wherever Reset lives) → click Reset → confirm via `listoraConfirm`
- **Expect**:
  - Success notice
  - All settings revert to defaults
  - Pro options also purged (combo mode):
    ```bash
    wp option get wb_listora_pro_white_label  # should be unset or default
    ```

### 9. Verify Pro reset listener fired (combo)
- **Action**: register a debug logger on `wb_listora_after_reset_settings` first, OR check audit log if Pro_Plugin has logging
- **Expect**: action fired exactly once on reset
- **On fail**: regression of 2026-04-30 PM symmetric reset fix — Pro must listen on `wb_listora_after_reset_settings` + `wb_listora_reset_option_keys`

### 10. Restore baseline (cleanup)
- **Action**:
  ```bash
  wp option update wb_listora_settings "$(cat /tmp/baseline.json)" --format=json
  ```

## Pass criteria

1. Each Settings tab renders without fatal
2. Saving one tab does NOT drop values from another (merge, not replace)
3. `wb_listora_get_setting()` helper resolves to the last saved value
4. Reset reverts to defaults + purges Pro options via the documented action

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Saving one tab clears another | settings save replaces option wholesale | `class-settings-page.php::save` — must `array_merge` |
| Pro options not purged on reset | Pro listener missing | `wb-listora-pro/includes/class-pro-plugin.php` listener on `wb_listora_after_reset_settings` |
| Native confirm() on Reset | regression | should use `listoraConfirm` |
| `get_setting` returns stale | option cache not flushed | `wp_cache_delete('alloptions','options')` after save |
