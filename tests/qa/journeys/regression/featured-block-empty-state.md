---
journey: featured-block-empty-state
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: ["#9977213192", "featured block silent disappearance", "canonical empty-state vocabulary"]
prerequisites:
  - "A listing type with zero published listings (e.g. a fresh 'event' type)"
estimated_runtime_minutes: 1
---

# Featured Listings block renders an empty state instead of vanishing

Card #9977213192: when the query (featured + top-rated fallback) returned no
IDs, `blocks/listing-featured/render.php` bare-returned — zero HTML, the block
silently vanished for editors and visitors alike. Categories and Reviews
blocks already render the canonical `.listora-card--empty .listora-empty`
vocabulary; Featured now matches.

Note: because the block backfills with top-rated listings when `sort` is
`featured`, the empty path only triggers when the selected `listingType` has
NO published listings at all — not merely no featured ones.

## Steps

### 1. Empty type shows the empty state
- **Action**: create a page with
  `<!-- wp:listora/listing-featured {"listingType":"<empty-type>"} /-->`
  and visit it anonymously.
- **Expect**: a visible `.listora-featured--empty.listora-card--empty` card
  with `role="status"`, star icon, title "No featured listings yet", and
  description "Listings marked as featured will appear here." — NOT an empty
  gap.

### 2. Populated type unaffected
- **Action**: same page with a populated `listingType` (e.g. `hotel`).
- **Expect**: normal carousel renders; no empty state.

### 3. Mobile
- **Action**: step 1 at 390px viewport.
- **Expect**: empty state centered, no horizontal overflow.
