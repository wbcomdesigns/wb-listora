---
journey: detail-header-no-duplicate-chips
plugin: wb-listora
priority: normal
roles: [anonymous, admin, member-owner]
covers: [BC-10194590939, BC-10194590988, badges, address, free-pro-seam]
prerequisites:
  - "Combo (Pro active), Badges and Verification features ON"
  - "Verified listing: id 45 /listing/le-petit-bistro/ (address '876 Madison Avenue, Upper East Side, NY 10021')"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The listing header says each thing once

Verified via: curl + DOM parse of `/listing/le-petit-bistro/` and a feature flip, 2026-10-12.

Header markup: `.listora-detail__header-top` holds the type chip, the Free "Verified" chip (`.listora-badge--open`, `blocks/listing-detail/render.php:463`), Open now / Closed, Pro's pills via `wb_listora_listing_title_badges`, then the rating. The address is `address.listora-detail__address` built by `wb_listora_format_address_line()`.

## Steps

### 1. Verified renders once (POSITIVE CONTROL for step 3)
- **Action**: count elements whose text is exactly "Verified" inside `.listora-detail__header-top` on listing 45.
- **Expect**: 1.

### 2. Pro's other pills survive
- **Action**: open a listing with Featured / Top Rated badges (`wp-admin` > Badges, or any featured listing: restaurant and hotel types have 5 featured each).
- **Expect**: those pills still render in `.listora-detail__header-top` (Free draws neither on the detail header). Fails if they vanish. Not exercised this pass: listing 45 had no extra pill.

### 3. Toggling verification off removes it from both
- **Action**: set `verification` false in `wb_listora_pro_features`, reload listing 45, restore.
- **Expect**: 0 "Verified" strings in the header (verified live: 0). Free's chip is gated on `wb_listora_is_verified()`.

### 4. Address renders once
- **Action**: listing 551 `/listing/fitlab-performance-gym/` (address "455 West 23rd St, Chelsea, NY 10011", city Chelsea, state NY).
- **Expect**: `address.listora-detail__address` text is exactly "455 West 23rd St, Chelsea, NY 10011", no trailing ", Chelsea, NY".

### 5. A bare street gains its parts
- **Action**: fixture with `_listora_address` = {address:"247 West Broadway", city:"Manhattan", state:"NY", postal_code:"10013"}.
- **Expect**: "247 West Broadway, Manhattan, NY 10013". Live example: listing 466 renders "1 World Trade Center, NY 10007, New York" (city not in street, appended).

### 6. Word-boundary matching
- **Action**: address "12 Nyack Road", state "NY".
- **Expect**: "NY" is not suppressed as present ("12 Nyack Road, NY"). Source: `includes/class-template-helpers.php:2173` uses `\b...\b`.

### 7. Role ladder
- The header is public: anonymous, member-owner, another member, editor and admin all see the same chips. Admin is the control; there is no owner-only variant.

### 8. 390px
- **Expect**: chips wrap, no horizontal page scroll. Not run.

## Fail diagnostics

| Symptom | File |
|---|---|
| Two "Verified" | `wb-listora-pro/includes/features/class-badges.php:869-909` `render_detail_badges()` (`wb_listora_pro_detail_native_badge_slugs`) |
| Address repeats city/state | `includes/class-template-helpers.php:2139` `wb_listora_format_address_parts()` |
| Header chip markup | `blocks/listing-detail/render.php:457-512` |
