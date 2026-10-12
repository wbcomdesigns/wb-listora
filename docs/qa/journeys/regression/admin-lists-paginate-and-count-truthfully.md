---
journey: admin-lists-paginate-and-count-truthfully
plugin: wb-listora-pro
roles: [administrator, editor, subscriber]
priority: high
covers: [moderators-pagination, moderators-search, moderators-bulk-actions, moderator-reassign-ui, badges-pagination, counter-truthfulness, BC-10199612602]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora AND wb-listora-pro both active"
  - "More moderators than one page holds: seed 25 users with role listora_moderator named QA-J1-modNN (20 per page) and at least 2 badges (the site has 6)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
changed: true
---

# Admin lists page, search, act in bulk, and their counters tell the truth

Pro's Moderators screen (`wb-listora-pro/includes/features/class-moderator.php`) and Badges screen (`class-badges.php`) once fetched every row with no `number`, had no search, had zero UI for `POST /moderators/reassign`, and (once paged) showed "0 Active Moderators" on a site with 60 because the stats were computed from the rendered page. They now render through the shared `Admin_Table` service. A counter that reports the page instead of the total is worse than no counter: it is confidently wrong.

2.0.0 shape (verified live 2026-10-12): one table (`form.listora-admin-table-form`) of moderators with views All / Active / Paused (`mod_view`), search `s`, `paged`, `per_page` (20, 50 or 100; default 20 through filter `wb_listora_pro_moderators_per_page`), four stat cards, and a separate "Add a moderator" card with a member search `elig_s` (up to 20 results, shown only after a search, so no member is unreachable behind a cap). The old two-table layout with `mod_paged` / `elig_paged` is gone.

## Steps

### 1. The table paginates
- **Action**: as administrator open `$SITE_URL/wp-admin/admin.php?page=listora-moderators` with 25 seeded moderators.
- **Expect**: h1 "Moderators"; 20 rows (`input[name="ids[]"]` count) and the footer `.listora-pagination-bar` with `.listora-pagination__count` "25 moderators"; `?paged=2` shows the remaining 5; `?per_page=50` shows all 25.
- **On fail**: `class-moderator.php:1047-1122` (`get_moderators_page()` slices before querying users).

### 2. Counts are the TOTAL, not the page
- **Expect**: stat card "Active moderators" reads 25 (the whole population) on page 1 and on page 2, never 20 or 5. Tab counts All/Active/Paused sum to the total. Badges: `.listora-pagination__count` reads "N badges" equal to the total number of badges (6 on the reference site), not the rendered rows.

### 3. Search narrows, and says so
- **Action**: `&s=QA-J1-mod07`, then `&s=QA-J1`, then `&s=zzzznope`.
- **Expect**: exact -> 1 row and "1 moderator"; partial matching all seeds -> "25 moderators", 20 rendered (still paged); no match -> 0 rows and `.listora-empty-state` titled "Nothing matches these filters", with the Clear link (`.listora-action-link`) in the filter bar. Search filters the QUERY (`get_users( search )`), never a client-side filter over one page.
- **Member search (POSITIVE CONTROL for "any member reachable")**: in "Add a moderator" search a subscriber's name (`elig_s=<name>`): a result row with a "Make moderator" action appears; an administrator or an existing moderator is NOT listed ("No member matches that. Administrators and existing moderators are not listed.").

### 4. Stats count everyone
- **Action**: pause two moderators from different pages, land on a page containing neither.
- **Expect**: "Paused" card reads 2 on every page.

### 5. Bulk works (verified 2026-10-12 by POST with the form nonce)
- Select-all toggles every row checkbox; unticking one clears select-all.
- Bulk "Pause" two -> redirect `...&bulk_done=2&bulk_total=2`, user meta `_listora_moderator_active` is `0` for both; notice "2 moderators updated." (`.notice-success.listora-notice`).
- Bulk "Activate" the same two -> `1` again.
- Bulk "Reassign their queue to..." with `moderator_reassign_to` set: a listing assigned to moderator A (`_listora_assigned_moderator`) moves to B and `wb_listora_pro_moderator_reassigned` fires, through the same `reassign_items()` that `POST /moderators/reassign` uses (`class-moderator.php:1612-1671`).

### 6. Bulk cannot bypass a per-row guard (the assertion that matters most)
- Bulk "Remove moderator role" on YOUR OWN account -> `bulk_done=0&bulk_total=1`, you are still an administrator. Verified.
- Bulk on yourself plus a real moderator -> `bulk_done=1&bulk_total=2`, the moderator became subscriber, you did not. The warning notice reads "1 of 2 updated. The rest were skipped: you cannot change your own role or an administrator's here, a queue needs an active moderator to go to, and only moderators can be activated." (`.notice-warning`).
- A bulk path that applies an action the single-row path refuses is a privilege bug.

### 7. Role ladder
| Role | Moderators / Badges screen |
|---|---|
| administrator (control) | full, steps 1-6 |
| listora_moderator | sees queue screens they hold caps for; Moderators screen needs `manage_listora_moderators` (administrator only by default), 403 otherwise |
| subscriber | 403 |
| logged out | redirect to wp-login.php |

### 8. No warnings
- **Action**: request every variant (both pages, page 2, a search, a bulk POST) and tail `wp-content/debug.log`.
- **Expect**: zero `Undefined variable` / `Fatal error` lines from `class-moderator.php` or `class-badges.php`.

### 9. Mobile
- **Expect** at 390: the table collapses its low-priority columns (`priority` 2 and 3 columns: "Done this month", "Role", "Since"), bulk bar wraps, no horizontal page scroll. Not measured in this pass.

## Cleanup
Restore the activation state of any moderator toggled; delete the QA-J1-mod users (`wp user delete <id> --yes`) and any QA-J1- listing used for the reassign check.

## Not verified
The listora_moderator role row of the ladder, the Paused-card-on-every-page check (step 4) and 390px were reasoned from source, not executed.

## Fail diagnostics
| Symptom | File |
|---|---|
| Wrong total on stat cards | `class-moderator.php:~840-900` `render_stats_grid()` must use `$page['counts']` / `all_ids` |
| Bulk applies a refused action | `class-moderator.php:1166-1262` `handle_bulk_actions()` -> `apply_moderator_action()` |
| Badges count is the page | `class-badges.php:1746-1805` (`found_posts` used for `total`) |

Verified via: curl as administrator against the live admin screens with 25 seeded QA-J1- moderators (deleted afterwards) and direct POSTs with the form nonce; DB/meta read back with wp-cli. Not click-tested; 390px not measured.
