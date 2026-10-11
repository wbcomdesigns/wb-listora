# Styling Listora with your own CSS

Listora keeps its directory looking right on any theme. Since 1.9.0 it shields its blocks from theme rules (a theme's `button`, `a` or `input` styles no longer leak into search bars, cards and forms). Since 1.9.1 your own CSS still gets through, in three ways.

## 1. Colours, corners and spacing: set the Listora variables

Every Listora colour and size is a CSS variable. Set them on `:root` (setting them on `body` does not reach every element):

```css
:root {
	--listora-primary: #7b2cbf;      /* buttons, links, active chips */
	--listora-radius-xl: 8px;        /* card corners */
	--listora-bg-elevated: #fffaf0;  /* card background */
}
```

Listora already follows your theme's brand colour when the theme publishes one: theme.json `primary`, Astra, Kadence, Blocksy, BuddyBoss or an Elementor kit. BuddyX, BuddyX Pro and Reign have full colour bridges. Set `--listora-primary` only when you want a different colour.

From PHP (a theme, a child theme or a small plugin), map any theme's or page builder's own tokens without writing CSS:

```php
add_filter( 'wb_listora_css_variables', function ( $vars ) {
	$vars['--listora-primary'] = 'var(--my-builder-accent)';
	return $vars;
} );
```

### Optional knobs

A few variables are not set by Listora at all; each has a built-in default and exists only so a theme or site can tune one detail:

| Variable | What it changes |
|---|---|
| `--listora-button-fg`, `--listora-button-fg-hover` | Button text colour, normal and hover |
| `--listora-card-fav-size` | Size of the favourite button on cards |
| `--listora-field-w`, `--listora-field-w-sm` | Width of admin settings inputs |
| `--listora-settings-label-col` | Width of the admin settings label column |
| `--listora-font-mono` | Monospace font for codes and keys |
| `--listora-overlay` | Backdrop colour behind dialogs |
| `--listora-z-sticky` | Stacking order of sticky bars |
| `--listora-migration-progress` | Set by the importer to draw its progress bar |

Every other `--listora-*` name a stylesheet reads is declared by Listora; `bin/token-parity-check.py` fails the build when one is not.

## 2. Additional CSS (Appearance > Customize > Additional CSS)

Write ordinary rules. They apply to Listora as they did before 1.9.0:

```css
.listora-card { border-radius: 0; background: #ffeecc; }
.listora-card__title { font-size: 1.4rem; }
.listora-btn--primary { background: #000; }
```

Listora copies your Additional CSS into a cascade layer called `listora-overrides`, which it reads ahead of its own defaults. Your rules keep styling the rest of the theme exactly as before.

## 3. A child theme or a custom stylesheet

Wrap the rules that target Listora in the same layer:

```css
@layer listora-overrides {
	.listora-card { background: #e6f4ea; }
	.listora-card__title { font-size: 30px; }
}
```

Rules outside the wrapper still work everywhere except inside Listora blocks, where the shield blocks them. `!important` also works, but the wrapper is the cleaner way.

## Turning the shield off

If your site styles Listora completely by itself, switch the isolation off. Listora then behaves as it did in 1.8: your CSS and your theme's CSS both apply directly.

```php
add_filter( 'wb_listora_theme_isolation', '__return_false' );
```

With it off, theme rules on buttons, links and inputs reach Listora again, so check the search bar, the submission form and the dashboard on your theme.

## Upgrading from 1.8 or 1.9.0

Rules in Additional CSS work again in 1.9.1 with no change. For CSS that lives in a child theme or a plugin, wrap the Listora rules in `@layer listora-overrides { ... }`, or move colours to the variables above.
