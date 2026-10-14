# WB Listora

A block-based directory plugin for WordPress. Build business, restaurant, hotel, real estate, job, event and other listing directories with faceted search, maps, frontend submission, reviews and claims.

## What it does

**Listings**
- Ten listing types ship ready to use: Business, Restaurant, Real Estate, Hotel, Event, Job, Healthcare, Education, Place and Classified. Each has its own fields and categories, and you can edit them or add your own in the type editor.
- Taxonomies for listing type, category, tag, location and feature, plus a service category for services attached to a listing.
- Business hours, services, featured listings, expiry and renewal, and per-type moderation and review settings.
- Demo content packs you can import from the setup wizard, the settings screen or WP-CLI.

**Search and maps**
- A Search block with keyword, custom-field and category filters, a "Near me" radius, and live results without a page reload.
- Interactive maps built on Leaflet, with marker clustering and "search this area". Google Maps is added by WB Listora Pro.

**Submission and members**
- A multi-step Add Listing wizard with conditional fields, photo uploads and a duplicate check. Members need an account to submit.
- A member dashboard for listings, reviews, claims, favorites, leads and profile.
- Favorites, and a contact form on each listing that saves every message as a lead. Owners read their leads in the dashboard, site owners under **Listora > Listings > Leads**.

**Reviews and claims**
- Star ratings, helpful votes, owner replies, reporting and a moderation queue.
- Business claims with proof documents and admin approval.

**Import, export and migration**
- CSV, JSON and GeoJSON import and export, run in the background for large files.
- Migrators for Business Directory Plugin, Directorist, GeoDirectory, HivePress and ListingPro.

**Trust and operations**
- Schema.org structured data for listings.
- Spam protection with reCAPTCHA v3, Cloudflare Turnstile, Akismet, a honeypot and rate limiting.
- Email templates, an email log, and automation triggers for listing, review, claim, favorite and member events that other tools can listen to.
- WordPress personal data export and erase support, and Site Health checks.
- Works with the block editor and with classic themes, and picks up the brand colour of common themes and page builders.

**Companion plugins**
The Integrations screen can install and connect other Wbcom plugins: BuddyNext, Jetonomy, WB Gamification, Learnomy, WP Career Board and MediaVerse.

## Who it is for

Site owners and agencies who need a directory or listing site on WordPress without custom development, and developers who want a block-based, REST-first directory to build on. WB Listora Pro adds monetization and advanced discovery on top of the same plugin.

## Where to get it

- Product site: [listora.org](https://listora.org)
- Download: [wbcomdesigns.com/downloads/listora/](https://wbcomdesigns.com/downloads/listora/)
- Pro edition: [wbcomdesigns.com/downloads/listora-pro/](https://wbcomdesigns.com/downloads/listora-pro/)

WB Listora is not listed on WordPress.org.

## Documentation

- [Documentation on listora.org](https://listora.org/docs/)
- [Documentation hub](https://docs.wbcomdesigns.com/products/listora/)
- [Release notes](https://wbcomdesigns.com/release-notes/listora/)

## Requirements

- WordPress 6.9 or later
- PHP 8.1 or later

## Installation

1. Download the plugin from the [download page](https://wbcomdesigns.com/downloads/listora/).
2. In WordPress go to **Plugins > Add New > Upload Plugin**, upload the zip and activate it.
3. Listora opens its setup wizard on activation. Until setup is complete it stays in the sidebar as **Listora > Setup Wizard** (`admin.php?page=listora-setup`).
4. Pick your listing types, optionally import demo content, and finish. Everything can be changed later under **Listora > Settings**.

## For developers

**Identifiers**

| Item | Value |
|---|---|
| PHP namespace | `WBListora` |
| Function and hook prefix | `wb_listora_` |
| Text domain | `wb-listora` |
| Post type | `listora_listing` |
| Taxonomies | `listora_listing_type`, `listora_listing_cat`, `listora_listing_tag`, `listora_listing_location`, `listora_listing_feature`, `listora_service_cat` |
| Meta prefix, table prefix | `_listora_`, `{wpdb prefix}listora_` |
| Interactivity API namespace | `listora/directory` |

**Blocks** (11, all registered from `blocks/*/block.json` under the `listora/` namespace): `listing-search`, `listing-grid`, `listing-card`, `listing-detail`, `listing-reviews`, `listing-submission`, `listing-map`, `listing-calendar`, `listing-categories`, `listing-featured` and `user-dashboard`.

**REST API.** Namespace `listora/v1`, with controllers for `listings`, `listing-types`, `search`, `reviews`, `claims`, `favorites`, `leads`, `dashboard`, `settings`, `submit`, `me`, `auth` and `import`/`export` (CSV, JSON and GeoJSON). Contact-form messages post to `listings/{id}/contact-form`. See the [REST API reference in this repo](docs/REST-API.md).

**Hooks.** Actions and filters use the `wb_listora_` prefix. The full list is in the [hooks reference](https://listora.org/docs/developer-guide/hooks-reference/).

**Template overrides.** Copy a template from `templates/` into `{your-theme}/wb-listora/` and keep the same relative path. Details: [template overrides](https://listora.org/docs/developer-guide/template-overrides/).

**WP-CLI.** The `wp listora` command has these subcommands: `stats`, `reindex`, `listing-types`, `import`, `export`, `migrate`, `demo`, `repair`, `repair-locations`, `repair-credit-ledger`, `test-email`, `cleanup` and `audit-prices`. Run `wp help listora <subcommand>` for options, or see the [WP-CLI guide](https://listora.org/docs/developer-guide/wp-cli-commands/).

**Build from source**

```bash
composer install --no-dev
npm install
npm run build        # blocks and layered CSS
npm run start        # watch mode
```

Quality checks available through Composer: `composer phpcs`, `composer phpstan`, `composer phpunit` and `composer ci`. See [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md).

## Support and licence

- Support: [wbcomdesigns.com/support/](https://wbcomdesigns.com/support/)
- Licence: GPL v2 or later. See [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).

Built by [Wbcom Designs](https://wbcomdesigns.com).
