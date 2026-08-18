# Frontend CSS Architecture & Standard

> **Base boundaries.** This document is the WB Listora-specific instance of the
> portfolio standards. The authority is:
> - **`/wp-plugin-development`** — Part 7 (frontend design system), 7.7 (no inline
>   `<style>`), 8.0 (no inline `<script>`), escaping, enqueue mechanics.
> - **`/ux-audit`** — the compliance/drift detector + cleanup playbook.
> - **`ux-foundation`** — the canonical token/component/responsive/a11y rules.
>
> When this doc and a skill disagree, the skill wins — fix this doc.

Applies to **both** wb-listora (Free) and wb-listora-pro (Pro). Pro ships no
`docs/` of its own (upscale model); this is the shared standard.

---

## 1. The layer cascade (single source of truth)

```
variables  →  components  →  base  →  block-specific
(tokens)      (button,        (page    (per-block
              card, modal,    shell,   layout in
              badge, …)       resets)  blocks/*/style.css)
```

| Layer | Source | Served file | Edited how |
|---|---|---|---|
| **Variables** (design tokens) | `src/variables/*.css` (manifest: `index.css`) | `assets/css/listora-variables.css` | **edit `src/`, run build** |
| **Components** (reusable UI vocabulary) | `src/components/*.css` (manifest: `index.css`) | `assets/css/listora-components.css` | **edit `src/`, run build** |
| **Base** (resets, theme-defence, layout) | `assets/css/listora-base.css` | same (directly served) | edit in place |
| **Block-specific** | `blocks/*/style.css` | same (directly served, via `block.json`) | edit in place |

Enqueue dependency chain (in `includes/class-assets.php`):
`listora-variables → listora-components → listora-base → block style.css`.
Pro consumes Free's handles by name (`listora-base`, etc.) — never redefines tokens.

### Build pipeline (the anti-drift rule)

`assets/css/listora-{variables,components}.css` are **GENERATED** by
`bin/build-css.mjs` (no dependencies; resolves the `src/*/index.css` `@import`
manifests). The header of each file says so.

```bash
npm run build       # webpack (JS) + build:css
npm run build:css   # CSS only — regenerate the two compiled files
```

**Never hand-edit the compiled files.** Editing `src/` + rebuilding is the only
correct path. This is enforced — see §3 Rule 4. (The entire May 2026 CSS
cleanup existed because the compiled files had been hand-edited out of sync
with source. The build pipeline + drift guard make that impossible now.)

---

## 2. Theme-independence (no `!important`, no `wp-element-button`)

Listora blocks render **outside `.entry-content`** (custom templates), so
themes' aggressive `.entry-content a:not(...)` anchor resets never reach our
components, and our own themes (BuddyX / BuddyX Pro / Reign) do not put
`!important` on buttons. Therefore:

- **Buttons / components** win cleanly via **doubled-class specificity**
  (`.listora-btn.listora-btn--primary`, 0,2,0) — context-independent, so it
  holds in block wrappers, the `wp_footer` Quick View modal, and Leaflet
  popups alike. **No `!important`. No `wp-element-button`.**
- **Content links inside blocks** are neutralized by the block-ancestor
  selector in `src/shared/theme-isolation.css`
  (`[class*="wp-block-listora"] a:not(...)`, 0,5,1) — specificity only, no
  `!important`. Pagination/button anchors are excluded from that reset.
- **Aggressive Wbcom themes** are reconciled in their own bridge files
  (`assets/css/themes/{slug}.css`, e.g. token mapping), never via plugin-side
  `!important`.

### Legitimate `!important` (kept by design — do NOT strip)

- `@media (prefers-reduced-motion: reduce)` resets (industry-standard a11y).
- `.listora-hide-*` / `display: none` hide-utilities (must beat any display).
- Leaflet 3rd-party overrides (the library injects inline styles; only
  `!important` or not-matching beats them).
- Modal `display: none` show/hide state (Quick View).

---

## 3. Enforced rules (`bin/coding-rules-check.sh`, runs in `composer ci`)

| Rule | Enforces | Exceptions |
|---|---|---|
| **1** | No native `current_user_can('wb_listora/…')` outside Permission_Engine | — |
| **2** | REST `__return_true` only on the documented public-controller allowlist | see allowlist |
| **3** | The clean CSS layer (`button.css`, `theme-hardening.css`; Pro: `pro-frontend.css`, comparison, needs-grid) stays **`!important`-free** | quick-view modal `display:none` |
| **4** (Free) | Compiled CSS matches a fresh build from `src/` — **drift guard** (`build-css.mjs --check`) | — |
| **5** | No `wp-element-button` in markup (theme-coupling hack) | `:not(.wp-element-button)` in theme-isolation CSS |
| **6** | No inline `<style>`/`<script>` in PHP | email templates, coming-soon + email-verification pre-bootstrap splash pages, `application/ld+json` (structured data), `Block_CSS`, `wp_add_inline_*`, code comments |

Run: `composer coding-rules` (fast) or `composer ci` (full gate).

---

## 4. Dynamic values (no inline `style=`)

| Value kind | Pattern | Example |
|---|---|---|
| **Finite set** (listing-type colors) | Generate per-value classes server-side via `wp_add_inline_style` | `.listora-type--{slug}{--listora-type-color:…}` (see `Assets::build_type_color_css()`) |
| **Per-block config** (columns, height) | `Block_CSS::render()` scoped `<style>` for that block instance | grid column count |
| **Per-instance continuous data** (rating-bar %, stagger index, event color) | A CSS custom property is acceptable — it is *data*, not styling (the `ux-foundation` "dynamic CSS variable" exception) | `style="--card-index: 3"` |
| **HTML email** | Inline styles required (Gmail/Outlook strip `<link>`) | `templates/emails/*` |

Static styling never goes inline — it lives in a CSS class.

---

## 5. Audits

- **Per-PR (automated):** `composer ci` runs the 6 coding-rules above + WPCS +
  PHPStan + architecture invariants.
- **Periodic (broader):** run `/ux-audit` (the skill ships
  `~/.claude/skills/ux-audit/templates/ux-audit.sh`) for a11y, breakpoint
  count, `outline:none` / `:focus-visible`, RTL logical-property, tap-target,
  and Lucide-icon checks. Known acceptable flags: the documented splash-page
  `<style>`/`<script>`, the dashicon→Lucide mapping table, and WP admin
  `menu_icon` dashicons.

---

## 6. Adding new frontend UI (day-1 checklist)

1. Compose from existing **components** (`.listora-btn`, `.listora-card`,
   `.listora-badge`, …) — don't reinvent. Need a new primitive? Add it to
   `src/components/` + `index.css`, then `npm run build:css`.
2. Tokens only — no raw hex/px for color/spacing; use `--listora-*` variables.
3. No `!important`, no `wp-element-button`, no inline `<style>`/`<script>`,
   no inline static `style=` (see §2–4).
4. Logical properties (`margin-inline-start`, not `margin-left`) for RTL.
5. Exactly two `@media` blocks (`≤1024px`, `≤640px`) at the file bottom.
6. `composer coding-rules` green before commit; `composer ci` before push.

## 7. Brand colour vs contrast

A brand colour is chosen to stand out on a surface, not to be legible as 11px
text on one. Listora bridges the site's brand into `--listora-primary`, and
that value belongs to the owner — the plugin does not get to overrule it.

**Two tokens, two jobs:**

| Token | Use for |
|---|---|
| `--listora-primary` | Backgrounds, borders, large display type. A 3:1 floor applies. |
| `--listora-primary-text` | Brand-coloured **text**. A darkened derivation that clears 4.5:1 for whatever brand the owner picked, and is close to a no-op on brands already dark. |

Never use `--listora-primary` for small text. BuddyX's default `#ee4036`
measures 3.62:1 on white, and worse on the tinted washes these labels sit on —
a 12-18% wash lifts the background toward the text, so a count badge measured
2.71:1 while the same colour on plain white measured 3.62:1. Always measure
against the **composited** backdrop, never an assumed white one.

Likewise do not bridge `--listora-fg-muted` to a theme's muted or tagline
colour. Those are tuned to recede against the theme's own backgrounds; Listora
paints its own and uses fg-muted for real information — tab labels, metadata,
status. Both BuddyX bridges keep Listora's value for this reason.

**Deliberate exception: white text on a brand BACKGROUND.** Primary buttons,
badge pills and active pagination measure 3.87:1 with BuddyX's default accent
and stay that way. Darkening the background would reach AA but would stop
Listora's buttons matching the theme's own, which use the same colour and fail
identically — fixing our audit at the cost of making every install look
inconsistent, over a colour the owner chose. Owners who must pass an audit opt
in with one line:

```css
:root { --listora-button-bg: var(--listora-primary-text); }
```

Filled buttons read `--listora-button-bg` / `--listora-button-fg`, falling back
to the brand, so that override is real rather than aspirational. They used to
hardcode `--listora-primary`, which broke this opt-in AND silently ignored the
theme bridges — BuddyX maps the theme's own button palette onto those tokens
precisely so Listora's buttons match the theme's buttons, and that mapping had
no effect. The winning declaration is
`.listora-btn.listora-btn--primary` (0,2,0) in the components layer; the
(0,1,0) rule in `listora-base.css` loses to it regardless of load order.

Decision taken 2026-08-18 (BC 10208336512). Do not silently reverse it.
