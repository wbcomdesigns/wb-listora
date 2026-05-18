# Pro P2 Sprint Plan — Development Order

## Approach: Layered Bottom-Up with 3-4 Parallel Agents Per Layer

```
Each layer builds on the previous. No circular dependencies.
Each commit is shippable. Multi-directory deferred to Pro v1.1.
```

---

## Pre-Sprint: FREE Plugin Services (Current Session)

Build the FREE part of Listing Services before starting Pro:
- `listora_services` table + `listora_service_cat` taxonomy
- Services CRUD class + REST endpoints
- "Services" tab on listing detail page
- "Manage Services" in user dashboard
- Search indexing (service text → listing search_index)
- Service category filter on search block
- Schema.org Service markup

**Why first:** Pro services features (video, gallery, cross-listing search) extend this.

---

## Pro v1.0 — 12 Features in 4 Layers

### Layer 1: Foundation (4 agents in parallel)

| Agent | Feature | Files Owned | Depends On |
|-------|---------|-------------|------------|
| A1 | P2-05 Badges | `features/class-badges.php`, `includes/badges/` | Nothing |
| A2 | P2-06 Moderator Role | `features/class-moderator.php`, `includes/moderator/` | Nothing |
| A3 | P2-07 Audit Log | `features/class-audit-log.php`, `includes/audit/` | Nothing |
| A4 | P2-14 Services PRO | `features/class-services-pro.php` (extends FREE) | FREE services |

**No file conflicts.** Each agent owns its feature directory.

### Layer 2: Core Features (4 agents in parallel)

| Agent | Feature | Files Owned | Depends On |
|-------|---------|-------------|------------|
| B1 | P2-02 Coupons | `features/class-coupons.php`, `includes/coupons/` | Badges (plan perks) |
| B2 | P2-03 Quick View | `features/class-quick-view.php`, JS module | Listing detail API |
| B3 | P2-04 Infinite Scroll | `features/class-pagination.php`, JS module | Search API |
| B4 | P2-09 Visual Field Mapper | `features/class-field-mapper.php`, admin page | Import system |

### Layer 3: Integration (3 agents in parallel)

| Agent | Feature | Files Owned | Depends On |
|-------|---------|-------------|------------|
| C1 | P2-01 Webhooks | `features/class-webhooks.php`, `includes/webhooks/` | Audit log (logs deliveries) |
| C2 | P2-08 Programmatic SEO | `features/class-seo-pages.php`, rewrite rules | Taxonomy data |
| C3 | P2-11 Google Places | `features/class-google-places.php` | Import system |

### Layer 4: Complex (2 agents, careful sequencing)

| Agent | Feature | Files Owned | Depends On |
|-------|---------|-------------|------------|
| D1 | P2-12 BuddyPress | `features/class-buddypress.php`, BP templates | Listings + reviews hooks |
| D2 | P2-13 Reverse Listings | `features/class-reverse-listings.php`, `includes/needs/`, blocks | Services + matching |

---

## Pro v1.1 (Post-Launch)

| Feature | Why Deferred |
|---------|-------------|
| P2-10 Multi-Directory | Touches every query, setting, and block. Needs stable v1.0 first. |

---

## Execution Rules

1. **Each layer completes before next starts** — commit + verify + push
2. **3-4 agents per layer** — no file conflicts, each agent owns its directory
3. **Self-check loop** per agent: php -l → WPCS → PHPStan → browser test
4. **Audit log wires into everything** — Layer 1 builds it, Layers 2-4 log to it
5. **Webhooks fire on everything** — Layer 3 builds it, hooks into all events
6. **Pro boots at plugins_loaded:20** — after Free loads at plugins_loaded:10
