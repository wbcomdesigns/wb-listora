# v2 Primitive Layer

**Every visual pattern that 2+ blocks need lives in `src/primitives/` as a canonical class. Blocks compose; they don't re-implement.**

---

## Primitive index

| Primitive | File | API root class | Used by |
|---|---|---|---|
| Page shell | `page-shell.css` | `.listora-page` + `--single`/`--list`/`--dashboard`/`--booking` | wraps the customer-facing page templates |
| Card | `card.css` | `.listora-card` + `__head`/`__body`/`__foot`/`__media` | listing-card, dashboard tab rows, reviews, needs cards, comparison columns |
| Empty state | `empty-state.css` | `.listora-empty` + `__icon`/`__title`/`__desc`/`__actions` | every list block when results count = 0 |
| Form field | `form-field.css` | `.listora-form-field` + `__label`/`__input`/`__error`/`__hint` | submission wizard, review form, post-need, profile, all forms |
| Button | `button.css` | `.listora-btn` + `--primary`/`--secondary`/`--ghost`/`--danger` + `--sm`/`--lg`/`--icon` | every button anywhere |
| Badge | `badge.css` | `.listora-badge` + `--success`/`--warning`/`--danger`/`--info`/`--neutral`/`--premium` | status badges, claim status, review status, listing status |
| Modal | `modal.css` | `.listora-modal` + `__backdrop`/`__panel`/`__head`/`__body`/`__foot` | claim, share, login, listoraConfirm |
| Tabs | `tabs.css` | `.listora-tabs` + `__list`/`__tab`/`__panel` (with full ARIA wiring) | listing-detail tabs, user-dashboard nav |
| Stepper | `stepper.css` | `.listora-stepper` + `__step` + `--complete`/`--active` | submission wizard, post-need flow |
| Tooltip | `tooltip.css` | `.listora-tooltip` | icon buttons, help icons |
| Table | `table.css` | `.listora-table` | credit history, claims list, audit log |

---

## API examples

### `.listora-card`

The canonical card. Every "card of X" in the plugin extends this.

```css
.listora-card {
    background: var(--listora-bg-elevated);
    border: 1px solid var(--listora-border-default);
    border-radius: var(--listora-card-radius);
    box-shadow: var(--listora-card-shadow);
    padding: 0;
    overflow: hidden;
    transition: box-shadow var(--listora-transition-base);
}
.listora-card:hover { box-shadow: var(--listora-card-shadow-hover); }

.listora-card__media   { aspect-ratio: var(--listora-card-image-ratio, 16/10); overflow: hidden; }
.listora-card__head    { padding: var(--listora-space-4); display: flex; gap: var(--listora-space-3); align-items: flex-start; }
.listora-card__body    { padding: var(--listora-space-4); display: flex; flex-direction: column; gap: var(--listora-space-3); }
.listora-card__foot    { padding: var(--listora-space-4); border-top: 1px solid var(--listora-border-divider); }
.listora-card__title   { font-size: var(--listora-text-size-lg); font-weight: var(--listora-weight-semibold); color: var(--listora-fg-strong); margin: 0; }
.listora-card__meta    { font-size: var(--listora-text-size-sm); color: var(--listora-fg-muted); }

/* Variants */
.listora-card--clickable { cursor: pointer; }
.listora-card--horizontal { display: grid; grid-template-columns: 200px 1fr; }
.listora-card--compact .listora-card__head,
.listora-card--compact .listora-card__body { padding: var(--listora-space-3); }
.listora-card--featured { border-color: var(--listora-premium); }
.listora-card--empty { background: var(--listora-bg-muted); text-align: center; }
```

**Adopters refactor like this (listing-card.php example):**

```html
<!-- before (today) -->
<article class="listora-card">
  <div class="listora-card__image">...</div>
  <div class="listora-card__body">
    <h3 class="listora-card__title">...</h3>
  </div>
</article>

<!-- after (v2 primitives) -->
<article class="listora-card">
  <div class="listora-card__media">...</div>
  <div class="listora-card__body">
    <h3 class="listora-card__title">...</h3>
  </div>
</article>
```

Mostly mechanical — `__image` becomes `__media` (more accurate; aspect ratio applies to video too).

### `.listora-empty`

```css
.listora-empty { padding: var(--listora-space-12) var(--listora-space-6); text-align: center; }
.listora-empty__icon  { width: 64px; height: 64px; margin: 0 auto var(--listora-space-6); color: var(--listora-fg-faint); }
.listora-empty__title { font-size: var(--listora-text-size-xl); color: var(--listora-fg-default); margin: 0 0 var(--listora-space-3); }
.listora-empty__desc  { font-size: var(--listora-text-size-base); color: var(--listora-fg-muted); margin: 0 0 var(--listora-space-6); }
.listora-empty__actions { display: flex; gap: var(--listora-space-3); justify-content: center; }
```

PHP helper:

```php
wb_listora_render_empty_state( array(
    'icon'   => 'inbox',
    'title'  => __( 'No listings yet', 'wb-listora' ),
    'desc'   => __( 'Add your first listing to get started.', 'wb-listora' ),
    'cta'    => array( 'label' => __( 'Add Listing', 'wb-listora' ), 'url' => '/add-listing/' ),
) );
```

Replaces 5 different empty-state implementations across the plugin.

### `.listora-form-field`

```css
.listora-form-field { display: flex; flex-direction: column; gap: var(--listora-space-2); }
.listora-form-field__label { font-size: var(--listora-text-size-sm); font-weight: var(--listora-weight-medium); color: var(--listora-fg-strong); }
.listora-form-field__label[data-required]::after { content: " *"; color: var(--listora-danger); }
.listora-form-field__input,
.listora-form-field__select,
.listora-form-field__textarea {
    padding: var(--listora-form-control-padding) var(--listora-space-3);
    border: 1px solid var(--listora-border-default);
    border-radius: var(--listora-input-radius);
    font-size: var(--listora-text-size-base);
    color: var(--listora-fg-default);
    background: var(--listora-bg-base);
    transition: border-color var(--listora-transition-fast);
}
.listora-form-field__input:focus,
.listora-form-field__select:focus,
.listora-form-field__textarea:focus {
    border-color: var(--listora-primary);
    outline: none;
    box-shadow: var(--listora-focus-ring);
}
.listora-form-field--invalid .listora-form-field__input { border-color: var(--listora-danger); }
.listora-form-field__error { font-size: var(--listora-text-size-sm); color: var(--listora-fg-danger); }
.listora-form-field__hint  { font-size: var(--listora-text-size-sm); color: var(--listora-fg-muted); }
```

Adopters: submission wizard (~80 form fields), review form (5 fields), post-need (8 fields), profile (10 fields). Total ~100 form fields migrate from `.listora-submission__field`, `.listora-form__input`, etc. to canonical `.listora-form-field`.

### `.listora-modal`

```css
.listora-modal { position: fixed; inset: 0; z-index: 9999; }
.listora-modal__backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
.listora-modal__panel {
    position: relative;
    margin: 5vh auto;
    max-width: 600px;
    background: var(--listora-bg-elevated);
    border-radius: var(--listora-radius-lg);
    box-shadow: var(--listora-modal-shadow);
    overflow: hidden;
}
.listora-modal__head { padding: var(--listora-space-6); border-bottom: 1px solid var(--listora-border-divider); display: flex; align-items: center; justify-content: space-between; }
.listora-modal__body { padding: var(--listora-space-6); max-height: 70vh; overflow-y: auto; }
.listora-modal__foot { padding: var(--listora-space-6); border-top: 1px solid var(--listora-border-divider); display: flex; gap: var(--listora-space-3); justify-content: flex-end; }
```

Plus JS controller (`src/primitives/modal.js`):
- `role="dialog"` + `aria-modal="true"` on `.listora-modal__panel`
- Focus trap inside panel
- Esc + backdrop click close
- Focus returns to triggering element on close

Adopters: claim modal, share modal, login modal, listoraConfirm. All 4 currently roll their own — collapse into one primitive + 4 thin extension stylesheets.

### `.listora-tabs`

```css
.listora-tabs__list { display: flex; gap: var(--listora-space-2); border-bottom: 1px solid var(--listora-border-divider); padding: 0; margin: 0; list-style: none; }
.listora-tabs__tab {
    padding: var(--listora-space-3) var(--listora-space-4);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--listora-fg-muted);
    cursor: pointer;
    transition: color var(--listora-transition-fast), border-color var(--listora-transition-fast);
}
.listora-tabs__tab[aria-selected="true"] {
    color: var(--listora-fg-strong);
    border-bottom-color: var(--listora-primary);
}
.listora-tabs__panel { padding: var(--listora-space-6) 0; }
```

Plus PHP helper that emits the correct ARIA wiring:

```php
wb_listora_render_tabs( array(
    'tabs' => array(
        'overview' => array( 'label' => 'Overview',  'panel_id' => 'panel-overview' ),
        'reviews'  => array( 'label' => 'Reviews',   'panel_id' => 'panel-reviews', 'count' => 12 ),
        // ...
    ),
    'active' => 'overview',
) );
```

Outputs `role="tablist"`, `role="tab"`, `aria-selected`, `aria-controls` correctly every time. listing-detail tabs.php + user-dashboard nav.php both consume.

---

## What this kills

When this primitive layer ships, these block-local class systems disappear (consolidated into the primitive):

| Block-local class | Replaced by |
|---|---|
| `.listora-card__image` (listing-card) | `.listora-card__media` |
| `.listora-grid__card` (listing-grid) | `.listora-card` (uses card.css directly) |
| `.listora-categories__card` (listing-categories) | `.listora-card --compact` |
| `.listora-featured__card` (listing-featured) | `.listora-card --featured` |
| `.listora-submission__field` (submission wizard) | `.listora-form-field` |
| `.listora-submission__error` | `.listora-form-field__error` |
| `.listora-detail__claim-modal` (inline in render.php) | `.listora-modal[data-modal="claim"]` |
| `.listora-detail__tab` | `.listora-tabs__tab` |
| `.listora-dashboard__row` (tab-listings) | `.listora-card --horizontal` |
| `.listora-reviews__helpful-btn` vs `.listora-detail__helpful-btn` | `.listora-btn --ghost --sm` (single canonical) |

Estimated 30-50% reduction in per-block style.css line counts (listing-search 647 → ~350, user-dashboard 1641 → ~900, listing-detail 1194 → ~600).
