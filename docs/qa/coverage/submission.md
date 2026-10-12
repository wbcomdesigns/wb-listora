# Coverage: submission

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Business hours: multiple ranges, picker, Firefox, theming, schema, preview, Places import | ora:submission/business-hours-multiple-ranges.md | F A R | owner, guest, admin | Y/Y | Y | builder add/remove and Firefox need a browser |
| Owner edit form, edit permission, staff edit, media kept on edit, empty-title | ora:submission/edit-my-listing.md | F R | owner, other, editor, admin, guest | n/n | n | 2 expected-fail steps (non-owner blank wizard, empty title saved) |
| Owner opens own unpublished listing, 404 for others, expired notice | ora:submission/owner-opens-own-unpublished-listing.md | F R | owner, other, guest, admin | Y/Y | n | trashed/deleted-author step added unrun |
| Field options, price, contact fields render, save, prefill | ora:submission/submission-field-values-round-trip.md | F A R | owner, guest, admin | Y/n | n | boundary price step 13 added unrun; Type Editor UI not run |
| Save Draft never limited, id carried, terms matrix, Buy Credits keeps draft, off-site return refused | ora:submission/submission-save-draft-and-resume.md | F R | owner, other, guest, admin | Y/Y | n | browser steps and WooCommerce return not run |
| Optional category, no empty fieldset, fieldset width, upload limit follows server | ora:submission/submission-step-rendering-invariants.md | F A | subscriber, guest, admin, no-cap | Y/Y | n | upload run needs large files; Event schedule 820/390 not measured |
| Wizard walk, required messages, terms gate, success text per moderation, category tree, reload keeps data, social gate, term id refused | ora:submission/submit-a-listing-wizard-end-to-end.md | F R | subscriber, contributor, editor, admin, guest, no-cap | Y/Y | n | dark/unknown theme not run; Free-only wizard not walked |
| Edit permission one rule (form, POST, PUT) | ora:submission/edit-permission-same-on-form-and-rest.md | F, R | admin, editor, owner, other, guest | 1440 | light | boundary, lifecycle seeds |
| A guest must sign in to submit; legacy unverified listings still verify | ora:submission/sign-in-required-to-submit.md | F, R, E | guest, owner, nocap, admin | 390 not run | not run | sign-in round trip, resend cooldown, admin Mark verified, layout sweep |
| Success card links to the registered Dashboard URL | ora:submission/submission-dashboard-url-registry.md | F | owner, other, admin, guest | both | not run | click-through unrun |
| Featured image drag and drop in submission | ora:submission/submission-featured-image-drag-drop.md | frontend, REST | owner, guest, no-upload member, admin | both | both | real upload, retry, edit path not live |
| Submission form style setting (wizard vs single page) | ora:submission/submission-form-style-setting.md | admin, frontend | admin, owner, guest, editor | 1440+390 | light/dark | steps 3-7 unrun |
| Unticking a toggle on the frontend edit form saves | ora:submission/toggle-untick-persists-frontend-edit.md | frontend, REST | owner, other, guest, admin | 1440+390 | light/dark | form-level steps unrun |
| No-permission member gets a reason | ora:submission/add-listing-no-permission-message.md | F, R | no-cap-member, owner, guest, admin | 1440/390 | light/dark | 390 and dark not verified in a browser |
| Frontend edit keeps description markup | ora:submission/frontend-edit-keeps-description-markup.md | F, R, import | owner, other, guest, editor, admin | both (n.v.) | n.v. | texturize + importer paths n.v. |
| Address search picker + confined Leaflet stacking | ora:submission/submission-map-picker.md | F, A | owner, guest, admin | both (n.v.) | n.v. | browser run, RTL, fixed-header theme n.v. |
| Editing keeps categories the form cannot show | ora:submission/submission-preserves-multi-category.md | R, F | owner, other, guest, admin | 1440/390 | light/dark | steps 5-8 not run |
| Submit REST refused when Submission feature is off | ora:submission/submission-rest-feature-gate.md | R, F | admin, owner, editor, guest | 1440/390 | light/dark | steps 4, 5, 7 not live |
| Stepper fits phones | ora:submission/submission-stepper-fits-phones.md | F | owner, guest, admin | 390/360 | light/dark | steps 2-6 not run |
| Success card centred | ora:submission/submission-success-card-centered.md | F | owner, guest, admin | both (1440 n.v.) | n.v. | real submission n.v. |
| Terms of Service acceptance enforced; single-form steps never `hidden` | ora:submission/submission-terms-acceptance-enforced.md | F, R | owner, other, guest, editor, admin | both (n.v.) | n.v. | wizard/browser steps n.v. |
| Featured-image upload operable by keyboard | ora:submission/submission-upload-keyboard-access.md | F | owner, guest, admin | 1440/390 | light/dark | edit-mode markup and retry not run |
| URL-shaped title refused | ora:submission/title-is-not-a-url.md | R, F | owner, other, guest, admin | 1440/390 | light/dark | steps 3-6 not run |
