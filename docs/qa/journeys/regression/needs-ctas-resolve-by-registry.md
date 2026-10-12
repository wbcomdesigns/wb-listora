---
journey: needs-ctas-resolve-by-registry
plugin: wb-listora-pro
priority: high
roles: [member, admin]
covers: [BC-10190564412, BC-10185680700, page-registry, needs, admin-bar]
prerequisites:
  - "Free + Pro active, Reverse Listings (needs) feature on"
  - "A page containing the listora-pro/post-need block under ANY slug (demo: /submit-a-request/, id 3629)"
  - "A page containing the listora-pro/needs-grid block under ANY slug (demo: /needs/, id 790)"
  - "A subscriber account (demo: subscriber2)"
estimated_runtime_minutes: 6
covers_card: 10190564412
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark]
changed: true
---

# Needs CTAs resolve through the page registry, not a hardcoded slug

Pro registers `post_need` (default block `listora-pro/post-need`) and `browse_needs` (default slug `needs`, block `listora-pro/needs-grid`) with Free's Page Registry (`wb-listora-pro/includes/class-pro-plugin.php:267-302`). Call sites use `wb_listora_get_page_url( 'post_need' )` / `( 'browse_needs' )` (`includes/features/class-needs-dashboard-tab.php:155`, `includes/needs/class-need-notifier.php:602,612`), never `get_page_by_path()`. The registry adopts a page by the BLOCK it contains, so any slug works. The CPT registers `show_in_admin_bar => false`.

Verified via: Playwright as subscriber2 on `/my-listings/?tab=needs` (links), `wp eval` for the registry keys and `show_in_admin_bar`, and as admin for the positive control.

## Steps

### 1. CTAs resolve whatever the pages are called
- **Setup**: demo pages are named `submit-a-request` and `needs` (neither is `post-need` / `browse-needs`); to prove it rename one more time to anything else.
- **Action**: as subscriber2 open `/my-listings/?tab=needs` (tab "My Needs") and evaluate:
```js
({ postANeed: [...document.querySelectorAll('a')].filter(a => /post a need/i.test(a.textContent)).map(a => a.href),
   browse:    [...document.querySelectorAll('a')].filter(a => /browse (all )?needs/i.test(a.textContent)).map(a => a.href) })
```
- **Expect** (observed): `postANeed` = `http://wb-listora.local/submit-a-request/` (the empty-state CTA "Post a Need", `wb-listora-pro/templates/dashboard/tab-needs.php:47`); `browse` = `http://wb-listora.local/needs/` ("Browse Needs" / "Browse all needs →" appear on the My Responses tab, `includes/features/class-my-responses-dashboard-tab.php:132,178`, and on the single-need template). Neither equals `location.origin + '/'` (that is the old fallback).

### 2. No wp-admin route is advertised to members
- **Why**: a public CPT whose `create_posts` cap members hold gets "+ New > Need" in the admin bar from WordPress core, pointing at `wp-admin/post-new.php?post_type=listora_need`.
- **Action**: as a subscriber on any frontend page collect `href`s containing `post-new.php` and `listora_need`.
- **Expect**: empty array. (Observed: subscriber2 has no admin bar at all on this theme, so this is vacuous for them; the code-level check is `get_post_type_object( 'listora_need' )->show_in_admin_bar === false`, observed.)
- **Positive control**: as admin the "+ New" menu lists Post, Media, Page, Product, Order, Coupon, Listing, Plan, User and NO Need entry; if "Need" appears, the flag regressed.

### 3. The registry keys stay registered
```bash
wp eval 'foreach (["post_need","browse_needs","compare"] as $k) printf("%s => %s\n",$k, wb_listora_get_page_url($k) ?: "(empty)");'
```
- **Expect** (observed): `post_need => /submit-a-request/`, `browse_needs => /needs/`, `compare => /compare-listings/`. `(empty)` is correct only when no page carries the block.

### 4. Role ladder
| Actor | Result |
|---|---|
| Member (subscriber) | steps 1-2 |
| Logged out | `/my-listings/` sign-in prompt; `/submit-a-request/` login prompt; browse page public |
| Moderator | same links on their own dashboard |
| Admin (control) | step 2 positive control; full CPT screens under Listora > Needs |

### 5. 390px
- **Expect**: CTA buttons >= 40px, no horizontal scroll on the Needs tab.

## Fail diagnostics
`class-pro-plugin.php:267-302` (registration), `features/class-needs-dashboard-tab.php:155`, `needs/class-need-notifier.php:602`, `templates/dashboard/tab-needs.php:47`.
