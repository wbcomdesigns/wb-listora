---
journey: page-registry-heals-stale-mapping
plugin: wb-listora
priority: high
roles: [admin]
covers: []
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one registered Listora page (Dashboard, Submit, Browse Needs, ...)"
estimated_runtime_minutes: 5
---

# A deleted page does not leave dead links behind

`Page_Registry::get_id()` healed a **missing** mapping — no stored ID, so find a
page carrying the registered block and adopt it. It did not heal a **stale** one.
An owner who deleted or trashed the mapped page left the option holding a dead
ID, and that ID was accepted unchecked.

Nothing errored. `get_permalink()` returned `''`, and every caller degraded
quietly: canonical tags stopped rendering, CTAs fell back to whatever secondary
URL they knew, and no surface anywhere said why. The method's own docblock had
always claimed it healed a "missing/stale" mapping; only the missing half was
implemented.

## Steps

### 1. A deleted mapped page is re-adopted

- **Action**:
  ```bash
  wp eval '
    $a = wp_insert_post(array("post_type"=>"page","post_status"=>"publish",
      "post_title"=>"A","post_content"=>"<!-- wp:listora-pro/needs-grid /-->"));
    update_option("wb_listora_pro_browse_needs_page_id", $a);
    $b = wp_insert_post(array("post_type"=>"page","post_status"=>"publish",
      "post_title"=>"B","post_content"=>"<!-- wp:listora-pro/needs-grid /-->"));
    wp_delete_post($a, true);
    echo wb_listora_get_page_url("browse_needs"), "\n";
    echo get_option("wb_listora_pro_browse_needs_page_id"), " should be $b\n";'
  ```
- **Expect**: a non-empty URL — page B — and the option rewritten to B's ID, so
  the heal is persisted rather than recomputed on every request.
- **On fail**: an empty URL is the original defect. Check that `get_id()` tests
  `is_live_page()` on the stored ID *before* returning it, not only when it is 0.

### 2. Trashing counts as gone

- **Action**: repeat with `wp_trash_post()` in place of `wp_delete_post()`.
- **Expect**: same outcome. A trashed page is not a page a visitor can reach, and
  `is_live_page()` already treated it that way — the caller just never asked.

### 3. Nothing to adopt degrades honestly

- **Action**: delete every page carrying the block, then resolve.
- **Expect**: an empty string, and callers that guard on it. This is the correct
  answer when the site genuinely has no such page; the defect was returning it
  while a perfectly good page sat there unadopted.
