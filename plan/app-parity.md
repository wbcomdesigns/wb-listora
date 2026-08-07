# Plugin ↔ App feature parity

**Living document.** Update the Status column as each capability lands in the app, in the same PR
that ships it. The HTML visualisation next to this file (`app-parity.html`) mirrors these tables —
refresh it when the numbers move.

| | |
|---|---|
| **Plugins** | Free + Pro 1.4.2 |
| **App** | `~/apps/listora-app` 1.0.0 |
| **Last verified** | 2026-08-07 |
| **Live routes** | 117 (`GET /wp-json/listora/v1`) |
| **Called by the app** | 34 (was 20 — submission, account, edit, claims, profile, reviews and renewal added) |
| **Free member-facing gaps** | 1 of 9 remaining — notifications only |

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

The app is a capable **discovery** client. It is not yet a **participation** client — members can
find and read, but cannot contribute.

**Every gap below is app-side.** All nine Free capabilities already have working plugin endpoints,
verified live including `DELETE /me` — no plugin work is required to close them.

---

## Free — member-facing gaps

| Capability | Route | Journey | Status | Task |
|---|---|---|---|---|
| Submit a listing | `POST /submit`, `/submit/check-duplicate` | 02, 07 | ✅ **Done** — app `9d37cc9` | #2 |
| Deactivate / delete account | `DELETE /me`, `POST /me/deactivate`, `/me/reactivate` | 11, 12-delete | ✅ **Done** — app `9d37cc9` | #1 |
| Edit own listing | `PUT /submit/{id}` | 17 | ✅ **Done** — app `684a81c` | #3 |
| Claim a business | `POST /claims`, `GET /dashboard/claims` | 05, 13 | ✅ **Done** — app `c8dbcce` | #5 |
| Notifications | `GET /dashboard/notifications`, `POST …/read` | 15 | ❌ Missing | #4 |
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
| Compare listings | `/compare` | 6 catalogued capabilities | ❌ Missing | #8 |
| Verification badges | `/listings/{id}/badges` | Display only — awarding stays admin | ❌ Missing | #8 |
| Owner analytics | `/analytics/listing/{id}` | Views / clicks / shares for own listing | ❌ Missing | #8 |
| Credit balance & plans | `/credits`, `/plans` | Read-only; purchase stays web (Apple IAP) | ❌ Missing | #8 |
| Lead form | `/listings/{id}/contact` | App uses Free's `contact-form`; diverges when Pro's toggle is on | ⚠️ Partial | #8 |

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

One candidate to re-check: `api/dashboard.ts` carries a hardcoded status map including the literal
`listora_deactivated`. If a site can change a value in wp-admin, the app must read it rather than
carry its own copy — this is the exact shape of the WP Career Board pipeline-stage bug the
`wbcom-mobile-app` skill documents. Tracked in task #9.
