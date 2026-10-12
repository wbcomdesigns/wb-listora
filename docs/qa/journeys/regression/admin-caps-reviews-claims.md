---
journey: admin-caps-reviews-claims
plugin: wb-listora
priority: critical
roles: [editor, subscriber, anonymous, administrator]
covers: [reviews-page-cap, claims-page-cap, settings-page-cap-regression]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "An editor-role user (qa_editor on the reference site), a subscriber (subscriber2), an administrator (varundubey)"
estimated_runtime_minutes: 5
covers_card: 9910737458
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# Reviews and Claims admin pages are gated on the moderation caps, not the types cap

Reviews and Claims pages and their single/bulk handlers were once gated on `manage_listora_types`, so roles holding the purpose-built `moderate_listora_reviews` / `manage_listora_claims` caps (the editor role) got a 403. Reviews now sit behind `moderate_listora_reviews`, Claims behind `manage_listora_claims`; Settings, Listing Types and taxonomy screens stay admin-only.

## Cap map (current source)

| Screen / action | Cap | Where |
|---|---|---|
| Reviews page + its handlers | `moderate_listora_reviews` | `includes/admin/class-admin.php:429` (menu), `:1562` (handler gate) |
| Claims page + its handlers | `manage_listora_claims` | `class-admin.php:439` (menu), `:1996` (handler gate) |
| Settings page | `manage_listora_settings` | `class-admin.php:449` |
| Listing Types, Categories, Locations, Features | `manage_listora_types` | `class-admin.php:381-420` |
| Role defaults | editor and administrator get the two moderation caps | `includes/core/class-capabilities.php:143-159` |

Note: Settings used to be listed here under `manage_listora_types`; it is now its own cap, `manage_listora_settings`, and the editor does not hold it.

## Steps

### 1. Editor holds the right caps
- **Action**: `wp eval 'foreach(["editor","administrator"] as $r){$o=get_role($r); echo $r,": ",(int)$o->has_cap("moderate_listora_reviews"),(int)$o->has_cap("manage_listora_claims"),(int)$o->has_cap("manage_listora_types"),(int)$o->has_cap("manage_listora_settings"),"\n";}'`
- **Expect**: `editor: 1100` and `administrator: 1111`.

### 2. Reviews page renders for the editor
- **Action**: log in as qa_editor (`?autologin=qa_editor`), open `$SITE_URL/wp-admin/admin.php?page=listora-reviews`.
- **Expect**: HTTP 200, `<title>` "Reviews < Listora", `h1.listora-page-header__title` containing "Reviews", the hub tabs row `.listora-hub-tabs` with Reviews active, no "Sorry, you are not allowed".

### 3. Claims page renders for the editor
- **Action**: same with `page=listora-claims`.
- **Expect**: 200, title "Claims < Listora", h1 "Claims".

### 4. Settings and Listing Types are BLOCKED for the editor (guard)
- **Action**: `page=listora-settings` and `page=listora-listing-types` as the editor.
- **Expect**: HTTP 403 with "Sorry, you are not allowed to access this page." on both.
- **On fail**: the editor was granted `manage_listora_settings` / `manage_listora_types`: `class-capabilities.php` defaults.

### 5. Handlers are gated by the cap, not just the page
- **Action**: `wp eval 'wp_set_current_user(<EDITOR_ID>); echo (int)current_user_can("moderate_listora_reviews"), (int)current_user_can("manage_listora_claims");'` then the same with subscriber2's ID.
- **Expect**: `11` then `00`. Then as the editor approve one QA-J1- pending review from `page=listora-reviews` (see `admin-bulk-actions-submit.md`): it works. A review moderation REST write by the subscriber (`PUT /listora/v1/reviews/{id}` with `status`) is refused.

### 6. Role ladder (pages)
| Role | Reviews | Claims | Settings |
|---|---|---|---|
| administrator (control) | 200 | 200 | 200 |
| editor | 200 | 200 | 403 |
| subscriber | 403 | 403 | 403 |
| logged out | 302 to wp-login.php | 302 | 302 |

### Cleanup
Delete any QA-J1- user or review created for the run.

## Notes
- Pro's `listora_moderator` role gets the moderation caps through the same registration, so the same gate applies.
- A future cap layout change must update: this journey's table, the menu declarations at `class-admin.php:381-470`, and the two handler gates (`:1562`, `:1996`).

## Fail diagnostics
| Symptom | File |
|---|---|
| Editor 403 on Reviews | `includes/admin/class-admin.php:429` must be `moderate_listora_reviews` |
| Editor 403 on Claims | `class-admin.php:439` must be `manage_listora_claims` |
| Editor can open Settings | `includes/core/class-capabilities.php:143-159` |

Verified via: curl/DOM as qa_editor, subscriber2 and anonymous; caps via wp eval (step 1 and 5 results as above).
