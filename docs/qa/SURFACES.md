# Surfaces — behaviour rows

One row per behaviour. A verdict covers every surface in the row.

| Behaviour | Surfaces | file:line | grep that re-verifies |
|---|---|---|---|
| Need response count (display) | /needs/ grid; single need header; REST `GET /listora/v1/needs/{id}` `prepare_need()`; matcher/expire email; My Needs tab | Pro `blocks/needs-grid/render.php:133-143`; `class-need-single-template.php:372`; `class-needs-controller.php` `prepare_need()`; `templates/dashboard/tab-needs.php:58` | `get_counts_for_needs` / `_listora_need_response_count` |
| Need response count (gates) | Post a Need edit banner; My Needs Delete handler; REST PATCH after-quotes; REST DELETE close-vs-trash; admin Needs Quotes column | Pro `templates/blocks/post-need/post-need.php:58`; `class-reverse-listings.php:171` and `:995`; `class-needs-controller.php:767` and `:840` | `META_PREFIX . 'response_count'` / `_listora_need_response_count` |
