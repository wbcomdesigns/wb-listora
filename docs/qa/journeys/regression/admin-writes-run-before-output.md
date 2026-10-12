---
journey: admin-writes-run-before-output
plugin: wb-listora-pro
roles: [administrator]
priority: high
covers: [headers-already-sent, load-hook-handlers, badges-save, moderator-actions, webhook-save, needs-moderation, BC-10199668750, BC-9927893041]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora AND wb-listora-pro both active"
  - "A theme or plugin that echoes on an early admin hook (Reign does, via a <style id=\"reign-admin-menu-icon\"> on admin_head); the reference site runs BuddyX Pro, which does not, so the failure is only observable on a Reign install - the redirect assertions below still hold"
  - "WP_DEBUG_LOG on, so the warning is observable"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
changed: true
---

# Every Pro admin write runs before output, so its redirect survives

A save that redirects cannot run inside a render callback. By render time WordPress has started the admin body, so `wp_safe_redirect()` emits `PHP Warning: Cannot modify header information - headers already sent by (output started at .../reign-theme/inc/init.php:1194)` and the `exit` after it ends the request where it stands. The record IS written; only the navigation fails, so the user sits on the form they just submitted with no feedback and often saves again.

Coupons hit this first (9927893041): fixed by capturing the hook suffix from `add_submenu_page()` and moving the write to `load-{$hook}`. Badges followed (10199668750), and a sweep found four pages in the same shape:

| Page (`page=` slug) | What runs on `load-{$hook}` | File |
|---|---|---|
| Badges (`listora-badges`) | save, delete, max-badges-per-card, order | `includes/features/class-badges.php:1314-1327` |
| Moderators (`listora-moderators`) | promote / demote / activate, bulk | `class-moderator.php:581-594` |
| Webhooks (`listora-webhooks`) | webhook save, pause/resume/delete/test | `class-outgoing-webhooks.php:2260-2271` |
| Needs (`listora-needs`) | approve / reject / close | `class-reverse-listings.php:737-748` |

## The detector

This class is greppable and **G6** in Free's `bin/audit-guardrails.sh` (lines 133-165, scanning both `$FREE_DIR` and `$PRO_DIR`) fails the build on it. (It was numbered G7 when this journey was written; G7 is now "admin list screens paginate".) Run it by hand from the Pro tree:

```bash
cd wb-listora-pro/includes/features
for f in *.php; do
  sub=$(grep -c "add_submenu_page" "$f")
  hookc=$(grep -c 'load-{\$this->' "$f")
  redir=$(grep -c "wp_safe_redirect\|wp_redirect" "$f")
  if [ "$sub" -gt 0 ] && [ "$redir" -gt 0 ] && [ "$hookc" -eq 0 ]; then
    echo "RENDER-PATH WRITE: $f"
  fi
done
```

Any output is the regression. Run 2026-10-12: no output.

## Steps

Note `wp-content/debug.log`'s line count first.

### 1. Badges (verified live 2026-10-12)
- **Action**: Listora > Badges > Add Badge (`page=listora-badges&action=new`), set a label (form posts `badge_label`, `badge_slug`, `badge_icon`, `badge_color`, `badge_text_color`, `badge_assignment`, nonce `listora_badge_form_nonce`), click Save Badge.
- **Expect**: 302 to `admin.php?page=listora-badges&saved=1` (the list), not `&action=new`; the notice "Badge saved. Listings show the change right away." (`.notice.listora-notice.notice-success`) is computed-visible (`getComputedStyle(el).display !== 'none'`, because a `:not(.listora-notice)` rule hides notices that lack the class); zero new `headers already sent` lines in debug.log.
- Repeat for Delete (row action, `action=delete&badge_id=<id>&_wpnonce=...` -> `&deleted=1`, "Badge deleted.", verified) and for the max-badges-per-card save and reorder ("Badge order saved.").

### 2. Webhooks
- **Requires** the `outgoing_webhooks` toggle ON (it is on here). Webhooks > Add > name + URL > Save.
- **Expect**: redirect to `page=listora-webhooks&updated=1`, the webhook in the list, zero warnings. The form posts a hidden `webhook_id` and the edit view also carries it in the query string; the handler must read the POST value first, or editing silently creates a second webhook.
- **Row actions** (`wh_action=pause|resume|delete|test`, nonce per webhook) redirect with `wh_notice=` and print "Webhook paused...", "Webhook resumed.", "Webhook deleted.", "Test sent and received..." respectively.
- **Open defect (see report)**: the save redirect carries `updated=1`, which the list never reads, so a successful save shows NO confirmation (`class-outgoing-webhooks.php:2799`; notice map at `:2447-2455` has no `saved` entry). Verified: `page=listora-webhooks&updated=1` renders zero notices.

### 3. Moderators (verified live)
- **Action**: Moderators > bulk or row Activate / Pause / Remove moderator role / Make moderator.
- **Expect**: redirect back to the list carrying `bulk_done=N&bulk_total=M`, the row updated, notice "N moderators updated." (or the warning "X of Y updated. The rest were skipped: ..."), zero warnings. See `admin-lists-paginate-and-count-truthfully.md` for the guards.

### 4. Needs
- **Requires** the `reverse_listings` toggle ON (on here). Needs > Approve / Reject / Close a pending request.
- **Expect**: redirect back to the list with `moderated=<action>`, notice "Request approved. Matching vendors have been told." / "Request rejected. The member has been told." / "Request closed. Vendors who quoted have been told.", zero warnings. A request already handled elsewhere gives the info notice "That request was already handled...".

### 5. POSITIVE CONTROL for the observation itself
- On a Reign (or any theme that prints on `admin_head`) install the unfixed pattern produces the warning; with the fix the same install is clean. On BuddyX Pro a clean log proves nothing about the fix, so assert the redirect location in each step above rather than relying on the log.

### 6. Role ladder
- **Administrator (control)**: steps 1-4.
- **Editor / subscriber**: the pages answer 403 (caps `manage_listora_settings`, `manage_listora_moderators`, ...); a forged POST to the same URL does not write (nonce + capability checks in each `handle_admin_actions()`).
- **Logged out**: redirect to wp-login.php.

## Cleanup

Delete the probe badge and webhook. Restore both feature toggles to their prior state: `reverse_listings` and `outgoing_webhooks` are OFF by default in a fresh install; a page that 403s or hides because its feature is off is correct behaviour, not a failure of this journey.

## Fail diagnostics
| Symptom | File |
|---|---|
| Redirect fails after save | handler moved into the render callback: the four `add_action( "load-{$this->..._hook}" )` lines above |
| Save succeeds, no notice | `class-badges.php:1555-1566`, `class-outgoing-webhooks.php:2447-2455` |
| Detector regression | `wb-listora/bin/audit-guardrails.sh:139-165` (G6) |

Verified via: curl as administrator (badge create + delete with real nonce, moderator bulk POSTs, page loads for webhooks/needs), the detector loop, source for the webhook and needs paths. The webhook save and the needs actions were not executed.
