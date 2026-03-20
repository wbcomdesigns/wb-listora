# WB Listora — Remaining Gaps for Complete Launch

**Updated:** 2026-03-20 (Session 2 progress below)

## Session 2 Completed
- [x] WPCS auto-fix on all 56+19 PHP files
- [x] .pot translation file generated
- [x] readme.txt created
- [x] Suggest endpoint: contains LIKE
- [x] Deprecated get_settings() renamed
- [x] Reviews tab with real data + rating summary + distribution bars
- [x] Dashboard Profile tab with form + notification prefs
- [x] Dashboard tab switching JS fallback
- [x] Admin Reviews moderation page (list, filter, approve/reject/delete)
- [x] Admin Claims management page
- [x] Admin Import/Export page
- [x] Admin menu: Reviews + Claims + Import/Export added
- [x] Per-notification toggle settings (10 events with descriptions)
- [x] Dark mode CSS tokens (full set)
- [x] RTL CSS support (direction overrides + logical properties)
- [x] Share dialog (Web Share API + clipboard fallback)
- [x] Favorite button toggle
- [x] Button type="button" on all interactive buttons
- [x] Pro: All 15 feature classes moved to init() pattern
- [x] Pro: Admin Credits management page (summary cards + transaction log)
- [x] Pro: Admin Analytics page (period selector, summary cards, top listings)
**Goal:** Ship Free + Pro together as complete product on wbcomdesigns.com

---

## Session Summary (Today's Fixes)

### Code Fixes
- [x] Keyword search `$wpdb->prepare()` param ordering
- [x] `wb_listora_render_hours()` function ordering
- [x] Schema generator array-to-string warning
- [x] Empty state `is-hidden` server-side
- [x] Filter count badge + clear button hidden server-side
- [x] Filter panel `hidden` by default

### Template/Layout Fixes
- [x] Single listing: `single_template` + `the_content` filter with recursion protection
- [x] Full-width page template registered + assigned to 7 pages
- [x] Listings page: 3-column grid (was 2-col split)
- [x] Directory-full: 3-column grid (was cramped split)
- [x] Submission form: shows all types (was locked to restaurant)

### UX/CSS Fixes
- [x] Card image placeholder: premium gradient + dot pattern
- [x] Featured badge: golden gradient
- [x] Dashboard stats: colored top borders
- [x] Listing detail: 2-column layout (content + sidebar)
- [x] Tab switching: vanilla JS fallback
- [x] Search placeholder text updated

### Data
- [x] 20/20 listings have Unsplash stock photos

---

## FREE PLUGIN — Remaining Work

### P0 (Blockers — Must fix before any launch)

| # | Issue | File(s) | Effort |
|---|-------|---------|--------|
| 1 | Unescaped output in 11 render.php | `blocks/*/render.php` | 1hr |
| 2 | Unsanitized `$_GET/$_POST` (12 instances) | admin, calendar, wizard | 1hr |
| 3 | Generate `.pot` translation file | CLI: `wp i18n make-pot` | 5min |
| 4 | Create `readme.txt` for WP.org | root | 30min |
| 5 | Orange loading bar visible when not loading | search block CSS/JS | 30min |
| 6 | Suggest endpoint prefix-only LIKE | `class-search-controller.php:466` | 15min |

### P1 (Important — Should fix before launch)

| # | Issue | File(s) | Effort |
|---|-------|---------|--------|
| 7 | Reviews tab shows placeholder text, not actual reviews | `listing-detail/render.php:367` | 2hr |
| 8 | No pagination on listings grid | `listing-grid/render.php` | 1hr |
| 9 | Dashboard Profile tab empty | `user-dashboard/render.php` | 2hr |
| 10 | Categories block untested | `listing-categories/` | 1hr |
| 11 | Featured listings block untested | `listing-featured/` | 1hr |
| 12 | Calendar block untested | `listing-calendar/` | 1hr |
| 13 | Dark mode CSS support | `assets/css/shared.css` | 2hr |
| 14 | RTL CSS support | All CSS files | 2hr |
| 15 | HTML email templates | `class-notifications.php` | 3hr |
| 16 | Per-notification toggles in settings | `class-settings-page.php` | 2hr |
| 17 | Admin Reviews page (moderation queue) | `admin/` | 3hr |
| 18 | Admin Claims page | `admin/` | 2hr |
| 19 | Listing edit from dashboard (edit mode in submission form) | `listing-submission/` | 2hr |
| 20 | Share dialog implementation | `listing-detail/view.js` | 1hr |

### P2 (Nice to have)

| # | Issue | Effort |
|---|-------|--------|
| 21 | Conditional field logic | 4hr |
| 22 | Duplicate listing detection | 2hr |
| 23 | Listing expiry email reminders | 1hr |
| 24 | Accessibility formal audit (WCAG 2.1 AA) | 4hr |
| 25 | Button `type` attributes on all buttons | 1hr |
| 26 | Image alt attributes on 4 locations | 30min |

---

## PRO PLUGIN — Remaining Work

### P0 (Security Blockers)

| # | Issue | File(s) | Effort |
|---|-------|---------|--------|
| 1 | Add `permission_callback` to 7 REST routes | analytics, advanced-search, comparison, lead-form | 30min |
| 2 | Run `phpcbf` on Pro plugin (267 auto-fixable) | All PHP files | 15min |
| 3 | Unsanitized `$_POST` in license, verification | 2 files | 30min |
| 4 | Move hooks from constructors to init() | 10 classes | 1hr |

### P1 (Core Pro Features — Must be complete)

| # | Issue | Current State | Effort |
|---|-------|--------------|--------|
| 5 | Google Maps full integration | Basic class exists | 3hr |
| 6 | Plan selection in submission flow | Not wired | 3hr |
| 7 | Analytics owner dashboard (charts/stats) | Server-side tracking works, no frontend | 5hr |
| 8 | Lead form full UI + email forwarding | Class skeleton | 4hr |
| 9 | Comparison side-by-side table | Class skeleton | 4hr |
| 10 | Multi-criteria reviews frontend UI | DB + backend done | 3hr |
| 11 | Photo reviews upload UI | Backend done | 3hr |
| 12 | Verification admin UI + badge display | Basic class | 2hr |
| 13 | Admin credit management page | Not built | 3hr |
| 14 | Saved searches with email alerts UI | Backend logic exists | 3hr |
| 15 | License activation/deactivation UI | Basic class | 2hr |

### P2 (Pro Features — Can ship without but should have)

| # | Issue | Effort |
|---|-------|--------|
| 16 | Outgoing webhooks (Zapier/Make.com) | 5hr |
| 17 | Competitor migration tools | 8hr |
| 18 | Coupon codes / promo pricing | 3hr |
| 19 | Quick view popup on cards | 3hr |
| 20 | Infinite scroll on grid | 2hr |
| 21 | Custom card badges | 2hr |
| 22 | Notification digest emails | 2hr |

---

## Effort Estimates

| Category | P0 | P1 | P2 | Total |
|----------|----|----|----|----|
| Free Plugin | 3hr | 28hr | 12hr | 43hr |
| Pro Plugin | 2hr | 35hr | 25hr | 62hr |
| **Combined** | **5hr** | **63hr** | **37hr** | **105hr** |

### Recommended Session Plan (P0 + P1 only = 68hr)

| Session | Focus | Hours | Deliverables |
|---------|-------|-------|-------------|
| Next | Free P0 fixes + escaping + readme | 4hr | Submission-ready free plugin |
| +1 | Reviews integration + pagination + profile tab | 5hr | Complete dashboard + detail tabs |
| +2 | Admin pages (reviews moderation, claims) | 5hr | Admin backend complete |
| +3 | Email templates + notification settings | 5hr | Notification system complete |
| +4 | Pro P0 security fixes + Google Maps | 4hr | Pro security clean |
| +5 | Pro analytics dashboard + charts | 5hr | Analytics feature complete |
| +6 | Pro lead form + comparison table | 5hr | Two Pro features complete |
| +7 | Pro plan selection + credit management | 5hr | Payment flow complete |
| +8 | Pro reviews (multi-criteria + photos) | 5hr | Pro reviews complete |
| +9 | Pro saved searches + verification | 5hr | Remaining Pro features |
| +10 | Dark mode + RTL + accessibility | 5hr | Cross-compat complete |
| +11 | Categories/featured/calendar blocks + testing | 5hr | All blocks verified |
| +12 | Final QA + polish + submission readiness | 5hr | Launch ready |
