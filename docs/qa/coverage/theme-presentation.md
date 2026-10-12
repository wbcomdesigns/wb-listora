# Coverage: theme-presentation

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| RTL stylesheet twins | ora:theme-presentation/rtl-twin-only-claimed-when-it-exists.md | frontend assets | guest | partial | partial | needs RTL language pack |
| Styles linked, not inline @import | ora:theme-presentation/styles-linked-not-inline-imported.md | F | guest, owner, admin | Y | light/dark | cascade-hash step, CSS lint live |
| No wp-element-button on customer surfaces | pro:theme-presentation/no-wp-element-button-customer-facing.md | F | guest, other, owner, admin | 1440/390 | light/dark | Free contact form Send button expected-fail; 390/dark not run |
| Pro empty states use the shared component | pro:theme-presentation/pro-empty-states-shared-component.md | frontend | guest, member, moderator, owner, admin | both | both | never run live; 8 more Pro empty-state call sites untested |
| Block stylesheets versioned | theme-presentation/block-styles-versioned.md | F | guest, owner, admin | n/a | n/a | needs two release builds for cache bump |
| Brand text meets AA contrast | theme-presentation/brand-text-meets-contrast.md | F | guest, owner | 1440/390 | light/dark | values computed, not measured composited |
| Listing modals trap focus | theme-presentation/modal-traps-focus.md | F | guest, other, owner, admin | 1440/390 | light/dark | Report-review modal expected-fail; 390/dark not run |
| Native pickers follow colour mode | theme-presentation/native-controls-color-scheme.md | F | owner, guest, admin | 1440/390 | light/dark | open native popup manual only |
| Every overlay opens and is usable at 1280/390 | theme-presentation/overlays-usable-at-every-width.md | F, A | guest, owner, other, admin | 1280 + 390 | both (n.v.) | all width tests n.v. |
| Site CSS reaches Listora; themes stay out; theme colour; opt-out | theme-presentation/site-css-and-layer-overrides.md | F | admin, guest, owner | 1440/390 | light/dark | three-theme run and token parity not live |
| --sm buttons >= 40px, row height parity | theme-presentation/sm-button-tap-target.md | F | guest, owner, admin | 390/820/1440 | light/dark | measurements not run in a browser |
