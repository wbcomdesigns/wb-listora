# Plugin ↔ App feature parity

**Living document.** Update the Status column as each capability lands in the app, in the same PR
that ships it. The HTML visualisation next to this file (`app-parity.html`) mirrors these tables —
refresh it when the numbers move.

| | |
|---|---|
| **Plugins** | Free + Pro 1.5.0 |
| **App** | `~/apps/listora-app` 1.0.0 |
| **Last verified** | 2026-08-08 |
| **Live routes** | 117 (`GET /wp-json/listora/v1`) |
| **Called by the app** | 36 (was 20 at the first audit) |
| **Free member-facing gaps** | **0 of 9 — all closed** |

## Why this file lives here

The capability catalogue is **plugin-owned** (`CAPABILITIES.md` in both repos, per rule 7 of the
`wbcom-mobile-app` skill). The app never re-enumerates features — it maps coverage against this
spine. Keeping the parity view beside the catalogue is what stops the two lists drifting.

The companion release gate lives in the app repo at `docs/FEATURE-COVERAGE.md` and blocks release on
any remaining ❌ row.

## Method

| Source | Used for |
|---|---|
| `wb-listora/CAPABILITIES.md` (113 rows) + `wb-listora-pro/CAPABILITIES.md` (167 caps) | The capability spine |
| Live `GET /wp-json/listora/v1` | Ground truth for routes. The plugin's own catalogue warns the manifest is unreliable here, so the index is probed directly. |
| App `api/`, `hooks/`, `app/` | Which endpoints the app actually calls, plus each tab screen read by hand |

**Confidence:** endpoint- and screen-level. This establishes what is *reachable*, not what is
bug-free. A grep tells you what exists, not what works.

**Status vocabulary:** ✅ Done · ⚠️ Partial · ⚠️ Deferred (reason + target required) ·
🚫 Web-only (by design) · ❌ Missing (must become Done or Deferred before release).

---

## Headline

The app is now a **participation** client, not just a discovery one. All nine Free member-facing
gaps identified in the first audit are closed: a member can submit, edit and renew a listing, claim
a business, reply to reviews, manage their profile and notifications, and delete their account.

Every one was app-side — all nine already had working plugin endpoints, so no plugin change was
needed to close any of them.

**Remaining work is Pro surfaces (#8) and the coverage release gate (#9).**

---

## Free — member-facing gaps

| Capability | Route | Journey | Status | Task |
|---|---|---|---|---|
| Submit a listing | `POST /submit`, `/submit/check-duplicate` | 02, 07 | ✅ **Done** — app `9d37cc9` | #2 |
| Deactivate / delete account | `DELETE /me`, `POST /me/deactivate`, `/me/reactivate` | 11, 12-delete | ✅ **Done** — app `9d37cc9` | #1 |
| Edit own listing | `PUT /submit/{id}` | 17 | ✅ **Done** — app `684a81c` | #3 |
| Claim a business | `POST /claims`, `GET /dashboard/claims` | 05, 13 | ✅ **Done** — app `c8dbcce` | #5 |
| Notifications | `GET /dashboard/notifications`, `POST …/read` | 15 | ✅ **Done** — app `b1c2f2f` | #4 |
| Reply to a review as owner | `POST /reviews/{id}/reply` | 03 | ✅ **Done** — app `a70b775` | #6 |
| My reviews | `GET /dashboard/reviews` | — | ✅ **Done** — app `a70b775` | #6 |
| Edit profile | `GET/POST /dashboard/profile` | 12 | ✅ **Done** — app `a70b775` | #6 |
| Renew a listing | `GET /listings/{id}/renewal-quote`, `POST …/renew` | 06 | ✅ **Done** — app `0a840c8` | #7 |

**Submission was the structural one** — without it the app could never grow the directory it
displays. It now creates listings end to end (verified: id 968, `pending`, correct type, category
and address). Photos, opening hours and social links are deliberately post-submit enrichment, not
preconditions; see the app's `docs/UX-SUBMISSION.md`.

**Known limitation:** the GPS path on the address field is not yet exercised on a device — headless
Chrome cannot supply a location, so only the typed fallback is verified.

---

## Pro — member-facing gaps

Each gates on its own `/app/config` feature flag; a module never assumes Pro is active.

| Capability | Route | Note | Status | Task |
|---|---|---|---|---|
| Compare listings | `/compare` | Multi-select + comparison screen; a feature wave | ⚠️ Deferred | #8 |
| Verification badges | embedded in `/listings/{id}/detail` | Already shipped — `BadgeRow`, gated on `features.badges`. This row was wrong: `/listings/{id}/badges` is POST/DELETE only (admin assignment). | ✅ **Done** | #8 |
| Owner analytics | `/analytics/listing/{id}` | Owner-only; 403 otherwise | ✅ **Done** — app `aa43bf4` | #8 |
| Credit balance & plans | `/credits`, `/credit-packs` | Read-only; no purchase flow (Apple 3.1.1) | ✅ **Done** — app `aa43bf4` | #8 |
| Lead form | `/listings/{id}/contact` | **Not broken** — Free's `contact-form` still returns 200 and delivers while `lead_form` is ON (verified). The gap is lead *attribution*, which would undercount the `leads` metric. Needs a plugin-side answer on which route the app should post to. | ⚠️ Deferred | #8 |

Pro's catalogue marks **69 capabilities "dark"** — that is the *toggle state of a given site*, not
absent product. Saved searches and the Needs marketplace are switched off on the QA site, so there is
nothing for the app to surface.

---

## Partial — present but incomplete

| Surface | In the app | Still web-only | Status |
|---|---|---|---|
| My Listings | Read, stats, deactivate, reactivate, create, edit, **renew** | — | ✅ Done |
| Reviews | Write, mark helpful, report, **owner reply, my-reviews** | — | ✅ Done |
| Contact owner | Free contact form | Pro lead form when its toggle is on | ⚠️ Partial |

---

## Working today

| Capability | Route | Status |
|---|---|---|
| Browse, search, autocomplete | `/search`, `/search/suggest` | ✅ Done |
| Map with tiles and markers | `/settings/maps` | ✅ Done |
| Listing detail, related, services | `/listings/{id}/detail`, `/related`, `/services` | ✅ Done |
| Favourites | `/favorites` | ✅ Done |
| Write and rate a review | `POST /listings/{id}/reviews` | ✅ Done |
| Mark a review helpful | `POST /reviews/{id}/helpful` | ✅ Done |
| Report a listing or review | `/listings/{id}/report`, `/reviews/{id}/report` | ✅ Done |
| Contact a listing owner | `POST /listings/{id}/contact-form` | ✅ Done |
| Sign in with Application Passwords | `/auth/app-password` | ✅ Done |
| Bootstrap config | `/settings/app-config` | ✅ Done |

---

## Correctly out of scope

| Area | Why it stays on the web | Status |
|---|---|---|
| Admin and moderation | 85 capabilities (56 Pro, 29 Free). Site owners administer on the web by design. | 🚫 Web-only |
| Buying credits | Apple in-app-purchase rules make this display-only on mobile. | 🚫 Web-only |
| Imports and competitor migration | Bulk operations belong to the site owner, not the member. | 🚫 Web-only |
| Settings of every kind | The app configures nothing — every setting lives on the website and the app reflects it. | 🚫 Web-only |

---

## Open risk — faithfulness, not absence

This matrix catches **absence**. It does not catch **divergence**: a screen that exists, works, and
shows something the site does not actually say still scores ✅.

**Audited 2026-08-07 — one real bug found and fixed (app `e0b52c4`).**

`api/dashboard.ts` carried `expired` in its status filter. This plugin registers `listora_expired`;
`expired` is not a status it has. The `status` arg has no enum, so the server **ignored** the
unknown value and returned everything — tapping "Expired" showed the member all their listings,
published ones included, with no hint the filter had done nothing. Verified live: `status=expired`
returned 4 rows where the unfiltered list had 3. The same map rendered `listora_payment` as
"Payment" where `Status_Manager` says **"Awaiting Credits"**.

Post statuses are code-registered, not admin-editable, so this is not quite the Career Board
pipeline-stage shape — but the outcome was identical: a screen that existed, worked, and disagreed
with the site.

Clean on re-check: report reasons round-trip against the server enum, review tabs render server
totals rather than `rows.length`, and renewal renders the server's quote rather than recomputing
`can_renew_now`.

**Structural follow-up — BC 10182473304:** statuses and labels are not published in
`/settings/app-config`, so every client must carry a copy that can drift again. Publishing them from
the same `Status_Manager::custom_statuses()` map that already drives `register_post_status()` is the
only fix that prevents recurrence.

The app's release gate now lives at `listora-app/docs/FEATURE-COVERAGE.md` and blocks on any
remaining ❌ row.
