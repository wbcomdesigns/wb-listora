---
journey: listing-header-chips-and-address
group: listing-detail
actor: customer
mode: mixed
plugin: wb-listora
priority: normal
roles: [guest, owner, other, editor, admin]
covers: [BC-10194590939, BC-10194590988, detail-header-badges, verified-chip-once, address-formatting, free-pro-seam]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Combo only for steps 1-3 (Pro active, Badges and Verification features ON); steps 4-8 run on Free alone"
  - "A verified listing $VERIFIED and a listing $STREET whose street line already contains its city/state (fixtures output lists ids)"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - "ora:regression/detail-header-no-duplicate-chips.md"
  - "ora:regression/listing-address-no-duplication.md"
site_profile: required
---

# The listing detail header says each thing once: one Verified chip, one complete address

## Why
Visitors see a doubled "Verified" pill or "Chelsea, NY" repeated in the address and read it as a broken site; a header that drops the postal code loses information. Free and Pro both draw into the same header so the seam must stay single.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Header markup: `.listora-detail__header-top` holds the type chip, the Free "Verified" chip (`.listora-badge--open`, `blocks/listing-detail/render.php:463`), Open now / Closed, Pro pills via `wb_listora_listing_title_badges`, then the rating. Address is `address.listora-detail__address`, built by `wb_listora_format_address_line()` (`includes/class-template-helpers.php:2118`) from `wb_listora_format_address_parts()` (`:2139`). Meta `_listora_address` is an array (address, city, state, postal_code, country, lat, lng).
- Address fixtures (QA-prefixed listings, deleted in Cleanup), edit via `wp post meta update <id> _listora_address '<json>'`:
  1. `{address:"247 West Broadway, Manhattan, NY 10013", city:"Manhattan", state:"NY", postal_code:"10013"}`
  2. `{address:"247 West Broadway", city:"Manhattan", state:"NY", postal_code:"10013"}`
  3. `{address:"", city:"Manhattan", state:"NY", postal_code:"10013"}`
  4. `{address:"", city:"", state:"", postal_code:""}`
  5. `{address:"1 Plaza, NY 10007", city:"New York", state:"NY", postal_code:"10007"}`
  6. `{address:"12 Nyack Road", city:"", state:"NY", postal_code:""}`
  7. markup/quotes/emoji in every part: `{address:"<b>1</b> \"Q\" 'x' 😀", city:"<i>c</i>", ...}`

## Steps

### 1. "Verified" renders once (positive control for step 3) - combo
- **Action**: on `$VERIFIED` count elements whose text is exactly "Verified" inside `.listora-detail__header-top`.
- **Expect**: 1.

### 2. Pro's other pills survive - combo
- **Action**: open a listing with Featured / Top Rated badges (wp-admin > Badges, or any featured listing).
- **Expect**: those pills still render in `.listora-detail__header-top` (Free draws neither on the detail header); they must not vanish.

### 3. Toggling verification off removes it from both - combo
- **Action**: set `verification` false in `wb_listora_pro_features`, reload `$VERIFIED`, restore.
- **Expect**: 0 "Verified" strings in the header (Free's chip is gated on `wb_listora_is_verified()`).

### 4. A formatted street line shows each part once
- **Action**: fixture 1 (and live listing `$STREET`).
- **Expect**: `address.listora-detail__address` text is exactly `247 West Broadway, Manhattan, NY 10013`; no trailing ", Manhattan, NY".

### 5. A bare street gains its parts
- **Action**: fixture 2.
- **Expect**: `247 West Broadway, Manhattan, NY 10013`. **Fail if** the postal code is missing.

### 6. No street, empty address
- **Action**: fixture 3, then fixture 4.
- **Expect**: fixture 3 renders `Manhattan, NY 10013`; fixture 4 renders no `<address>` element (the template guards `if ( $location )`) and no stray commas.

### 7. Containment is whole-word, postal not repeated
- **Action**: fixture 5, then fixture 6.
- **Expect**: fixture 5 renders `1 Plaza, NY 10007, New York` (city appended because it is not in the street; postal not repeated); fixture 6 renders `12 Nyack Road, NY` ("NY" is NOT suppressed by the substring in "Nyack"; match uses `\b...\b`, `includes/class-template-helpers.php:2173`).

### 8. Adversarial input
- **Action**: fixture 7.
- **Expect**: tags/quotes/emoji escaped as text, no markup injected, header layout intact. Not verified live.

### 9. Role ladder, sibling surfaces, layout
- Public output: guest, `{owner}`, `{other}`, `{editor}` and `{admin}` see the same chips and address; admin is the control for editing the meta. The same address string appears on the card and in the schema output; compare with `GET /listora/v1/listings/<id>`.
- **390px**: chips wrap, address wraps, no horizontal page scroll. Not verified live.

## Role ladder
See step 9; no owner-only variant exists.

## Pass criteria
1. Exactly one "Verified" chip; Pro's other pills survive.
2. The address reads each of street, city, state and postal once, in order, with no stray commas.
3. Identical for every role; no horizontal scroll at 390.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Two "Verified" | Pro draws a second chip | `wb-listora-pro/includes/features/class-badges.php:869-909` `render_detail_badges()` (`wb_listora_pro_detail_native_badge_slugs`) |
| Address repeats city/state | containment regex | `includes/class-template-helpers.php:2139` |
| Postal missing | postal block | `includes/class-template-helpers.php:2171-2182` |
| Stray commas | `array_filter` | `wb_listora_format_address_line()` `:2118` |
| Header chip markup | | `blocks/listing-detail/render.php:457-512` |

## Not verified
Steps 2, 8 and 9 (390px) were not run; steps 4-7 were checked against live metadata via `wb_listora_format_address_line()`, fixtures 2-7 are synthetic and were not created.

## Cleanup
Delete the QA fixtures and restore `wb_listora_pro_features`.
