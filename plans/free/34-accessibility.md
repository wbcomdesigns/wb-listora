# 34 — Accessibility (WCAG 2.1 AA)

## Scope: Free + Pro

---

## Commitment

WB Listora targets **WCAG 2.1 Level AA** compliance. Directory plugins are public-facing tools — they must be usable by everyone.

---

## Key Requirements

### Perceivable

| Requirement | Implementation |
|-------------|---------------|
| Images have alt text | Listing featured image uses title as alt. Gallery images use filename or custom alt. |
| Color is not sole indicator | Status badges have text labels AND colors. Rating has numeric AND star display. |
| Sufficient contrast | All text meets 4.5:1 ratio. Interactive elements meet 3:1. Inherited from theme. |
| Text can be resized to 200% | All sizing in rem/em. No viewport-unit-only text. No overflow on zoom. |
| Content reflows at 320px | Single-column layout at narrow widths. No horizontal scroll. |

### Operable

| Requirement | Implementation |
|-------------|---------------|
| All interactive elements keyboard accessible | Tab order follows visual order. Focus visible on all elements. |
| No keyboard traps | Modals (lightbox, share, claim) have Escape to close and focus returns to trigger. |
| Skip links | "Skip to search results" before map block. "Skip to content" before navigation. |
| Sufficient time | No auto-advancing carousels (or pausable). Session timeouts warn before expiry. |
| Focus management | After AJAX search: focus moves to results count. After form submit: focus moves to confirmation. |

### Understandable

| Requirement | Implementation |
|-------------|---------------|
| Form labels | Every input has visible `<label>`. Required fields marked with `aria-required`. |
| Error identification | Inline errors linked via `aria-describedby`. Error list at form top with links to fields. |
| Consistent navigation | Tabs, pagination, sort controls behave consistently across all pages. |
| Language | `lang` attribute on multilingual content. Field labels translated. |

### Robust

| Requirement | Implementation |
|-------------|---------------|
| Valid HTML | All output passes W3C validation. |
| ARIA usage | Only ARIA when native HTML isn't sufficient. No ARIA roles that duplicate native semantics. |
| Screen reader testing | Tested with VoiceOver (Mac), NVDA (Windows). |

---

## Component-Specific A11y

### Star Rating Input
```html
<fieldset class="listora-rating-input">
  <legend>Your Rating</legend>
  <div role="radiogroup" aria-label="Rating">
    <label><input type="radio" name="rating" value="1"> <span aria-label="1 star">★</span></label>
    <label><input type="radio" name="rating" value="2"> <span aria-label="2 stars">★★</span></label>
    <label><input type="radio" name="rating" value="3"> <span aria-label="3 stars">★★★</span></label>
    <label><input type="radio" name="rating" value="4"> <span aria-label="4 stars">★★★★</span></label>
    <label><input type="radio" name="rating" value="5"> <span aria-label="5 stars">★★★★★</span></label>
  </div>
</fieldset>
```

### Map Block
```html
<div class="listora-map-wrapper">
  <a href="#listora-results" class="screen-reader-text">Skip map, go to listing results</a>
  <div
    class="listora-map"
    role="application"
    aria-label="Map showing 23 listing locations. Use search results below for keyboard-accessible listing browsing."
  >
    <!-- Map renders here -->
  </div>
</div>
```

### Search Results
```html
<div class="listora-results" aria-live="polite" aria-atomic="true">
  <p class="listora-results__count" role="status">
    Showing 20 of 156 results for "Italian restaurants in Manhattan"
  </p>
  <ul class="listora-grid" role="list">
    <li><article><!-- card --></article></li>
  </ul>
</div>
```

### Favorite Toggle
```html
<button
  class="listora-card__favorite"
  aria-label="Save Pizza Palace to favorites"
  aria-pressed="false"
  data-wp-on--click="actions.toggleFavorite"
  data-wp-bind--aria-pressed="state.isFavorited"
>
  <svg aria-hidden="true"><!-- heart --></svg>
</button>
```

### Tab Navigation
```html
<div class="listora-tabs">
  <div role="tablist" aria-label="Listing details">
    <button role="tab" id="tab-overview" aria-selected="true" aria-controls="panel-overview">
      Overview
    </button>
    <button role="tab" id="tab-reviews" aria-selected="false" aria-controls="panel-reviews">
      Reviews
    </button>
  </div>
  <div role="tabpanel" id="panel-overview" aria-labelledby="tab-overview">
    <!-- content -->
  </div>
  <div role="tabpanel" id="panel-reviews" aria-labelledby="tab-reviews" hidden>
    <!-- content -->
  </div>
</div>
```

Arrow keys navigate between tabs. Tab key moves into panel content.

---

## Testing Plan

### Automated
- `axe-core` integration in block editor tests
- Pa11y CI for generated HTML pages
- HTML validator in CI pipeline

### Manual
- VoiceOver (macOS Safari)
- NVDA (Windows Chrome/Firefox)
- Keyboard-only navigation test
- High contrast mode test
- 200% zoom test
- Screen magnifier test

### Checklist Per Component
- [ ] Keyboard navigable
- [ ] Screen reader announces correctly
- [ ] Focus visible
- [ ] Sufficient contrast
- [ ] No reliance on color alone
- [ ] Responsive at 320px
- [ ] Works at 200% zoom
