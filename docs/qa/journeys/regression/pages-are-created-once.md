---
journey: pages-are-created-once
plugin: wb-listora
priority: critical
roles: [admin]
covers: []
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Pro active"
estimated_runtime_minutes: 12
---

# A Listora page is created once, and editing it does not spawn a second

Three copies of "ensure a page exists" grew up separately — Free's activator,
Pro's Compare page, Pro's Buy Credits page — and the two Pro copies decided
whether the mapped page *counted* by re-inspecting its content: `publish` status,
and for Buy Credits whether the block was still in it.

That is the wrong question to ask, and it turned an ordinary edit into a
duplicate page:

> Replace the Buy Credits block with a shortcode and your own copy — a thing
> site owners do — and the next call decided that was not its page. It created
> `buy-credits-2`, linked every Buy Credits CTA to the new empty one, and left
> the page you wrote orphaned.

Reproduced before the fix, and step 2 below is that exact reproduction.

A mapped page belongs to the owner whatever they put on it. Resolution now goes
through `Page_Registry::get_id()` — matched on the registered **block**, not a
title search capped at 25 results — and creation happens at most once per key
per site, recorded in a ledger.

## Steps

### 1. First call creates, second call does not

- **Action**:
  ```bash
  wp eval 'echo wb_listora_ensure_page("buy_credits"), " ", wb_listora_ensure_page("buy_credits");'
  ```
- **Expect**: the same ID twice.

### 2. Editing the page does NOT create a second one

The regression this file exists for.

- **Action**: replace the page's content with anything that is not the block —
  a shortcode, a page-builder layout, plain copy — then call `ensure` again.
- **Expect**: the **same ID**, and exactly one Buy Credits page on the site.
- **On fail**: a new ID, and a `buy-credits-2` page, is the original defect back.
  Look for a `has_block()` or `'publish' === $post->post_status` test guarding
  whether the stored mapping is trusted. Neither belongs there.

### 3. Unpublishing does not create a second one

- **Action**: set the page to draft, call `ensure` again.
- **Expect**: same ID. A draft is still the owner's page.

### 4. A deleted page is NOT resurrected

- **Action**: trash or delete the page, then call `ensure` again.
- **Expect**: **0**, and no new page. `status_for()` reports `missing`.
- **On fail**: silently re-creating a page someone deleted is the plugin
  overruling a deliberate act, on a schedule they cannot see. If they want it
  back there is a control for that — step 6.

### 5. Enabling a feature creates its pages

The card this shipped for: enabling Reverse Listings used to leave Post a Need
and Browse Needs unmapped, so the feature was on and both entry points led
nowhere — while Compare and Buy Credits created theirs, which made it read as a
bug rather than a step someone had to take.

- **Action**: with Reverse Listings OFF and neither page present, turn it ON.
- **Expect**: both pages exist and are mapped. Browse Needs lands on the `needs`
  slug (see `needs-one-slug-migration.md`).
- **Then**: toggle OFF and ON again. **No second pair.**
- **Note**: a site that already had the feature on before 1.7.0 never makes a
  transition, so a one-time back-fill covers it on `init`. Test that path by
  clearing `wb_listora_pro_feature_pages_backfilled` with the feature already on.

### 6. Create page recovers a deleted one

- **Action**: Listora > Settings > General > Pages. A deleted page's row reads
  **Missing** and offers **Create page**. Click it.
- **Expect**: a success notice with an Edit link, the row now **Linked**, the
  dropdown selecting the new page, and the Create button gone. At 390px the
  control is a 40px tap target and the page does not scroll sideways.
- **On fail**: without this control, "will not resurrect" is a dead end and the
  owner has to know to hand-build a page with the right block in it.

### 7. Buy Credits appears in the Pages table

- **Action**: look at the Pages table.
- **Expect**: rows for Directory, Add Listing, My Dashboard, Compare Listings,
  **Buy Credits**, Post a Need, Browse Needs.
- **On fail**: Buy Credits was absent before 1.7.0 because it had its own option
  and never registered. It is registered against that same option key, so
  nothing moves on upgrade — a site with a mapped credits page keeps it.

### 8. Buying Pro later connects to the Free site, and does not duplicate it

Most people run Free first and buy Pro later, so this transition is the normal
case, not an edge one. All of it is asserted here because each half fails
differently.

- **Action**: on a site that has run Free alone — with at least one Free page
  RENAMED by its owner, e.g. My Dashboard moved to `/my-listings/` — activate Pro.
- **Expect**: Pro's four pages appear (Compare, Buy Credits, Post a Need, Browse
  Needs), Browse Needs on the `needs` slug. **Free's pages are untouched**,
  including the renamed one, which still resolves.
- **On fail**: a second dashboard page, or a dashboard link reverting to
  `/my-dashboard/`, means something resolved by slug instead of through the
  registry.
- **Note**: Pro's page registration is attached during plugin load, which on the
  activation request happens after `init` has already fired — so its keys are
  not registered on that request. The back-fill on the next request is what
  creates them. Expect the pages on the following page load, not instantly.

### 9. Pro deactivating does not break Free

- **Action**: deactivate Pro.
- **Expect**: the site loads, Free's three pages still resolve, and Pro's four
  pages remain on disk as ordinary pages that still answer their URLs. Only the
  registry keys go away.
- **On fail**: a fatal or a broken Free page means Free took a dependency on a
  Pro-registered key.

### 10. Reactivating reconnects to the SAME pages

- **Action**: reactivate Pro. Watch for a fatal.
- **Expect**: all four keys resolve to **the same IDs as before**, and no
  `-2` duplicates anywhere.
- **On fail**: a fatal on activation is its own class of bug — the activation
  path `require_once`s files directly and the Pro autoloader has not run there,
  so any class referenced from `Pro_Migrator::create_tables()` must be required
  explicitly. This was a real fatal: activation died on `Needs_Slug` not found,
  and the message named a class rather than anything connected to activating a
  plugin.

### 11. Uninstalling and reinstalling Pro re-adopts the pages

The strongest form of the test: uninstall sweeps every `wb_listora_pro_*` option,
so all four mappings are gone. It deliberately leaves the pages, which are the
owner's content.

- **Action**: delete the four page-id options, keeping the pages.
- **Expect**: every key resolves to **the same page as before**, by block
  adoption, and no page is created.
- **On fail**: creating fresh pages here would leave the owner's customised ones
  orphaned while the plugin linked to empty replacements.

## Notes

- **`wb_listora_ensure_page()` is the extension-safe entry point.** Pro calls it
  rather than writing its own routine, which is how there came to be three.
- The ledger is `wb_listora_created_pages`, a key => page-id map. It records an
  event and is never recomputed from the current state of the site — clearing it
  makes `ensure` willing to create again, which is only useful in testing.
