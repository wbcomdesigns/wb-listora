---
journey: blocked-members-styled
plugin: wb-listora
priority: normal
roles: [subscriber, administrator]
covers: [user-dashboard, member-blocks, dashboard-profile-tab]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who has blocked at least two other members, one with a very long display name (create QA-J1-blocker, QA-J1-blocked1, QA-J1-blocked2 and call \\WBListora\\Core\\Member_Blocks::block( blocker, target ) twice; delete them afterwards)"
estimated_runtime_minutes: 6
covers_card: 10322692957
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Blocked Members looks like the rest of the Profile tab

Sentinel for the Blocked Members section styling (`templates/blocks/user-dashboard/tab-profile.php:195-275`, CSS `blocks/user-dashboard/style.css:2335-2415`).

## Background

The section's markup and unblock handler shipped in 1.7.0 with no CSS at all: bare `h3`/`p` at browser defaults, with the theme's list bullets on the populated branch. The same commit that adds markup can ship without the stylesheet and nothing fails, hence this sentinel.

Current structure (verified in the served HTML 2026-10-12 as a member with two blocks): `div.listora-dashboard__section.listora-dashboard__blocked` > `h3.listora-dashboard__section-title` "Blocked Members", `p.listora-dashboard__section-desc` "You will not see their reviews, and they cannot contact you. Blocking works in both directions.", the shared empty-state card `div.listora-card--empty[data-listora-blocked-empty]` (title "You have not blocked anyone.", present but `hidden` while there are blocks), then `ul.listora-dashboard__blocked-list` > `li.listora-dashboard__blocked-row` > `img.listora-dashboard__blocked-avatar` + `span.listora-dashboard__blocked-name` + `button.listora-dashboard__unblock-btn` (`aria-label="Unblock <name>"`, text "Unblock"). The section sits OUTSIDE the profile `<form>`.

## Steps

### 1. The section matches its siblings
- **Action**: as the blocker, open My Listings > Profile (`/my-listings/?tab=profile`) and scroll to Blocked Members.
- **Expect**: card chrome like the sub-sections above it (`.listora-dashboard__profile-section`): same background, border and radius. Compare computed `border-radius` and `border` of `.listora-dashboard__blocked` against `.listora-dashboard__profile-section`; padding is `var(--listora-space-5)`, shadow `--listora-shadow-sm`.

### 2. The description reads as supporting text
- **Expect**: `.listora-dashboard__section-desc` is `--listora-text-size-sm` and `--listora-fg-muted`, not body-sized.

### 3. The populated list has no bullets
- **Expect**: `ul.listora-dashboard__blocked-list` has `list-style-type: none`, zero margin and padding, each row `display:flex` with a 32px circular avatar (the HTML carries `width="24"`; CSS sets 32px), the name and "Unblock" on one line at desktop, a hairline between rows.

### 4. A long display name does not push the button off
- **Action**: with the long-named blocked member.
- **Expect**: the name wraps (`overflow-wrap: anywhere`), "Unblock" stays visible and fully clickable, min-height 40px, no horizontal page scroll.

### 5. Unblocking restyles correctly without a reload
- **Action**: Unblock each member in turn.
- **Expect**: rows disappear (REST `DELETE /listora/v1/me/blocks/{id}` through the IAPI store, `src/interactivity/store.js:2095`); when the last goes, `[data-listora-blocked-empty]` loses `hidden` and shows "You have not blocked anyone." with the same shared empty-state styling as the server-rendered one. This is the step the card called out: the JS reveal depends on the same CSS, so a fix that only styled the server render leaves this branch bare. POSITIVE CONTROL: the empty card is present in the DOM from first render, so it can be revealed.

### 6. 390px
- **Expect**: at <= 640px the row wraps, the Unblock button drops to its own full-width row beneath the name (`flex: 1 0 100%`), >= 40px tall; avatar stays beside the name; no horizontal scroll.

### 7. RTL twin exists
- **Verify**: `blocks/user-dashboard/style-rtl.css` and `style.layer.css` both carry the `__blocked` rules (7 matches each, 2026-10-12). They are generated; never hand-edit, run the build.

### 8. Role ladder
- **The blocker (owner of the list)**: steps 1-7.
- **The blocked member**: the list is per blocker, so the person who was blocked does not see the blocker in their own Blocked Members section (the contact block itself is symmetric; see `blocking-enforced-on-live-contact-route.md`).
- **Another member / logged out**: the Profile tab shows their own list only; logged out gets the dashboard login prompt.
- **Administrator (control)**: sees their own list.

## Automated coverage
None. Presentation: a computed-style assertion in PHPUnit would test the harness. Walk it in a browser.

## Fail diagnostics
| Symptom | File |
|---|---|
| Bare list | `blocks/user-dashboard/style.css:2357-2415` lost, or the build not run (`style.layer.css` stale) |
| Empty state not revealed | `src/interactivity/store.js:2095-2135` |
| Section inside the form | `templates/blocks/user-dashboard/tab-profile.php:213` |

Verified via: curl/DOM as a member with two blocks (markup, classes, copy) plus CSS in source; computed styles, the unblock flow and 390px were not run in a browser.
