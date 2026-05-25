# WB Listora Documentation

![WB Listora - directory site homepage on the modernized 1.0.5 UI](images/home-frontend.png)

WB Listora is a complete WordPress directory plugin. Build any type of listing directory - business, restaurant, hotel, real estate, jobs, events, and more - using native Gutenberg blocks and the WordPress Interactivity API.

This documentation covers **both WB Listora Free and WB Listora Pro**. Pages that document Pro-only features are marked with a **Pro feature** callout at the top. Browse by what you're trying to do:

---

## Find the docs you need by role

### Setup & Configuration
*"How do I install and configure my directory?"*

Start here if you're standing up a new directory. Covers installation, the setup wizard, your first directory page, listing types, Pro activation, and every settings tab.

→ [Setup & Configuration](#setup--configuration)

### For Site Owners
*"How do I run my directory day-to-day?"*

Moderating reviews, approving claims, managing moderators (Pro), tracking analytics (Pro), running coupons (Pro), awarding verification badges (Pro), coming-soon mode (Pro), white-label (Pro).

→ [For Site Owners](#for-site-owners)

### For Listing Owners
*"How do I add and manage my listings?"*

Submitting a listing from the frontend, the user dashboard, adding services, and (Pro) buying credits to activate listing plans.

→ [For Listing Owners](#for-listing-owners)

### For Visitors
*"How do I find what I need and engage with listings?"*

Search and filters, saving favorites, saved-search alerts (Pro), writing reviews (incl. multi-criteria and photo reviews on Pro), contacting owners via lead forms (Pro), comparing listings (Pro), posting needs to the reverse marketplace (Pro).

→ [For Visitors](#for-visitors)

### Maps
*"How do I use maps in my directory?"*

OpenStreetMap on Free (no API key), Google Maps on Pro (Places autocomplete, marker clustering).

→ [Maps](#maps)

### Migration & Import
*"How do I move from another directory plugin?"*

Step-by-step guides for migrating from Directorist, GeoDirectory, Business Directory Plugin, and ListingPro.

→ [Migration & Import](#migration--import)

### ‍ Developer Reference
*"How do I extend or integrate with WB Listora?"*

The 11 Free + 5 Pro Gutenberg blocks, every action and filter hook, the complete REST API contract, custom-field types, and the Pro extension surface.

→ [Developer Reference](#developer-reference)

---

## Setup & Configuration

Bring your directory online in under an hour.

| What you do | Guide |
|---|---|
| 1. Install WB Listora | [Installation & Activation](getting-started/installation.md) |
| 2. Run the setup wizard | [Setup Wizard](getting-started/setup-wizard.md) |
| 3. Create your directory page | [Creating Your Directory Page](getting-started/creating-directory-page.md) |
| 4. Define listing types (Restaurant, Hotel, etc.) | [Understanding Listing Types](getting-started/listing-types.md) |
| 5. (Pro) Install and activate WB Listora Pro | [Installing WB Listora Pro](getting-started/activating-pro.md) |
| 6. (Pro) Activate your license key | [License Management (Pro)](getting-started/pro-license.md) |
| Configure global behaviour | [General Settings](settings/general-settings.md) |
| Tune search behaviour | [Search Settings](settings/search-settings.md) |
| Configure submission & moderation | [Submission & Moderation Settings](settings/submission-settings.md) |

---

## For Site Owners

Day-to-day operation of your directory.

| What you do | Guide |
|---|---|
| Manage star ratings, written reviews, helpful votes, replies | [Reviews & Ratings](features/reviews-system.md) |
| Approve or reject business claim requests | [Business Claims](features/business-claims.md) |
| (Pro) Assign trusted team members as moderators | [Moderator Role (Pro)](features/moderators.md) |
| (Pro) Track views and clicks per listing | [Analytics (Pro)](features/analytics.md) |
| (Pro) Create discount codes for listing plans | [Coupons (Pro)](features/coupons.md) |
| (Pro) Award verified badges to vetted businesses | [Verification Badges (Pro)](features/verification-badges.md) |
| (Pro) Hide the directory while setting up, or run a members-only directory | [Coming Soon & Private Mode (Pro)](features/coming-soon.md) |
| (Pro) Rename the plugin for client handoff | [White Label (Pro)](features/white-label.md) |
| (Pro) Batch notifications into a daily digest | [Digest Notifications (Pro)](features/digest-notifications.md) |

---

## For Listing Owners

Adding and managing your own listings.

| What you do | Guide |
|---|---|
| Submit a listing from the frontend | [Submitting a Listing](features/frontend-submission.md) |
| Manage your listings, reviews, favorites from one place | [User Dashboard](features/user-dashboard.md) |
| Add a service catalog to your listing | [Services on Your Listing](features/services-per-listing.md) |
| (Pro) Purchase credits and activate a listing plan | [Credits & Pricing Plans (Pro)](features/credits-and-plans.md) |

---

## For Visitors

Finding what you need and engaging with listings.

| What you do | Guide |
|---|---|
| Search, filter, narrow by geography | [Search & Filters](features/search-and-filters.md) |
| Save listings to revisit | [Favorites](features/favorites.md) |
| (Pro) Save a search and get daily email alerts when new matches arrive | [Saved Searches (Pro)](features/saved-searches.md) |
| Write a review (star rating + text) | [Reviews & Ratings](features/reviews-system.md) |
| (Pro) Rate per criterion (Food, Service, Ambiance for restaurants etc.) | [Multi-Criteria Reviews (Pro)](features/multi-criteria-reviews.md) |
| (Pro) Attach photos to your review | [Photo Reviews (Pro)](features/photo-reviews.md) |
| (Pro) Contact a listing owner via a Contact form | [Lead Forms / Contact Owner (Pro)](features/lead-forms.md) |
| (Pro) Compare listings side by side | *Documentation in progress - see [Comparison block](features/blocks-overview.md#comparison-block-pro)* |
| (Pro) Post what you need; businesses respond | [Needs Marketplace (Pro)](features/needs-marketplace.md) |

---

## Maps

Maps in WB Listora work on both Free and Pro - the difference is the provider.

| What you do | Guide |
|---|---|
| Use OpenStreetMap (no API key required) | [OpenStreetMap (Free)](settings/map-settings.md) |
| (Pro) Replace OSM with Google Maps + Places autocomplete + clustering | [Google Maps (Pro)](features/google-maps.md) |

---

## Migration & Import

Moving from another directory plugin?

- [From GeoDirectory](migrate-from-geodirectory.md)
- [From Directorist](migrate-from-directorist.md)
- [From Business Directory Plugin](migrate-from-business-directory-plugin.md)
- [From ListingPro](migrate-from-listingpro.md)

---

## Developer Reference

Build on top of WB Listora.

| What you need | Guide |
|---|---|
| Block-by-block overview (11 Free + 5 Pro blocks) | [Gutenberg Blocks Overview](features/blocks-overview.md) |
| Every action and filter hook | [Hooks Reference](developer-guide/hooks-reference.md) |
| Full REST API contract | [REST API](developer-guide/rest-api.md) |
| Define your own field types | [Custom Fields & Field Types](developer-guide/custom-fields.md) |
| Extend the Free plugin from Pro or a third-party add-on | [Extending with WB Listora Pro](developer-guide/extending-with-pro.md) |

---

## Also useful

- **[Feature Catalog](feature-catalog.md)** - every feature on one page, with tier (Free / Pro) and audience.
- **[Why WB Listora?](why-wb-listora.md)** - what makes it different from competitors.
- **[Plugin Comparison](comparison.md)** - side-by-side vs other directory plugins.
