---
journey: page-registry-heals-stale-mapping
plugin: wb-listora
priority: high
roles: [admin, guest, owner]
covers: [page-registry, stale-mapping, needs]
prerequisites:
  - "WP-CLI against this site's $WP_PATH"
  - "Pro active with the needs-grid page registered (key browse_needs, option wb_listora_pro_browse_needs_page_id) for the worked example; the Free `directory` key is the Free-only equivalent (added, not verified live)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes:
  - ora:regression/page-registry-heals-stale-mapping.md
requires: [wb-listora-pro]
fails_on: unrecorded
site_profile: required
---

# A deleted or trashed mapped page does not leave dead links behind

## Why
`Page_Registry::get_id()` (`includes/core/class-page-registry.php:101`) heals a MISSING mapping (adopts a page carrying the registered block) and, since the fix, a STALE one: a stored ID that is deleted or trashed fails `is_live_page()` (`:490`, used at `:114`), is re-resolved, and the healed ID is persisted to the option so it is not recomputed per request.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Record `$NEEDS_PAGE_ID` = `get_option( 'wb_listora_pro_browse_needs_page_id' )` to restore.

## Steps

### 1. A deleted mapped page is re-adopted
- **Action**: create page A containing `<!-- wp:listora-pro/needs-grid /-->`, `update_option( 'wb_listora_pro_browse_needs_page_id', A )`; `wb_listora_get_page_url( 'browse_needs' )` returns A's URL (control). `wp_delete_post( A, true )`. Resolve again.
- **Expect**: a non-empty URL (the existing live Needs page) and `get_option( 'wb_listora_pro_browse_needs_page_id' )` rewritten to that page's ID, so the heal is persisted.
- **Fail**: an empty URL is the original defect: `get_id()` must test `is_live_page()` on the stored ID before returning it, not only when it is 0 (`class-page-registry.php:114`).

### 2. Trashing counts as gone
- **Action**: repeat with `wp_trash_post()`.
- **Expect**: same outcome.

### 3. Nothing to adopt degrades honestly
- **Action**: with no page carrying the block (temporarily drop the block from every candidate or use a registry key with no pages), resolve.
- **Expect**: empty string, and every caller guards on it (callers compose their own fallback). Not executed here: requires unpublishing the real page.

### 4. The public consequence
- **Action**: after step 1 as {owner} open `$DASH/?tab=needs`.
- **Expect**: the "Post a Need" / "Browse Needs" hrefs are the healed URL.

## Role ladder
System/admin behaviour with a public consequence: {guest}, members and moderators all get the healed URL in CTAs (step 4). {admin} (control) deletes the page; no front-end role can.

## Pass criteria
1. A stale stored ID (deleted or trashed) is re-resolved and persisted.
2. With nothing to adopt the result is `''` and callers cope.

## Fail diagnostics
| Symptom | File |
|---|---|
| Empty URL after delete | `includes/core/class-page-registry.php:101`, `:114`, `is_live_page()` `:490` |
| Healed ID not persisted | same method's option write |
| Dead CTA href | the caller must guard on `''` |

## Not verified
Step 3 and the Free-only `directory` variant; steps 1-2 were run with `wp eval` and temporary pages.

## Cleanup
Delete the temporary pages; restore the option to `$NEEDS_PAGE_ID`.
