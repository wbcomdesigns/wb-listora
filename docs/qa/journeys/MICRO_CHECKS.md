# Micro checks: what an agent inspects on every surface a journey opens

A journey step proves one behaviour. These checks find the small defects around it. Run the whole list on **every surface the group manifest (`_group.md`) names**, once per cell of the matrix, and report each finding by its id (for example `P-07 at {dashboard}?tab=reviews, 390, dark`). Pro journeys use this same file.

**Matrix** (a surface is done only when every cell is done): viewport 1440, 1024, 390 x colour mode light, dark x theme `theme.primary`, `theme.unbridged` (from the site profile). Roles: every rung in the group's role ladder.

**How to measure.** Use a DOM query for anything that is a number (sizes, overflow, contrast, counts) and a scaled screenshot for anything that is a judgement (alignment, balance, clipping). Never report from a screenshot what a query can prove.

## Presentation

| id | Check | How |
|---|---|---|
| P-01 | No horizontal page scroll | `documentElement.scrollWidth <= clientWidth` at each viewport |
| P-02 | Nothing clipped, overlapping or cut by a parent | screenshot; query `scrollWidth > clientWidth` on text containers |
| P-03 | Long content survives: a 120-character title, a long unbroken word, a long email or URL, a 4-digit count | seed it, then P-01 and P-02 |
| P-04 | Empty, loading and error states each render a designed state, not a blank area or a raw message | force each one (no rows, throttled network, 500 or offline) |
| P-05 | Zero, one and many items look right (no orphan separator, no "1 listings", grid does not stretch one card) | N = 0, 1, >1 |
| P-06 | Spacing and alignment follow the grid: equal gutters, baselines line up, no doubled margin | screenshot at each viewport |
| P-07 | Tap targets are at least 40 x 40 px on 390 | bounding boxes of every link, button and input in the plugin's own markup |
| P-08 | Text contrast meets WCAG AA (4.5:1 body, 3:1 large text and icons) in light and dark | computed colours of text against its real background |
| P-09 | Dark mode has no light box, hard-coded hex colour, invisible icon or unreadable placeholder | toggle the theme's dark mode and emulate `prefers-color-scheme: dark` |
| P-10 | Hover, focus-visible, active, disabled, selected and visited states exist and differ on every control | hover and tab to each control; themes override these |
| P-11 | The plugin owns its look on an unbridged theme: buttons, inputs, links and headings inside plugin markup are styled by the plugin | run on `theme.unbridged` |
| P-12 | Icons and images: no broken image, empty `src`, missing alt, stretched ratio or layout shift on load | query `img` natural size and `alt`; watch the load |
| P-13 | Typography: one heading per level in order, no text below 12 px, no truncated label without a full-text fallback | query headings and computed font sizes |
| P-14 | Modals, drawers, popovers and menus fit the viewport, scroll inside, and close by button, Escape and outside click | open each at 390 and 1440 |
| P-15 | Sticky and fixed elements (action bars, headers, back-to-top, cookie bars) do not cover content or controls | scroll to the end at 390 |
| P-16 | RTL: the layout mirrors, nothing uses a physical left or right | switch the site language to an RTL locale when the group lists RTL |
| P-17 | Print and zoom: the page is usable at 200% zoom | browser zoom 200% at 1440 |
| P-18 | Copy: no placeholder text, raw key, untranslated string, double punctuation, wrong plural or brand name leak under white label | read every visible string |

## Functionality

| id | Check | How |
|---|---|---|
| F-01 | Every visible control does something: no dead button, `#` link or handler that throws | click each one; watch console and network |
| F-02 | Console is clean: no error, no unhandled rejection, no deprecation from plugin scripts | read console after load and after each action |
| F-03 | Network is clean: no 4xx or 5xx, no request repeated in a loop, no request to a wrong host | read network after load and after each action |
| F-04 | Every form field: empty, whitespace only, maximum length plus one, markup, quotes, emoji, negative, zero, wrong type | submit each; the error names the field and keeps the other values |
| F-05 | Double submit and fast repeat clicks create one record and charge once | double click; replay the request |
| F-06 | Reload, back and forward keep or restore state and never resubmit | after each write |
| F-07 | Deep link: the URL of any state (tab, page, filter, open dialog) opens that same state when pasted in a new tab | copy the URL, open it fresh, as guest and as member |
| F-08 | Tampered URL parameters are handled: a huge page number, a negative id, another member's id, an unknown tab | edit the query string |
| F-09 | The same action through every entry point gives the same result: page, block, shortcode, wp-admin, REST | repeat with the same fixture |
| F-10 | The role ladder holds: each rung sees and can do exactly what the group manifest says, and a refusal explains itself | repeat as each rung |
| F-11 | Keyboard only: every control is reachable in a sensible order, focus is visible, focus returns after a dialog closes | Tab, Shift+Tab, Enter, Space, Escape |
| F-12 | Assistive names: every icon-only control has an accessible name; status changes are announced | accessibility snapshot |
| F-13 | Without JavaScript the server-rendered page still shows content and core links work | disable JS, reload |
| F-14 | Slow or failed request: the control shows progress, cannot be re-fired, and recovers with a message | throttle, then block the request |
| F-15 | Two tabs or two members acting on the same record: the second gets a clear "already done" result | parallel sessions |
| F-16 | What the page shows equals what is stored: read the value back from the DB or REST after every write | `wp` or REST read-back |
| F-17 | Dates, times, money and counts follow the site timezone, currency and locale | repeat at UTC+14 and UTC-12 and a zero-decimal currency, then restore |
| F-18 | A setting changes behaviour, not just its saved value: flip it and observe the effect on every surface it governs | each state of each setting the group lists |

## Reporting a finding

One entry per finding: check id, group, journey and step (or "surface sweep"), surface URL with placeholders, viewport, mode, theme, role, what is shown, what is expected, the measured value, and a screenshot path under `~/work-artifacts`. A finding outside the group's scope is still reported; name the group it belongs to.
