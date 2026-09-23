# WB Listora — Core Paths (the 60–70%)

> **What nearly every owner uses, ranked.** QA walks these first, every cycle,
> as the named role, on a clean install. Bug priority follows this list (see
> `owner-questions.md` → Triage): a defect on a core path outranks one on an edge
> regardless of who reported it.
>
> **Seeded from evidence, confirmed by a human once.** Evidence: what activation
> creates, what the settings screen shows first, what the readme leads with, what
> the Basecamp ledger shows people report on, and the free/pro split (free is the
> core by definition). Re-confirm at each major release; the ledger will tell you
> when the ranking has drifted (`C-4`).

Last confirmed: 2026-09-18 by Varun (plugin owner). Ranking accepted as seeded:
free discovery and submission are the spine; credits (9), moderation (11) and
emails (12) are real but rank below them. Re-confirm at the next major release.

| # | Flow (owner's words) | Role | Surface | Why it is core (evidence) | Journey | Free/Pro |
|---|---|---|---|---|---|---|
| 1 | "Visitors find a listing" — browse the directory, filter, open one | anonymous | Directory archive + single listing | Readme's first sentence: "listings, faceted search, maps". The zero-config flow | `browse-and-open` | Free |
| 2 | "A member submits a listing" — multi-step wizard with media | member-owner | Submission wizard | Readme names it a key feature; account required to submit | `submit-listing` | Free |
| 3 | "A member manages what they submitted" — edit, deactivate, renew | member-owner | User dashboard block | Dashboard is the member's home; 4 board cards filed against it | `dashboard-manage` | Free |
| 4 | "Search actually finds things" — faceted + geo/radius + map area | anonymous | Search block + map | Readme feature 2; `wb_listora_reindex_offset` shows a live index to keep correct | `faceted-search` | Free |
| 5 | "Reviews and ratings work both ways" — leave one, owner replies | member-owner + member-other | Reviews panel on single listing | Readme feature; 3 board cards open against reviews | `review-and-reply` | Free |
| 6 | "Someone claims their business" — request, owner approves, ownership moves | member-other → admin | Claim flow + admin approval | Readme feature; ownership transfer is a data-integrity path | `claim-listing` | Free |
| 7 | "The owner sets the directory up" — setup wizard, listing types, demo content | admin | Setup wizard + Listora admin | `wb_listora_setup_complete` / `wb_listora_setup_data`; 3 board cards on the wizard | `setup-wizard` | Free |
| 8 | "The owner shapes what a listing is" — listing types, custom fields, services | admin | Listing type registry admin | `wb_listora_needs_defaults`, `wb_listora_defaults_created` on activation | `listing-types` | Free |
| 9 | "Members buy and spend credits" — balance, purchase, gated action | member-owner | Credits / Buy Credits | 3 RFT cards + SDK integration; money path | `credits-purchase` | Free + Pro |
| 10 | "Listings show on a map" — markers, clustering, popups | anonymous | Map block | Readme feature 3; clustering card open | `map-render` | Free (Google via Pro) |
| 11 | "The owner moderates what arrives" — pending queue, approve, reject | admin | Moderation | Submission implies moderation; 5 `wp_mail` sites hang off it | `moderate-queue` | Free |
| 12 | "Members are told what happened" — submission, claim, review emails | member-owner | Email | 5 `wp_mail` sites in the inventory | `notifications` | Free |

## Edge (walk after core — welcome findings, never the starting point)

| Flow | Role | Surface | Why it is edge |
|---|---|---|---|
| Import / export CSV, JSON, GeoJSON | admin | Import-export admin | One-time migration, not daily use |
| REST + Application Passwords / app login | developer | REST API | Powers the app, but not an owner-facing surface |
| WP-CLI commands | developer | CLI | Operator tooling |
| Search re-index after bulk change | admin | Indexer | Runs itself; only surfaces when wrong |
| Term entity repair migration | admin | Migrator | One-off repair (`wb_listora_term_entity_repair_done`) |

## Zero-config check (C-2)

The first thing a new owner would try, with **no settings touched**:
**activate, run the setup wizard, land on a directory page that lists something
and opens a single listing** — must complete on a fresh activate. Two open board
cards sit on exactly this path (wizard claims "ready!" while demo content is
still importing; wizard is re-runnable with no completion guard), so treat it as
the first walk of every cycle until both are closed.

## Notes for the confirming human

- Ranking is seeded from evidence, not preference. If credits (9) matter more to
  your customers than claims (6), say so and I will re-rank — the ledger suggests
  credits is where recent reports cluster.
- Rows 3, 5, 7 carry open board cards today; they are ranked on what owners use,
  not on where bugs happen to be.
- Nothing here is Pro-only. If Pro's directory surfaces (Google Maps, per-plan
  entitlements) deserve a core row, add them and mark the row Pro.
