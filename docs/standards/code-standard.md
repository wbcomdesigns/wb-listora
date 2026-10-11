# WB Listora code standard

The bar for every change to WB Listora and WB Listora Pro. A developer opening any file should find one obvious place for each rule, code that explains itself, and no history lessons. `bin/code-standard-check.py` measures the parts a script can see. It runs in local CI and fails a change that makes any number worse.

## 1. One rule, one place

- Each behaviour (who may edit a listing, which status moves are allowed, "open now", what an email gate allows, a list of slugs) has exactly one function. The frontend form, the REST route, the admin screen, the CLI and Pro all call it.
- Before writing a check, grep for an existing one. Before fixing a bug, find its twin on the other entry points and fix the shared function.
- Current shared homes:
  - `wb_listora_user_can_edit_listing()`
  - `Status_Manager::change_status()`
  - `Business_Hours::open_now_ids()`
  - `wb_listora_get_demo_packs()`
  - `Page_Registry::get_id()`
  - `Dashboard_Data` (member dashboard counts and lists, block + REST)
  - `Claims_Model::delete()`
  - `Rate_Limiter::client_ip()`
  - Pro `Email_Helpers::send()`
  - Pro `Reverse_Listings::delete_need()`

## 2. Layers

- Templates and block `render.php` files render. They do not query the database: data comes from a model or service (`Listing_Data`, `Reviews_Model`, `Claims_Model`, ...) that REST uses too.
- REST controllers validate, call the shared function, and shape the response. Business rules live in `includes/core`, `includes/workflow` or a model.
- Pro talks to Free only through documented hooks, `wb_listora_service()` and public `wb_listora_*` functions (INV-3).

## 2a. Flexible by default: every default has a way out

Listora runs on themes, page builders and plugins we will never see. Nothing about a site is hard-coded without a documented way to change it:

- **Every default behaviour, list or label comes from one place that can be changed**: a PHP filter (`wb_listora_*`), a JS filter (`wp.hooks`, namespace `wbListora.*`), a CSS variable (`--listora-*`), a template override, or a setting.
- **Theme, plugin and service names appear only in data a site can extend.** Theme colour fallbacks sit in a CSS variable chain plus `wb_listora_css_variables`; bridges use `wb_listora_theme_bridges`; geocoders use `window.wbListoraGeocoder`.
- **A behaviour change ships with its escape hatch** (production rule 3), for example `wb_listora_categories_hide_empty`, `wb_listora_owner_name_use_real_name` or `wb_listora_theme_isolation`.
- **No escape hatch for a broken path.** A filter that turns a known bug back on is not flexibility.
- **New extension points are documented in the same change** (`docs/website/developer-guide/hooks-reference.md`, the manifest, and a JS section for `wp.hooks`).

## 3. One UI component each

- Pagination: `wb_listora_render_pagination()`.
- Empty state: `wb_listora_render_empty_state()`.
- Admin page header: `wb_listora_render_admin_header()`.
- Stars: one renderer for display and one for input.
- Buttons, badges and cards: the shared CSS components.
- A new screen uses these; it never hand-builds a second copy.

## 4. Comments explain why, once

- Comment the reason the code cannot show, in as few lines as it takes.
- No ticket numbers, customer names, dates or "this used to ..." stories in code. They belong in the commit message and the card, where `git log -S` and `git blame` find them.
- A docblock describes the contract (what goes in, what comes out, what it fires). It doesn't narrate how the function came to be.
- Delete comments that restate the code.

## 5. Size

- A class has one job. A file over 1,500 lines is split by job (not by line count) when it is next touched for a feature.
- A function over 100 lines gets a second look: is it doing two jobs?

## 6. No dead or defensive code

- No code for situations that cannot happen. Pro requires Free 1.9.0, so Pro does not guard `function_exists( 'wb_listora_x' )` for a function Free 1.9.0 always defines. It does not carry "older Free" fallbacks either.
- Unused classes, settings and options go through the deprecation rule (`_doing_it_wrong`, two minor releases, then removal). They don't linger silently.
- No `error_log()` left in shipped code paths, except deliberate failure logging behind `WP_DEBUG`.

## 7. Suppressions are justified

- Every `phpcs:ignore` names the sniff and says why on the same line (`-- table name from trusted prefix`).
- A suppression for something that can be fixed (escaping, prepared SQL, nonce) is a bug, not a waiver.

## 8. Proof

- New non-trivial logic gets one PHPUnit test that fails when the logic breaks.
- Every customer-visible fix adds a runbook Section D row and a regression journey (the plugin's self-growth contract).
- UI changes are checked in a browser at 1440 and 390, light and dark. Changes that affect theme styling are also checked across Reign, BuddyX, BuddyX Pro, Twenty Twenty-Four and Astra.
- `LOCAL_CI_SITE_URL=<site> composer ci:no-journeys` is green, including the dead-end crawl (stage 2.6) and this standard's check.

## The numbers (ratchet)

`bin/code-standard-check.py` counts these per plugin and compares them with `docs/standards/code-standard-baseline.json`:

| Metric | Why it matters |
|---|---|
| Ticket references in code comments | History in code instead of in git |
| Comment blocks longer than 15 lines | Narrative instead of intent |
| Files over 1,500 lines | Classes doing several jobs |
| `$wpdb` calls in `blocks/` and `templates/` | Data access in the view layer |
| `function_exists( 'wb_listora_…' )` guards | Defensive noise (Pro) or pluggable wrappers (Free, kept only where documented as pluggable) |
| `phpcs:ignore` without a `--` reason | Unexplained suppressions |
| `error_log(` calls | Debug output in shipped code |

A change may lower any number, and then updates the baseline with `--update`. It may not raise one. To pay down a number, take the group card for it on the board and lower it in one area at a time.
