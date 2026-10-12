---
journey: moderation-and-settings-screen-capabilities
plugin: wb-listora
priority: critical
roles: [editor, subscriber, anonymous, administrator]
covers: [reviews-page-cap, claims-page-cap, settings-page-cap-regression, listing-types-cap, role-defaults, card-9910737458]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "An {editor}-role user, a subscriber ({other}) and an {admin}"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
site_profile: required
supersedes: ["ora:regression/admin-caps-reviews-claims.md"]
---

# Reviews and Claims screens are gated on the moderation caps; Settings and Listing Types stay admin-only

## Why
Reviews and Claims were once gated on `manage_listora_types`, so roles holding the purpose-built `moderate_listora_reviews` / `manage_listora_claims` caps (editor) got a 403; widening the other way would hand editors Settings.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Switch users by logging out between roles.
- Cap map (current source):

| Screen / action | Cap | Where |
|---|---|---|
| Reviews page + handlers | `moderate_listora_reviews` | `includes/admin/class-admin.php:429` (menu), `:1562` (handler gate) |
| Claims page + handlers | `manage_listora_claims` | `class-admin.php:439`, `:1996` |
| Settings page | `manage_listora_settings` | `class-admin.php:449` |
| Listing Types, Categories, Locations, Features | `manage_listora_types` | `class-admin.php:381-420` |
| Role defaults | editor and administrator get the two moderation caps | `includes/core/class-capabilities.php:143-159` |

- Pro's `listora_moderator` role gets the moderation caps through the same registration, so the same gate applies. A future cap layout change must update this table, the menu declarations at `class-admin.php:381-470` and the two handler gates.

## Steps

### 1. Editor holds the right caps
- **Action**: `wp eval 'foreach(["editor","administrator"] as $r){$o=get_role($r); echo $r,": ",(int)$o->has_cap("moderate_listora_reviews"),(int)$o->has_cap("manage_listora_claims"),(int)$o->has_cap("manage_listora_types"),(int)$o->has_cap("manage_listora_settings"),"\n";}'`.
- **Expect**: `editor: 1100`, `administrator: 1111`.
- **Read-back**: the printed digits.

### 2. Reviews page renders for the editor
- **Action**: log in as {editor} (`?autologin={editor}`), open `$SITE_URL/wp-admin/admin.php?page=listora-reviews`.
- **Expect**: HTTP 200, `<title>` "Reviews < Listora", `h1.listora-page-header__title` containing "Reviews", `.listora-hub-tabs` with Reviews active, no "Sorry, you are not allowed".

### 3. Claims page renders for the editor
- **Action**: same with `page=listora-claims`.
- **Expect**: 200, title "Claims < Listora", h1 "Claims".

### 4. Settings and Listing Types are BLOCKED for the editor (guard)
- **Action**: `page=listora-settings` and `page=listora-listing-types` as {editor}.
- **Expect**: HTTP 403 "Sorry, you are not allowed to access this page." on both; no Leads tab in the Listings hub (`manage_listora_settings`).
- **Read-back**: if open, the editor was granted `manage_listora_settings`/`manage_listora_types`: check `class-capabilities.php` defaults.

### 5. Handlers are gated by the cap, not just the page
- **Action**: `wp eval 'wp_set_current_user(<EDITOR_ID>); echo (int)current_user_can("moderate_listora_reviews"), (int)current_user_can("manage_listora_claims");'` then with the subscriber's ID; then as {editor} approve one QA-J1 pending review from the screen; as the subscriber `PUT /listora/v1/reviews/{id}` with `status`.
- **Expect**: `11` then `00`; the editor's approve works; the subscriber's write is refused.
- **Read-back**: review status in DB; the approve flow itself: `admin/02-moderate-review.md`.

## Role ladder
| Role | Reviews | Claims | Settings | Listing Types |
|---|---|---|---|---|
| {admin} (control) | 200 | 200 | 200 | 200 |
| {editor} | 200 | 200 | 403 | 403 |
| subscriber | 403 | 403 | 403 | 403 |
| {guest} | 302 to wp-login.php | 302 | 302 | 302 |

## Pass criteria
1. Editor opens Reviews and Claims and can act; Settings and Listing Types return 403.
2. Handlers check the cap, not only the menu.
3. Subscriber and logged-out are refused everywhere.

## Fail diagnostics
| Symptom | File |
|---|---|
| Editor 403 on Reviews | `includes/admin/class-admin.php:429` must be `moderate_listora_reviews` |
| Editor 403 on Claims | `class-admin.php:439` must be `manage_listora_claims` |
| Editor can open Settings | `includes/core/class-capabilities.php:143-159` |

## Not verified
Pro `listora_moderator` role rows (covered with Pro's moderator journeys); 390 (wp-admin).

## Cleanup
Delete any QA-J1 user or review created for the run.
