---
journey: admin-notices-not-duplicated
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [admin-header, admin-notices]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
covers_card: 10332070181
---

# Admin notices show once on Listora list screens

Regression sentinel for the second 1.8.0 QA bounce.

## Background

The auto-injected Listora admin header printed a `.wp-header-end` marker on every Listora screen, including core list tables that already print one; core's common.js then copied each notice after both. The marker is now printed on plugin pages only (`$plugin_page`).

## Steps

### 1. Listings list
- **Action**: open `edit.php?post_type=listora_listing&updated=1`.
- **Expect**: one "1 post updated." notice; one `.wp-header-end` in the DOM.

### 2. Categories
- **Expect**: one marker on `edit-tags.php?taxonomy=listora_listing_cat`.

### 3. Plugin pages keep theirs
- **Expect**: Listing Types and Settings pages still have exactly one marker, and notices appear under the header.
