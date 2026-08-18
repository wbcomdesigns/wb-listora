---
slug: dashboard-title-follows-tab
priority: normal
covers:
  - BC 10208510032
likely_files:
  - includes/class-plugin.php
  - includes/class-template-helpers.php
  - templates/blocks/user-dashboard/nav.php
  - src/blocks/user-dashboard/view.js
  - ../wb-listora-pro/includes/class-pro-plugin.php
---

# The dashboard titles itself by its active tab

The whole member dashboard is one page, and that page is usually called "My
Listings" — so Credits, Profile, Reviews and Claims all announced themselves
as My Listings in the browser tab, the history entry, the bookmark and to a
screen reader.

The fix has to be server-side: the tabs NAVIGATE (`?tab=…`), so a title set
only in JS is overwritten by the next page load and never applies at all to a
link opened directly or a restored session. JS still covers the in-page hash
switch.

## Steps

1. Visit the dashboard page with no `tab` param.
   - **Expect:** the page's own title, unchanged.
2. Visit `?tab=credits`, `?tab=profile`, `?tab=reviews`, `?tab=claims`,
   `?tab=favorites` — **as fresh page loads, not in-page clicks**.
   - **Expect:** each `<title>` names that tab.
   - **Fail if:** any reads "My Listings". Clicking through in one session can
     pass on the JS path alone while every one of these fails.
3. With Pro active, visit `?tab=saved-searches` and `?tab=analytics`.
   - **Expect:** "Saved Searches" and "Analytics".
   - **Fail if:** they read "My Listings" — Pro registers these through
     `wb_listora_dashboard_tab_labels`, and that registration must be on the
     always-run path. The dashboard is a FRONTEND page, so registering it in
     an admin-only init looks correct to an admin testing from wp-admin and
     is broken for every member.
4. Visit `?tab=nonsense`.
   - **Expect:** the page's own title. An unknown tab must not invent one.
5. Now click through the sidebar tabs without reloading.
   - **Expect:** the title updates, and carries NO count badge — "Credits",
     never "Credits 48.00" or "Saved Searches 5". The label is read from
     `data-listora-tab-label`, not from `textContent`.
6. Confirm the sidebar still renders every label (the template reads the same
   map, so a broken map would empty the sidebar, not just the title).
