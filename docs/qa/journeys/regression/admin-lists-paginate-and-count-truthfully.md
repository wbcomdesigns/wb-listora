---
journey: admin-lists-paginate-and-count-truthfully
plugin: wb-listora-pro
roles: [admin]
priority: high
covers: [moderators-pagination, moderators-search, moderators-bulk-actions, moderator-reassign-ui, badges-pagination, counter-truthfulness, BC-10199612602]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora AND wb-listora-pro both active"
  - "More moderators than one page holds (seed ~60) and at least 2 badges"
estimated_runtime_minutes: 8
---

# Admin lists page, search, act in bulk — and their counters tell the truth

Three gaps on one screen, plus a fourth that appeared while closing them.

**No paging.** Active Moderators fetched every moderator with no `number` and rendered them in one
table — 60 rows on the install this was reproduced on, against a card that recorded "not at scale
today (6 users)". Eligible Users was hard-capped at `number => 50` with no pager, so user 51 was
unpromotable from the UI and the copy told the admin to go change roles by hand instead.

**No search.** Neither table had one, on a screen whose whole job is finding a person.

**No bulk.** `POST /moderators/reassign` had been implemented and journey-verified since it shipped,
with **zero UI consumers**, while `CAPABILITIES.md:282` documented "Reassign moderation items …
Moderators page" as a surface that existed. It didn't.

**And the one paging created:** the stats grid was handed the rendered page instead of the full
population, so it read **"0 Active Moderators"** on a site with 60. Badges had the same shape —
"N badges configured" counting the page. A counter that reports the page instead of the total is
worse than no counter, because it is confidently wrong.

> Paginating a list without re-checking every count derived from it is how you trade one bug for
> another. Both were caught here only because the screen was looked at, not because the code
> compiled.

## Steps

### 1 — Both tables paginate

Open **Listora → Moderators**.

- **Expect** 20 rows per table and a `.listora-pagination` nav under each, **computed-visible**.
- `?mod_paged=2` returns a different 20. `?elig_paged=2` returns eligible users 21-40.
- No user is unreachable at any page — the 50-cap dead end is the regression.

### 2 — The header count is the TOTAL

- **Expect** `Active Moderators (60)` — the whole population, not `20`.
- Badges: **Expect** "N badges configured" to equal the total, not the rendered page.

### 3 — Search narrows, and says so

Search an exact login, then a partial, then nonsense.

- Exact → 1 row, header count `1`.
- Partial matching all seeds → header count `60`, 20 rendered (still paginated).
- No match → 0 rows and the "no moderators match that search" empty state with a Clear link.

Search must filter the **query**, not the rendered page — a client-side filter over one page is the
trap the Coupons toolbar comment already warns about.

### 4 — Stats count everyone

- **Expect** Active Moderators / Items in Queue / Processed This Month to be computed over **all**
  moderator IDs. Activate exactly two moderators anywhere in the list, land on a page containing
  neither, and the card must still read `2`.

### 5 — Bulk works

- Select-all toggles every row checkbox; unchecking one row clears select-all (otherwise the header
  box lies about the selection and someone applies an action to more rows than they meant to).
- Bulk **Deactivate** two → redirect carries `bulk_done=2&bulk_total=2`, meta is `0` for both.
- Bulk **Activate** the same two → back to `1`.
- Bulk **Reassign items to…** moves `_listora_assigned_moderator` and fires
  `wb_listora_pro_moderator_reassigned` — the same path `POST /moderators/reassign` uses, because
  both now call one `reassign_items()`.

### 6 — Bulk cannot bypass a per-row guard

This is the assertion that matters most.

- Bulk **Demote to subscriber** on **your own account** → **`bulk_done=0&bulk_total=1`**, and you
  are still an administrator.
- Same for demoting an administrator.

A bulk path that applies an action the single-row path refuses is a privilege bug, not a UI gap.

### 7 — No warnings

Request every variant — both pages, page 2 of each, a search, a bulk POST — and assert **zero**
`Undefined variable` or `Fatal error` lines in debug.log. Splitting a render into helper methods is
exactly when a paging variable falls out of scope and silently reads as `0`.

## Cleanup

Restore the activation state of any moderator toggled during the run.
