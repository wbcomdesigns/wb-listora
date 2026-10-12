---
journey: featured-block-empty-state
plugin: wb-listora
priority: normal
roles: [anonymous, editor, admin]
covers: ["#9977213192", "featured block silent disappearance", "canonical empty-state vocabulary"]
prerequisites:
  - "A listing type with listings but NO featured ones: business (8 listings, 0 featured on this site), or event (0 listings)"
estimated_runtime_minutes: 2
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Featured Listings block renders an empty state instead of vanishing

When the featured query returns no ids, `blocks/listing-featured/render.php:66-81` (`wb_listora_render_empty_state()` call at :70) renders the shared empty state through `wb_listora_render_empty_state()` instead of returning nothing.

2.0.0 correction: the block no longer back-fills with top-rated listings (`wb_listora_featured_backfill` defaults false, `render.php:52`). So the empty state shows whenever the chosen type has no FEATURED published listing, not only when it has no listings at all.

Verified via: curl of temporary pages (QA-J1, deleted): business -> empty, event -> empty, hotel -> populated, 2026-10-12.

## Steps

### 1. No featured listings shows the empty state
- **Action**: page with `<!-- wp:listora/listing-featured {"listingType":"business"} /-->`, view logged out.
- **Expect**: `div.listora-featured--empty.listora-card--empty[role="status"]` with title "No featured listings yet" and description "Listings marked as featured will appear here." Same for `{"listingType":"event"}`.

### 2. POSITIVE CONTROL: populated type unaffected
- **Action**: same page with `{"listingType":"hotel"}` (5 featured).
- **Expect**: normal block with `--listora-featured-columns: 4`, "See all" link `.listora-featured__see-all`, no empty state.

### 3. Editor sees the same
- **Action**: as qa_editor insert the block in the editor preview.
- **Expect**: same empty card (server render), not a gap. Not run.

### 4. Role ladder
- Public output: anonymous, member, owner, editor, admin identical. Admin is the control.

### 5. 390px
- **Expect**: card centred, no horizontal overflow. Not run.

## Fail diagnostics

| Symptom | File |
|---|---|
| Block vanishes | `blocks/listing-featured/render.php:70` empty-state call missing (bare `return`) |
| Old fill behaviour expected | `blocks/listing-featured/render.php:52` filter `wb_listora_featured_backfill` |
