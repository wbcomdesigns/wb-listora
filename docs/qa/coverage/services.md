# Coverage: services

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Services CRUD, categories kept, category checkboxes in the wp-admin box, photos, flag | ora:services/listing-services-crud-and-photos.md | F A R | owner, other, guest, editor, admin | Y/Y | n | media frame steps need browser; foreign image on update may clear photo |
| Services modal presentation | ora:services/dashboard-services-modal.md | F | owner, other, admin, guest | both | not run | browser run |
| Service description toggle on the listing page | ora:services/service-details-toggle.md | frontend | guest, owner, other, admin | 1440+390 | light/dark | click unrun in a browser |
| Services can be switched off per listing type | ora:services/services-per-listing-type.md | admin, frontend, dashboard, REST | admin, guest, owner, other | 1440+390 | light/dark | edit/save flow unrun; lifecycle |
| Service hooks and booking_url extension point | pro:services/service-booking-cta.md | frontend, REST, hooks | guest, member, owner, editor, admin | both | both | owner decision on built-in Book CTA (expected-fail) |
