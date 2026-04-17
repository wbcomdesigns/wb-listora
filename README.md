# WB Listora

A modern, block-based WordPress directory plugin. Build any kind of listing directory — business, restaurant, hotel, real estate, jobs, events — with faceted search, interactive maps, frontend submission, reviews, claims, and a built-in WP-CLI + REST API.

[![CI](https://github.com/wbcomdesigns/wb-listora/actions/workflows/ci.yml/badge.svg)](https://github.com/wbcomdesigns/wb-listora/actions/workflows/ci.yml)

---

## Quick start

```bash
# Install into a WordPress site
cd wp-content/plugins
git clone https://github.com/wbcomdesigns/wb-listora.git
cd wb-listora
composer install --no-dev
npm install && npm run build

# Activate
wp plugin activate wb-listora
```

Then visit **WordPress Admin → Listora** and run the setup wizard.

**Requirements:** WordPress 6.9+, PHP 7.4+, MySQL 5.7+ / MariaDB 10.2+.

---

## What you get

**10 listing types out of the box** — Restaurant, Hotel, Real Estate, Business, Job, Event, Healthcare, Education, Place, Classified. Each with its own fields, categories, and card layout. Add your own via the type editor.

**11 Gutenberg blocks** — Search, Grid, Card, Detail, Reviews, Submission, Map, Calendar, Categories, Featured, User Dashboard. All use the WordPress Interactivity API — no jQuery, instant filtering, no page reloads.

**Two-phase search engine** — FULLTEXT keyword search on a denormalized `search_index` table, then custom-field and geo filtering with bounding-box + Haversine. Scales to 100k+ listings on a single node.

**Frontend submission** — Multi-step wizard with type selection, conditional fields, media upload, optional guest checkout, reCAPTCHA v3 / Cloudflare Turnstile, terms acceptance.

**Reviews + ratings** — Star ratings, multi-criteria, helpful votes, owner replies, moderation queue. Pro adds photo reviews.

**Claims** — Business owners claim listings with proof documents and email notification to admin.

**Favorites** — Users save listings into collections. REST-backed.

**CSV import/export** — Column-mapping importer, GeoJSON support, 4 competitor migrators (Directorist, GeoDirectory, Business Directory Plugin, ListingPro).

**WP-CLI** — `wp listora stats | reindex | import | export | repair | migrate | demo`.

**REST API** — 42 endpoints under `listora/v1/`. Every write has `before_` + `after_` hooks and response shape filters.

**Schema.org structured data** — Automatic JSON-LD per type for rich results.

**Theme-adaptive** — CSS tokens inherit from `theme.json`. Works on block themes and classic themes.

**Accessible** — ARIA roles, keyboard-first navigation, screen-reader labels, WCAG 2.1 AA target.

---

## Architecture at a glance

```
wb-listora/
├── wb-listora.php          Plugin entry, constants, autoloader
├── includes/
│   ├── core/               CPT, taxonomies, field registry, type definitions
│   ├── rest/               12 REST controllers (listora/v1/*)
│   ├── search/             Search engine, indexer, facets, geo queries
│   ├── admin/              Menus, settings (9 tabs), setup wizard, columns
│   ├── db/                 Schema + migrator (11 custom tables)
│   ├── import-export/      CSV, GeoJSON, competitor migrators
│   └── workflow/           Cron, notifications, expiration
├── blocks/                 11 Gutenberg blocks (Interactivity API)
├── src/                    Block source + shared interactivity store
├── templates/              Overridable via theme/wb-listora/…
└── tests/                  PHPUnit (unit + integration)
```

More detail: **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)**.

---

## Custom database tables

Prefix: `{wp_prefix}listora_`. All `ENGINE=InnoDB`.

| Table | Purpose |
|---|---|
| `geo` | Lat/lng + geohash per listing (primary key: listing_id) |
| `search_index` | Denormalized search rows with FULLTEXT index |
| `field_index` | Custom-field filter index |
| `reviews` + `review_votes` | Ratings + helpful votes |
| `favorites` | User saves into collections |
| `claims` | Business claim requests |
| `hours` | Weekly schedule per listing |
| `analytics` + `payments` | Pro hooks into these |
| `services` | Listing services / menu items |

---

## Hooks

30+ action/filter hooks for extension. Every write operation fires `wb_listora_before_*` (filter — return `WP_Error` to abort) and `wb_listora_after_*` (action). Every REST response is filterable via `wb_listora_rest_prepare_{resource}`.

Full reference: **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)** + `CLAUDE.md`.

---

## Development

```bash
composer install           # PHP dev deps (PHPUnit 9.6, PHPStan, WPCS)
npm install && npm run build   # Block JS/CSS

# Tests
composer run-script test   # Or: vendor/bin/phpunit
vendor/bin/phpstan analyse # Level 7
vendor/bin/phpcs           # WordPress coding standards

# WP-CLI
wp listora stats
wp listora reindex --batch-size=500
```

**CI pipeline** (`.github/workflows/ci.yml`):
- PHP Lint (PHP 8.1 – 8.4)
- PHPCS WordPress standards (blocking)
- PHPStan level 7 (blocking, with baseline)
- PHPUnit (PHP 8.1/WP 6.9 + PHP 8.2–8.4/WP latest)
- Plugin Check (PCP, blocking)

See **[docs/CONTRIBUTING.md](docs/CONTRIBUTING.md)**.

---

## Pro add-on

**[WB Listora Pro](https://github.com/wbcomdesigns/wb-listora-pro)** adds Google Maps, a credit-based payment system, pricing plans, analytics, lead forms, listing comparison, multi-criteria + photo reviews, saved-search alerts, verification badges, needs/reverse-listings, coupons, visual field mapper, outgoing webhooks, and more.

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) if present, or https://www.gnu.org/licenses/gpl-2.0.html.

Built by [Wbcom Designs](https://wbcomdesigns.com).
