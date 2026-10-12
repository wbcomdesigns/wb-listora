# Journey coverage index

Every behaviour has exactly one row and every row has exactly one owner journey. A journey may own several rows when it walks several tightly coupled behaviours; a behaviour is never owned by two journeys. A journey that restates another journey's steps is a duplicate: merge it into the owner (the repo whose code owns the behaviour) or replace the restated steps with a one-line reference to the owner step.

Rules for editing these tables:

1. One row per behaviour, one owner journey. Never add a second row for a behaviour that already has an owner; extend the owner row instead.
2. The owner column holds an existing journey path (`free:` or `ora:` for wb-listora, `pro:` for wb-listora-pro). Deleting, merging or renaming a journey updates its rows in the same change; no row may point at a missing file.
3. Every journey file under `docs/qa/journeys/` (both repos) owns at least one row here.
4. Matrix columns use `Y` (asserted), `n` or `n.v.` (declared, not verified live) and `-` (not applicable).

| Area file | Behaviours (rows) | Scope |
|---|---|---|
| [A-submit-edit](A-submit-edit.md) | 26 | submit, edit, dashboard listings, renewal, setup, expiry (area A) |
| [B1-search-map](B1-search-map.md) | 31 | B1 search, map and misc consolidated journeys |
| [B2-browse-grid](B2-browse-grid.md) | 36 | B2 browse, grid, featured, compare, quick view, saved search, visibility |
| [C-reviews-claims-leads](C-reviews-claims-leads.md) | 45 | reviews, claims, leads, reporting (area C) |
| [D-dashboard-account](D-dashboard-account.md) | 30 | D-dashboard-account |
| [E-money-pro](E-money-pro.md) | 29 | Area E (credits, plans, coupons, refunds, receipts, webhook, needs) |
| [F1-admin](F1-admin.md) | 30 | area F1-admin |
| [F2-admin](F2-admin.md) | 31 | F2-admin (47 journeys consolidated to 33) |
| [G-seo-theme-a11y-rest](G-seo-theme-a11y-rest.md) | 37 | SEO, theme, a11y, REST contracts, block safety (area G) |
| **Total** | **295** | 175 Free journeys and 103 Pro journeys (excluding `.template.md`) |
