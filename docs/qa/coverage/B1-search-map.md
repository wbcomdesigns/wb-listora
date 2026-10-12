# Coverage: B1 search, map and misc consolidated journeys

One owner row per behaviour. Surfaces: F frontend, A admin, R REST, C CLI, B block. Viewports 1440/390 and light/dark are what each journey's frontmatter lists; "n.v." = declared, not verified live.

| behaviour | owner journey | surfaces | roles | 1440/390 | light/dark | gaps |
|---|---|---|---|---|---|---|
| Typing only suggests; Enter / Search button / location Enter run the search; Escape; one clear icon; suggest envelope | customer/search-keyboard-and-suggestions.md | F, R | guest, owner, admin | both (n.v.) | both (n.v.) | stop-word keyword returns 0 (expected-fail); browser run missing |
| Every in-use feature is filterable ("Show all features (N)") | customer/search-features-filter-reaches-every-feature.md | F, R | guest, owner, admin | both (n.v.) | n.v. | 2000+ query-count check n.v. |
| Filters survive another plugin owning `category`/`type`/`page` | customer/directory-url-params-are-prefixed.md | F, R, B | all 5 | both (n.v.) | n.v. | mu-plugin conflict + Pro load-more/saved search n.v. |
| Zero-result grid empty state (first paint, live, pinned type, editor picker) | customer/directory-empty-states.md | F, A(editor), B | guest, owner, editor, admin | both (n.v.) | n.v. | hydration/live steps n.v. |
| Grid default view and remembered choice | customer/grid-view-mode-default-and-remembered.md | F, B | guest, owner, admin | both (390 n.v.) | n.v. | hydration steps n.v. |
| Active page number contrast and past-the-end page | customer/pagination-active-page-contrast.md | F | guest, owner | both | both | light mode + unknown theme n.v. |
| rating / hours / featured_image / is_favorited on every listing row (REST) | system/search-rest-listing-row-fields.md | R | all 5 | n/a | n/a | tz matrix and scale rerun added, n.v. |
| Search engine never builds `IN ()`; extension seams scope every surface | system/search-engine-sql-safety-and-seams.md | R, F, B | admin, guest | 1440 | light | empty multi-value filter expected-fail; seam probe n.v. |
| Health Check reports the search index as usable | admin/health-check-search-index-usable.md | A | admin, editor, guest | 1440 | n.v. | destructive steps n.v. |
| Map renders without fatals and plots exactly the grid's search (markers, popup, bounds, cap notice) | customer/map-renders-and-agrees-with-grid.md | F, B, R | all 5 | both (390 n.v.) | n.v. | drag path, 390, dark n.v. |
| One map provider and start coordinates on every map surface; Google only draws with Google live | customer/map-provider-single-engine-and-defaults.md | F, A, B | guest, owner, admin | both (n.v.) | n.v. | marker drag n.v.; Google half needs key |
| OSM tile fallback preserved on upgrade | system/map-tiles-survive-upgrade.md | C(eval), A | admin | n/a | n/a | real pre-1.6.0 upgrade n.v. |
| Address search picker + confined Leaflet stacking | customer/submission-map-picker.md | F, A | owner, guest, admin | both (n.v.) | n.v. | browser run, RTL, fixed-header theme n.v. |
| Location terms follow `_listora_address` from every write surface; also: Location terms follow address from wp-admin | system/location-terms-follow-the-address.md | F, A, R, C, import | admin, owner, other, guest | both (n.v.) | n.v. | steps 8-9 need key/CSV; 10-12 n.v.; cross-area duplicate with system/location-terms-follow-the-address |
| `wp listora repair-locations` | system/repair-locations-command.md | C | admin | n/a | n/a | 100k batching gap (product) |
| Competitor hours are mapped on import; also: Migrated hours mapped or preserved raw | admin/competitor-hours-are-mapped-on-import.md | C(eval), A, F | admin, guest | both (n.v.) | n.v. | no licensed Directorist/ListingPro install; real migration and timezone not run |
| Dashboard list tabs reach every row; Claims REST + tab pagination | customer/dashboard-tabs-paginate-every-row.md | F, R | owner, other, editor, guest, admin | all 3 (390 n.v.) | n.v. | reviews-written pager needs seed |
| Space moderation queue is bounded | system/space-moderation-queue-is-bounded.md | R | admin, space-moderator, other, guest | n/a | n/a | needs host filter; scale step n.v. |
| Save heart overlays the card; count follows the toggle | customer/favorite-controls-on-card-and-detail.md | F, R | owner, other, editor, guest, admin | both (n.v.) | n.v. | browser run |
| Deactivate/reactivate return `already_*` flags | customer/listing-deactivate-reactivate-flags.md | R, F | owner, other, guest, editor, admin | both (n.v.) | n.v. | dashboard toast n.v. |
| Breadcrumb trail equals JSON-LD BreadcrumbList | customer/breadcrumb-trail-matches-json-ld.md | F, B | guest, owner, admin | both (390 n.v.) | n.v. | induced WP_Error, owner view n.v. |
| Every overlay opens and is usable at 1280/390 | system/overlays-usable-at-every-width.md | F, A | guest, owner, other, admin | 1280 + 390 | both (n.v.) | all width tests n.v. |
| Page registry heals a deleted/trashed mapping | system/page-registry-heals-stale-mapping.md | C(eval), F | admin, guest, owner | n/a | n/a | nothing-to-adopt, Free-only key n.v. |
| Terms of Service acceptance enforced; single-form steps never `hidden` | customer/submission-terms-acceptance-enforced.md | F, R | owner, other, guest, editor, admin | both (n.v.) | n.v. | wizard/browser steps n.v. |
| Success card centred | customer/submission-success-card-centered.md | F | owner, guest, admin | both (1440 n.v.) | n.v. | real submission n.v. |
| Frontend edit keeps description markup | customer/frontend-edit-keeps-description-markup.md | F, R, import | owner, other, guest, editor, admin | both (n.v.) | n.v. | texturize + importer paths n.v. |
| wp-admin features use the curated checkbox grid | admin/admin-features-checkbox-grid.md | A, R | admin, editor, owner, other, guest | 1440 | n.v. | click-tests n.v. |
| Duplicate review criteria get unique keys | admin/listing-type-review-criteria-unique-keys.md | A, F | admin, editor, owner | both (n.v.) | n.v. | not run live |
| Services box category checkboxes | admin/services-box-category-checkboxes.md | A | admin, editor | 1440 | n/a | not run live |
| Compare: one renderer, one table (Pro) | pro:customer/compare-one-renderer-one-table.md | F, B | guest, owner, admin | 1440/820/390 (n.v.) | n.v. | alignment/RTL n.v. |
| /needs/ is the marketplace (Pro) | pro:customer/needs-archive-is-the-marketplace.md | F, B | guest, owner, admin | both (390 n.v.) | n.v. | theme override, SEO canonical n.v. |

Entry-point check (frontend/backend/REST): search (F+R), map (F+R+A settings), location terms (F+A+R+import), hours (R+A+import), dashboard pagination (F+R), favourites (F+R), terms (F+R+A for admin editing). No data store here is missing an entry point; gaps are verification, listed above.
