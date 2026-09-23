---
journey: blocked-members-styled
plugin: wb-listora
priority: normal
roles: [subscriber]
covers: [user-dashboard, member-blocks, dashboard-profile-tab]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who has blocked at least two other members"
estimated_runtime_minutes: 5
covers_card: 10322692957
---

# Blocked Members looks like the rest of the Profile tab

Regression sentinel for the Blocked Members section styling.

## Background

The section's markup and its unblock handler shipped in 1.7.0 with **no CSS at all**. It rendered as bare `h3`/`p` at browser defaults, with the theme's list bullets on the populated branch — visibly breaking out of the tab it sits in. `listora-dashboard__section-title` happened to be styled already; the container, the description and the whole `__blocked*` family were not.

This is a whole-feature gap rather than a tweak, which is why it is worth a sentinel: the same commit that adds markup can ship without the stylesheet and nothing fails.

## Steps

### 1. The section matches its siblings
- **Action**: as a member, open Dashboard → Profile and scroll to Blocked Members.
- **Expect**: the same card chrome as the Social Links / Email Notifications sub-sections directly above — same padding, background, border radius and shadow. Compare computed `padding` and `border-radius` against `.listora-dashboard__profile-section`; they should be identical, not merely similar.

### 2. The description reads as supporting text
- **Expect**: smaller and muted, not body-sized black.

### 3. The populated list has no bullets
- **Action**: with two or more blocked members.
- **Expect**: `list-style-type: none`, no left padding, and each row laid out as avatar (32px, circular) + name + Unblock on one line at desktop.

### 4. A long display name does not push the button off
- **Action**: block a member whose display name is very long.
- **Expect**: the name wraps; the Unblock button stays visible and fully clickable; no horizontal page scroll.

### 5. Unblocking restyles correctly without a reload
- **Action**: unblock each member in turn.
- **Expect**: rows disappear, and when the last one goes, the empty-state paragraph swapped in by the IAPI store carries the same muted styling as the server-rendered one. **This is the step the card specifically called out** — the JS `replaceWith` depends on the same CSS, so a fix that only styled the server render would leave this branch bare.

### 6. 390px
- **Expect**: the Unblock button drops to its own full-width row beneath the name, at least 40px tall; the avatar stays beside the name; no horizontal scroll.

### 7. RTL twin exists
- **Verify**: `blocks/user-dashboard/style-rtl.css` contains the `__blocked` rules. It is generated — do not hand-edit it; run the build.

## Automated coverage

None. This is presentation, and a computed-style assertion in PHPUnit would test the test harness rather than the page. Walk it in a browser.
