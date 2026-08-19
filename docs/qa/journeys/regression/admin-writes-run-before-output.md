---
journey: admin-writes-run-before-output
plugin: wb-listora-pro
roles: [admin]
priority: high
covers: [headers-already-sent, load-hook-handlers, badges-save, moderator-actions, webhook-save, needs-moderation, BC-10199668750, BC-9927893041]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora AND wb-listora-pro both active"
  - "A theme or plugin that echoes on an early admin hook (Reign does, via `<style id=\"reign-admin-menu-icon\">` on admin_head)"
  - "WP_DEBUG_LOG on, so the warning is observable"
estimated_runtime_minutes: 6
---

# Every Pro admin write runs before output, so its redirect survives

A save that redirects cannot run inside a render callback. By render time WordPress has already
started the admin body, so `wp_safe_redirect()` emits

```
PHP Warning: Cannot modify header information - headers already sent by
(output started at .../reign-theme/inc/init.php:1194) in .../pluggable.php on line 1535
```

and the `exit` after it ends the request where it stands. The record **is** written — only the
navigation fails, so the user sits on the form they just submitted with no feedback and often
saves again.

Coupons hit this first (`9927893041`) and was fixed by capturing the hook suffix from
`add_submenu_page()` and moving the write to `load-{$hook}`. Badges was then filed with the same
symptom (`10199668750`) — and a sweep found **four** pages in the same shape, not one:

| Page | What moved to `load-{$hook}` |
|---|---|
| Badges | save, delete, max-badges-per-card |
| Moderator | promote / demote / activate |
| Outgoing Webhooks | webhook save |
| Reverse Listings (Needs) | approve / reject |

> The card was scoped to "the Badges Save redirect only". Scoping a fix to the reported symptom is
> what left the other three shipping.

## The detector

This class is greppable, and **G7** in `bin/audit-guardrails.sh` now fails the build on it. Run it
by hand to confirm the sweep is still complete:

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

Any output is the regression.

## Steps

For **each** of the four pages, with `wp-content/debug.log` line count noted first:

### 1 — Badges

Badges → Add New → set a label → **Save Badge**.

- **Expect** the URL becomes `admin.php?page=listora-badges` (the list), **not** `&action=new`.
- **Expect** a "Badge saved." notice that is **computed-visible**:
  `getComputedStyle(el).display !== 'none'` — Listora's own admin notices were being hidden by a
  `:not(.listora-notice)` rule, so presence is not proof.
- **Expect zero** new `headers already sent` lines in debug.log.
- Repeat for **Delete** on that badge, and for the max-badges-per-card save.

### 2 — Outgoing Webhooks

Requires the `outgoing_webhooks` toggle ON. Webhooks → Add → name + URL → **Save**.

- **Expect** redirect to the list, the webhook present, zero warnings.
- The form posts a hidden `webhook_id` and the edit view also carries it in the query string; the
  handler must read the POST value first, or editing an existing webhook silently creates a second
  one.

### 3 — Moderators

Moderators → Promote / Demote / Activate a user.

- **Expect** redirect back to the list with the row updated, zero warnings.

### 4 — Needs

Requires the `reverse_listings` toggle ON. Needs → Approve or Reject a pending need.

- **Expect** redirect back to the list with the status changed, zero warnings.

## Cleanup

Delete the probe badge and webhook. Restore both feature toggles to whatever they were before —
`reverse_listings` and `outgoing_webhooks` are OFF by default, and a page that 403s because its
feature is off is correct behaviour, not a failure of this journey.
