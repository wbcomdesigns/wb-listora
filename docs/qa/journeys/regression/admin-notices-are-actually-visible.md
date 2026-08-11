---
journey: admin-notices-are-actually-visible
plugin: wb-listora
priority: critical
roles: [admin]
covers: [BC-10190572606, BC-10154189084, BC-10184284834, BC-10154198434, admin-notices, computed-style]
prerequisites:
  - "Site reachable at $SITE_URL with Free + Pro active"
  - "Auto-login mu-plugin present (?autologin=1)"
  - "For the Maps notice: map_provider = google with google_maps_key empty"
  - "For the licence notice: Pro running without an active licence"
estimated_runtime_minutes: 6
covers_card: 10190572606
---

# An admin notice must be VISIBLE, not merely present (BC 10190572606 sentinel)

`assets/css/admin.css` hides `.wb-listora-admin .notice:not(.listora-notice)` so
that third-party notices do not clutter Listora screens. Every `class="notice`
in this repo is one of **our** notices, so any that omits `listora-notice` hides
itself.

This is unrecoverable rather than cosmetic. Our notices are deliberately scoped
to Listora screens (`if ( 0 !== strpos( $page, 'listora' ) ) return;`), so **the
only screens they render on are the only screens that hide them.** There is no
page where an owner can see them.

## Why this journey asserts computed style

Three cards (10154189084, 10184284834, 10154198434) were each fixed correctly,
each signed off as "browser-verified", and each shipped invisible. The markup was
genuinely present every time. Measured live on 2026-08-11:

| Element state | `getComputedStyle().display` | `innerText` non-empty |
|---|---|---|
| with `listora-notice` | `block` | yes |
| without `listora-notice` | `none` | **yes** |

`innerText` returns the sentence in BOTH states, so reading the markup, grepping
the HTML, or calling `innerText` on an ancestor all report success while the
owner sees nothing. **Only `getComputedStyle` on the element itself catches it.**

Any journey that covers an admin notice must assert computed visibility. A
presence assertion is not a verification of this class.

## Steps

### 1. The Maps notice renders and is visible
- **Setup**: set `map_provider` to `google`, leave `google_maps_key` empty.
- **Action**: open `wp-admin/admin.php?page=listora-settings&tab=maps&autologin=1`.
- **Assert** (in the browser, not on the HTML source):

```js
[...document.querySelectorAll('.wb-listora-admin .notice')].map(el => ({
  cls: el.className,
  display: getComputedStyle(el).display,
  visibility: getComputedStyle(el).visibility,
}))
```

- **Expect**: every row reports `display` other than `none` and `visibility:
  visible`. The "OpenStreetMap is still live" notice must be among them.
- **Fails if**: any row shows `display: "none"` — that notice is unreachable for
  the owner on every screen it renders on.

### 2. The licence notice on the same screen
- **Expect**: with Pro unlicensed, "WB Listora Pro is running, but plugin updates
  are paused and the mobile app is switched off…" is present AND visible by the
  same measurement.

### 3. The counterfactual — proves the assertion has teeth
```js
const el = document.querySelector('.wb-listora-admin .notice.listora-notice');
const withClass = getComputedStyle(el).display;
el.classList.remove('listora-notice');
const without = getComputedStyle(el).display;
el.classList.add('listora-notice');
({ withClass, without });
```
- **Expect**: `{ withClass: "block", without: "none" }`. If `without` is not
  `"none"`, the suppression rule has changed and this journey needs rewriting.

### 4. Static rule — the class, not the instance
`bin/coding-rules-check.sh` Rule 10 fails the build if any `class="notice` in
`includes/`, `blocks/` or `templates/` omits `listora-notice`. Run it directly:

```bash
bash bin/coding-rules-check.sh   # Rule 10 must pass
```

Rule 10 was verified to FAIL on a deliberately regressed notice before landing,
so a green result is meaningful rather than vacuous.

## Note for Pro

Pro has no copy of this rule; its notices are covered because Rule 10 runs over
the Free tree only. Pro notice sites were swept by hand in the same commit
(`class-google-maps.php`, `class-pro-plugin.php`, `class-license.php`,
`class-feature-manager.php`, `class-business-details.php`, `class-badges.php`,
`class-coupons.php`, `class-setup-wizard.php`). If Pro grows a coding-rules
script, port Rule 10 into it.
