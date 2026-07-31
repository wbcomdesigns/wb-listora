# Stuck / Confusion Audit — 2026-07-31

Browser + config pass over site-owner admin and member frontend on `directory.local` (Listora Free+Pro 1.3.0 / 1.3.1 cycle). Goal: find where owners get stuck or members get confused, then fix.

## Solved in this pass

| Case | Fix |
|------|-----|
| Claim admin email CTA → 404 (`page=listora&tab=claims`) | → `page=listora-claims` |
| Free contact form `actions.submitContactForm` missing | Implemented in IAPI `store.js` (+ rebuild) |
| Custom badges only on detail, missing on cards/QV | Pro hooks `wb_listora_card_view_data` + `wb_listora_after_card_image` |
| Sitemap OFF still leaked Listora taxonomies | `wp_sitemaps_taxonomies` filter drops `listora_*` |
| Features Google Maps ON but OSM still live (no key) | Live map status notice on Maps tab; provider filter falls back to OSM without key |
| Buy Credits “Buy Now” → same page loop | Treat self-referential pack URLs as missing; clear member notice; seed no longer writes circular URLs |

## Verified OK (no product bug)

- Admin surfaces with correct slugs: coupons, badges, audit, webhooks, moderators, needs, analytics, transactions
- Guest submit login gate
- Browse/post needs, compare, submission plan step
- Pro lead form uses `submitLeadForm` when lead_form is ON (Free contact path covered for lead_form OFF)

## Remaining owner config (not code bugs)

- **No payment gateway credentials** — members correctly see “checkout is not available yet” until Stripe/PayPal (or a real external pack URL) is configured
- **Google Maps feature ON without API key / provider still OSM** — intentional; Maps tab now states OpenStreetMap is live
- Pro license banner (updates paused) — expected until license entered

## Basecamp cards (pre-existing groups)

1. Comms wiring — claim CTA + Free contact IAPI — **both fixed in code this pass**
2. Discovery + SEO — custom badges on cards + sitemap taxonomies — **both fixed in code this pass**

