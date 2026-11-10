---
journey: dashboard-tab-load-leaves-url-and-customizer-alone
group: dashboard-account
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [owner, admin]
covers: [dashboard, deep-links, customizer, history]
prerequisites:
  - "Reign or BuddyX active; {owner} has a listing"
estimated_runtime_minutes: 5
last_verified: 2026-11-08
verified_against: free 2.1.1
viewports: [1440]
themes: [reign, buddyx-pro]
site_profile: required
---

# Opening a dashboard tab link does not fire a click or rewrite the address

## Why
On load the dashboard clicked the nav link named by `?tab=` to show that tab. WordPress's Customizer preview listens to every link click, took it for navigation to a URL without the other query args, and reloaded the preview forever (preview iframe counter climbed from 8 to 26 in 12 s). The same click also rewrote the address bar and dropped `?action=edit&id=N`, so a refresh left the edit form.

## Steps
### 1. Customizer preview settles
- **Action**: open `$SITE_URL/wp-admin/customize.php?url=<urlencoded {dashboard}?listings_page=2&tab=overview&listings_status=pending>` as {admin}; poll `document.title` and `document.querySelectorAll('iframe').length` for 10 s.
- **Expect**: title becomes "Customize: Overview - ..." and stays; exactly one `customize-preview-0` iframe, never a second.

### 2. Load keeps the address
- **Action**: open `{dashboard}?tab=favorites&action=edit&id=123&listings_page=2` as {owner}; read `location.search` after 1.5 s.
- **Expect**: unchanged, and `#dash-panel-favorites` is visible.

### 3. A member's own click still syncs
- **Action**: click `#dash-tab-overview`.
- **Expect**: `location.search` is `?tab=overview&listings_page=2` (action and id dropped), Overview visible, Favorites hidden, document title starts "Overview".
