---
journey: page-registry-heals-stale-mapping
plugin: wb-listora
priority: high
roles: [admin]
covers: [page-registry, stale-mapping, needs]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI against this site's path"
  - "Pro active with the needs-grid page registered (key browse_needs, option wb_listora_pro_browse_needs_page_id)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
changed: true
---

# A deleted page does not leave dead links behind

`Page_Registry::get_id()` (`includes/core/class-page-registry.php:101`) heals a MISSING mapping (adopt a page carrying the registered block) and, since the fix, a STALE one: a stored ID that is deleted or trashed is checked with `is_live_page()` (`:490`, used at `:114`) and re-resolved, with the healed ID persisted to the option so it is not recomputed per request.

Verified via: `wp eval` with temporary QA-J1 pages and the original option restored (`790` before and after).

## Steps

### 1. A deleted mapped page is re-adopted
- **Action**: create page A containing `<!-- wp:listora-pro/needs-grid /-->`, `update_option( 'wb_listora_pro_browse_needs_page_id', A )`; `wb_listora_get_page_url( 'browse_needs' )` returns A's URL (control). `wp_delete_post( A, true )`. Resolve again.
- **Expect** (observed): a non-empty URL (the existing live Needs page `/needs/`) and `get_option( 'wb_listora_pro_browse_needs_page_id' )` rewritten to that page's ID (790), so the heal is persisted.
- **Fail**: an empty URL is the original defect: `get_id()` must test `is_live_page()` on the stored ID before returning it, not only when it is 0 (`class-page-registry.php:114`).

### 2. Trashing counts as gone
- **Action**: repeat with `wp_trash_post()` in place of `wp_delete_post()`.
- **Expect**: same outcome (observed: option stays/returns 790, URL `/needs/`).

### 3. Nothing to adopt degrades honestly
- **Action**: with no page carrying the block (temporarily drop the block from every candidate or use a registry key with no pages), resolve.
- **Expect**: empty string, and every caller guards on it (callers compose their own fallback). Not executed here: it requires unpublishing the real Needs page.

### 4. Role ladder
System/admin behaviour with a public consequence: logged-out visitors, members and moderators all get the healed URL in CTAs (spot-check `/my-listings/?tab=needs` "Post a Need" / "Browse Needs" hrefs as a member after step 1). Admin (control) deletes the page; no front-end role can.

## Cleanup
Delete QA-J1 pages A and B; restore the option (`790`).
