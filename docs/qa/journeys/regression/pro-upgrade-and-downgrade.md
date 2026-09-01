---
journey: pro-upgrade-and-downgrade
plugin: wb-listora
priority: critical
roles: [admin, anonymous]
covers: []
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Free active; Pro available to activate"
estimated_runtime_minutes: 20
---

# Activating Pro changes nothing you did not ask for, and turning it off does not leave blank pages

Both plugins are ours, so both directions are ours to get right. Most sites run
Free first and add Pro later; many never use most of Pro. Neither fact was
handled.

**Upgrade.** Fourteen Pro features are on by default and four of them want a
page. Activation therefore published four pages onto a live site — into its
menus, its sitemap, its page list — for an owner who bought Pro for one feature
and asked for none of them.

**Downgrade.** Switch a feature off, or deactivate Pro, and its pages stay
published while their blocks render nothing. Measured on a real downgrade: four
pages, HTTP 200, **zero characters** inside the content area. A visitor reads
that as a broken site and a search engine indexes it.

## Steps

### 1. Activating Pro creates nothing

- **Setup**: a site with Pro's features ON but its pages absent — the state a
  long-running Free site is in the moment Pro is activated. (Set the toggles
  first, then delete the pages, so no OFF → ON transition fires.)
- **Action**: activate Pro. Load a few admin pages.
- **Expect**: **no pages created.** Nothing in Pages, nothing in menus.
- **On fail**: anything auto-published here is the defect. A deliberate OFF → ON
  toggle SHOULD create — that is the owner asking — but activation is not.

### 2. It offers instead, once, and takes no for an answer

- **Expect**: on Listora admin screens only, a notice naming the missing pages
  and a **Create N pages** button, plus **No thanks**.
- **Action**: press Create.
- **Expect**: all of them created and mapped, the notice gone.
- **Action** (separate run): press **No thanks**.
- **Expect**: it does not come back — for that user, permanently. Not a
  transient that re-nags next week.

### 3. NEGATIVE — the notice must be visible, not merely present

This step exists because it failed. The notice rendered correctly, sat in the
DOM, and could not be seen by anyone.

- **Action**: on Listora > Settings, measure the notice:
  ```js
  const n = [...document.querySelectorAll('.notice')].find(x => x.innerText.includes('nowhere to send'));
  n.getBoundingClientRect().height   // must be > 0
  ```
- **Expect**: a real height, exactly one `.listora-admin-header`, exactly one
  `h1`, exactly one `.wp-header-end`.
- **On fail**: WordPress relocates notices to just after the first `h1` in
  `.wrap`, or after `.wp-header-end`. Listora screens had neither — the page
  title was a `<p>` — so core's JS dropped notices into whatever it found, on
  Settings an inactive tab pane with `display: none`. Two headers is the
  related bug: Settings opts out of the auto-injected header, but added the
  filter inside its render method, which runs long after `in_admin_header`
  where the injection happens.

### 4. A feature switched off returns 404, not a blank page

- **Action**: with Pro active, switch Reverse Listings OFF. Request `/needs/`
  and `/post-need/` **logged out**.
- **Expect**: **404**.
- **On fail**: a 200 with an empty content area is the original defect.

### 5. NEGATIVE — a page the owner wrote on stays up

The guard must never take down a page that still has something to say.

- **Action**: add a paragraph of your own above the block on the Compare page,
  then switch Comparison off. Request it logged out.
- **Expect**: **200**, showing your paragraph.
- **On fail**: 404 here means the guard is judging the page by its feature
  rather than by whether it has content. That is a live page taken off a
  customer's site, which is worse than the bug being fixed.

### 6. NEGATIVE — an editor can still reach it

- **Action**: request the same 404ing page while logged in as someone who can
  edit it.
- **Expect**: the page renders, with the admin-bar Edit link.
- **On fail**: hiding it from the only person who can fix it makes the state
  undiagnosable.

### 7. Settings says what happened

- **Action**: Listora > Settings > General > Pages.
- **Expect**: those rows read **Feature off**, with a line explaining the page
  returns 404 and what to do — turn the feature back on, or delete the page.
- **On fail**: a page that looks Linked here while being gone from the site is
  how this stays a mystery.

### 8. Deactivating Pro entirely behaves the same

- **Action**: deactivate Pro. Request all four pages logged out.
- **Expect**: 404 for the bare ones, 200 for the one carrying the owner's
  paragraph, and **Free's three pages completely unaffected**.
- **Note**: with Pro gone its keys are not registered, so the page is
  identified from the meta stamp, the created-pages ledger, or the live
  mappings — whichever the site has. A site whose pages predate the stamp is
  covered by the other two.

### 9. All of it is reversible

- **Action**: reactivate Pro / switch the features back on.
- **Expect**: every page 200 again, **same IDs**, no duplicates. Nothing about
  the downgrade is destructive — no page is deleted, no mapping dropped.

## Notes

- `wb_listora_hide_unavailable_pages` disables the 404 behaviour entirely.
- `is_available` is a per-page-key callback in the registration; Pro's four
  pages each return their feature toggle.
- **Do not test the notice by reading the HTML.** Step 3 passed every
  server-side check while being invisible. Measure the rendered height.
