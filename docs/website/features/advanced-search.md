# Advanced Search

> **Pro feature** — requires [WB Listora Pro](../getting-started/activating-pro.md).
Power-user search that goes beyond keyword + category — multi-facet filtering (price range, rating, distance radius, custom field values), saved-search alerts that email new matches daily, and a wider field weight tuned for relevance. Built on the same query engine as Free search, just unlocked with more signal.

![Advanced Search — power filters expanded above the listing grid](../images/advanced-search.png)

## What it is

Free WB Listora ships a solid search experience — keyword + listing type + category + map radius. Advanced Search extends it for the segments that need more:

- **Multi-facet UI** — open a "More filters" panel and combine: keyword, type, category, location (with adjustable radius), price range, rating threshold, custom-field values (e.g. amenities for hotels, "remote OK" for jobs), open-now-only for businesses with hours.
- **Saved searches** — once a visitor finds a filter combo they like, they save it (in their `wp_user_meta` under `_listora_saved_searches`).
- **Daily email alerts** — Action Scheduler job `wb_listora_pro_saved_search_alerts` runs daily, finds new listings matching any user's saved searches since the last run, and emails matches via the `saved-search-alert.php` template.
- **Tuned relevance** — Advanced Search uses a different MySQL `MATCH AGAINST` weight + a larger candidate pool than Free search, so long-tail queries return more results.
- **REST routes** — `GET /listora/v1/saved-searches`, `POST /listora/v1/saved-searches`, `PATCH /listora/v1/saved-searches/{id}`, `DELETE /listora/v1/saved-searches/{id}` — usable by your own mobile app.

Combined with [Programmatic SEO Pages](seo-pages.md), Advanced Search turns the directory into a real search destination — not just a browsing surface.

## How you use it

### As a site owner — enable + configure

1. **Enable the feature:** Listora → Settings → Features → **Advanced Search** (default: **off** per product design — turn on intentionally; defaults emphasize the simpler search UX).
2. **Visit your Directory page** in an incognito window — verify a **More filters** button appears next to the existing search bar. Click it; the multi-facet panel slides open.
3. **Tune facet visibility** (optional): Settings → Search → **Advanced Filters** — tick which facets appear (price, rating, custom fields, open-now). Some may not apply to all listing types.
4. **Saved-search alerts:** Settings → Search → **Daily Alerts** — toggle on/off globally; per-user override is in each user's dashboard profile.

### As a visitor — power-search

1. On the Directory page, click **More filters**.
2. Set price range, rating, radius, custom fields. Hit **Apply**.
3. The grid + map update. Refine until you have what you want.
4. Click **Save this search** → name it ("Pet-friendly hotels in NYC under $200"). The search is stored against your user account.
5. From now on, when a new listing matches your search, you receive an email digest (daily) listing the matches with one-click links.
6. Manage saved searches from your dashboard → **Saved Searches** tab — edit, rename, disable, delete.

## Settings & options

| Setting | Location | Default | Notes |
|---|---|---|---|
| Feature toggle | Settings → Features → Advanced Search | **Off** | Off by default — enable if your audience benefits from filter density |
| Facet visibility | Settings → Search → Advanced Filters | All on | Per-facet toggle |
| Daily alerts | Settings → Search → Daily Alerts | On (when feature enabled) | Per-user override available |
| Alert cron | `wb_listora_pro_saved_search_alerts` | Daily | Action Scheduler |
| Storage (saved searches) | `wp_usermeta._listora_saved_searches` | — | Per-user, JSON-encoded |
| Storage (alert state) | `wp_usermeta._listora_saved_search_last_alert_at` | — | Per-saved-search timestamp |

REST routes (logged-in):

- `GET /wp-json/listora/v1/saved-searches` — list the current user's saved searches.
- `POST /wp-json/listora/v1/saved-searches` — create one.
- `PATCH /wp-json/listora/v1/saved-searches/{id}` — rename, change filter, toggle alerts.
- `DELETE /wp-json/listora/v1/saved-searches/{id}` — delete.

Developer hooks:

- `wb_listora_pro_advanced_search_facets` (filter) — modify the facet list shown in the More-Filters panel per page.
- `wb_listora_pro_saved_search_alert_subject` (filter) — modify the daily alert email subject.
- `wb_listora_pro_saved_search_match_query` (filter) — modify the candidate-match SQL (e.g. exclude listings older than 1 hour).

## Related

- [Search & Filters (Free)](search-and-filters.md) — the underlying search engine; Advanced Search is the extended UX on top.
- [Saved Searches (Pro)](saved-searches.md) — companion doc focused on the alert flow specifically.
- [Programmatic SEO Pages (Pro)](seo-pages.md) — turn high-value filter combinations into indexed landing pages.
- [Developer Reference: REST API](../developer-guide/rest-api.md) — `/saved-searches/*` endpoint shapes.
