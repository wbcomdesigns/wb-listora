# v2 Token System

**One CSS file per token category. Each token has exactly ONE definition. Blocks reference tokens; blocks NEVER define their own tokens.**

---

## `src/tokens/spacing.css`

**Decision: numeric scale wins.** `--listora-gap-*` is renamed/replaced by `--listora-space-{N}`. The 9-token numeric scale (`space-1, 2, 3, 4, 5, 6, 8, 10, 12`) covers every spacing need with predictable arithmetic.

```css
:root {
    /* Spacing scale — rem-based. Every spacing value in any block references one of these. */
    --listora-space-1:  0.25rem;   /* 4px */
    --listora-space-2:  0.5rem;    /* 8px */
    --listora-space-3:  0.75rem;   /* 12px */
    --listora-space-4:  1rem;      /* 16px */
    --listora-space-5:  1.25rem;   /* 20px */
    --listora-space-6:  1.5rem;    /* 24px */
    --listora-space-8:  2rem;      /* 32px */
    --listora-space-10: 2.5rem;    /* 40px */
    --listora-space-12: 3rem;      /* 48px */
    --listora-space-16: 4rem;      /* 64px */

    /* Semantic aliases for the most common slots */
    --listora-page-padding-block:    var(--listora-space-8);
    --listora-page-padding-inline:   var(--listora-space-4);
    --listora-grid-gap:              var(--listora-space-6);
    --listora-card-padding:          var(--listora-space-4);
    --listora-card-gap:              var(--listora-space-3);
    --listora-form-field-gap:        var(--listora-space-4);
    --listora-form-control-padding:  var(--listora-space-3);
}
```

**Migration from v1:** every `--listora-gap-xs/sm/md/lg/xl/2xl/3xl` maps deterministically:

| v1 (gap-X) | v2 (space-N) | Value |
|---|---|---|
| `--listora-gap-xs` | `--listora-space-1` | 0.25rem |
| `--listora-gap-sm` | `--listora-space-2` | 0.5rem |
| `--listora-gap-md` | `--listora-space-4` | 1rem |
| `--listora-gap-lg` | `--listora-space-6` | 1.5rem |
| `--listora-gap-xl` | `--listora-space-8` | 2rem |
| `--listora-gap-2xl` | `--listora-space-12` | 3rem |
| `--listora-gap-3xl` | `--listora-space-16` | 4rem (NEW — gap-3xl was 4rem, kept) |

Mechanical search-replace across the 249 v1 uses.

---

## `src/tokens/typography.css`

**Decision: separate axes.** Typography size and foreground color stop sharing the `--listora-text-*` namespace.

```css
:root {
    /* === Type size scale — rem-based. */
    --listora-text-size-xs:    0.75rem;    /* 12px */
    --listora-text-size-sm:    0.875rem;   /* 14px */
    --listora-text-size-base:  1rem;       /* 16px */
    --listora-text-size-lg:    1.125rem;   /* 18px */
    --listora-text-size-xl:    1.25rem;    /* 20px */
    --listora-text-size-2xl:   1.5rem;     /* 24px */
    --listora-text-size-3xl:   1.875rem;   /* 30px */
    --listora-text-size-4xl:   2.25rem;    /* 36px */

    /* === Line heights */
    --listora-line-tight:   1.25;
    --listora-line-snug:    1.375;
    --listora-line-base:    1.5;
    --listora-line-relaxed: 1.625;

    /* === Font weights */
    --listora-weight-normal:    400;
    --listora-weight-medium:    500;
    --listora-weight-semibold:  600;
    --listora-weight-bold:      700;

    /* === Foreground (text) colors — semantic */
    --listora-fg-default:   var(--wp--preset--color--contrast, #1a1a1a);
    --listora-fg-strong:    #1a1a1a;
    --listora-fg-muted:     var(--wp--preset--color--contrast-2, #555);
    --listora-fg-faint:     var(--wp--preset--color--contrast-3, #999);
    --listora-fg-inverse:   #ffffff;
    --listora-fg-accent:    var(--listora-primary);
    --listora-fg-danger:    var(--listora-danger);
    --listora-fg-success:   var(--listora-success);
    --listora-fg-warning:   var(--listora-warning);
    --listora-fg-link:      var(--listora-primary);
    --listora-fg-link-hover: var(--listora-primary-hover);
}
```

**Migration from v1:**
- `--listora-text-{xs,sm,base,lg,xl,2xl,3xl}` (size) → `--listora-text-size-{xs..3xl}` PLUS the new `4xl`. 130 mechanical replacements.
- `--listora-text` (color) → `--listora-fg-default`
- `--listora-text-secondary` → `--listora-fg-muted`
- `--listora-text-muted` → `--listora-fg-faint` (note: muted shifts meaning toward "secondary, low contrast")
- `--listora-text-strong` → `--listora-fg-strong`
- `--listora-text-faint` → `--listora-fg-faint`
- `--listora-text-inverse` → `--listora-fg-inverse`
- `--listora-font-size-*` → `--listora-text-size-*` (the dead alternate scale gets renamed and reused)

---

## `src/tokens/colors.css`

```css
:root {
    /* === Brand */
    --listora-primary:        var(--wp--preset--color--primary, #2271b1);
    --listora-primary-hover:  #135e96;
    --listora-primary-fg:     #ffffff;  /* foreground on primary bg */

    /* === Semantic status — base / bg / fg triplet for each */
    --listora-success:        #16a34a;
    --listora-success-bg:     #ecfdf5;
    --listora-success-fg:     #15803d;

    --listora-warning:        #d97706;
    --listora-warning-bg:     #fef3c7;
    --listora-warning-fg:     #b45309;

    --listora-danger:         #dc2626;
    --listora-danger-bg:      #fef2f2;
    --listora-danger-fg:      #b91c1c;

    --listora-info:           #2563eb;
    --listora-info-bg:        #eff6ff;
    --listora-info-fg:        #1d4ed8;

    --listora-premium:        #7c3aed;
    --listora-premium-bg:     #f3e8ff;
    --listora-premium-fg:     #6d28d9;

    --listora-favorite:       var(--wp--preset--color--vivid-red, #cf2e2e);
    --listora-rating:         var(--wp--preset--color--luminous-vivid-amber, #f5a623);

    /* === Surface (backgrounds) */
    --listora-bg-base:        var(--wp--preset--color--base, #fff);
    --listora-bg-surface:     color-mix(in srgb, var(--listora-bg-base) 95%, var(--listora-fg-default));
    --listora-bg-elevated:    #ffffff;
    --listora-bg-muted:       var(--wp--preset--color--contrast-4, #f5f5f5);
    --listora-bg-inverse:     #1a1a1a;

    /* === Border */
    --listora-border-default: var(--wp--preset--color--contrast-3, #e0e0e0);
    --listora-border-subtle:  #e2e8f0;
    --listora-border-strong:  #c3c4c7;
    --listora-border-divider: #f0f0f0;
    --listora-border-focus:   var(--listora-primary);
}
```

**Migration: `--listora-error*` aliases removed.** All callers use `--listora-danger*`.

---

## `src/tokens/radius.css`

```css
:root {
    --listora-radius-sm:   6px;
    --listora-radius-md:   10px;
    --listora-radius-lg:   14px;
    --listora-radius-xl:   20px;
    --listora-radius-full: 9999px;

    /* Semantic */
    --listora-card-radius:   var(--listora-radius-md);
    --listora-input-radius:  var(--listora-radius-sm);
    --listora-button-radius: var(--listora-radius-sm);
    --listora-pill-radius:   var(--listora-radius-full);
}
```

---

## `src/tokens/shadow.css`

```css
:root {
    --listora-shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
    --listora-shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --listora-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -2px rgba(0,0,0,0.05);
    --listora-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.04);
    --listora-shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.08), 0 8px 10px -6px rgba(0,0,0,0.04);

    --listora-focus-ring: 0 0 0 2px var(--listora-bg-base), 0 0 0 4px var(--listora-primary);

    /* Semantic */
    --listora-card-shadow:       var(--listora-shadow-sm);
    --listora-card-shadow-hover: var(--listora-shadow-lg);
    --listora-modal-shadow:      var(--listora-shadow-xl);
}
```

---

## `src/tokens/motion.css`

```css
:root {
    --listora-transition-fast: 0.1s ease;
    --listora-transition-base: 0.2s ease;
    --listora-transition-slow: 0.35s cubic-bezier(0.4, 0, 0.2, 1);

    /* Easing curves */
    --listora-ease-out:      cubic-bezier(0, 0, 0.2, 1);
    --listora-ease-in-out:   cubic-bezier(0.4, 0, 0.2, 1);
}

@media (prefers-reduced-motion: reduce) {
    :root {
        --listora-transition-fast: 0.01ms ease;
        --listora-transition-base: 0.01ms ease;
        --listora-transition-slow: 0.01ms ease;
    }
}
```

---

## `src/tokens/index.css`

```css
@import "./colors.css";
@import "./spacing.css";
@import "./typography.css";
@import "./radius.css";
@import "./shadow.css";
@import "./motion.css";
```

The build pipeline concatenates these into the shipped `assets/css/listora-tokens.css` (enqueued before every other plugin CSS).

---

## Net change

| Tokens removed | Tokens added | Tokens renamed |
|---|---|---|
| `--listora-gap-xs..3xl` (7) | `--listora-space-16` (1 new for 4rem) | `--listora-text-{xs..3xl}` → `--listora-text-size-{xs..3xl}` (130 callsites) |
| `--listora-font-size-{xs..4xl}` (8 — dead) | `--listora-text-size-4xl` (replacing dead font-size-4xl) | `--listora-text` → `--listora-fg-default` |
| `--listora-error*` (3) | `--listora-fg-*` semantic foreground set (10) | `--listora-text-secondary` → `--listora-fg-muted` |
| `--listora-card-*` semantic aliases | (kept, reference new tokens) | `--listora-text-muted` → `--listora-fg-faint` |

**Net file size change:** shared.css `:root` block goes from ~120 lines (with duplicates) to ~80 lines across 6 small files. More organized, fewer total lines.
