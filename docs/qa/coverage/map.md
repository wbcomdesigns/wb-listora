# Coverage: map

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Location terms follow `_listora_address` from every write surface; also: Location terms follow address from wp-admin | ora:map/location-terms-follow-the-address.md | F, A, R, C, import | admin, owner, other, guest | both (n.v.) | n.v. | steps 8-9 need key/CSV; 10-12 n.v.; cross-area duplicate with map/location-terms-follow-the-address |
| One map provider and start coordinates on every map surface; Google only draws with Google live | ora:map/map-provider-single-engine-and-defaults.md | F, A, B | guest, owner, admin | both (n.v.) | n.v. | marker drag n.v.; Google half needs key |
| Map renders without fatals and plots exactly the grid's search (markers, popup, bounds, cap notice) | ora:map/map-renders-and-agrees-with-grid.md | F, B, R | all 5 | both (390 n.v.) | n.v. | drag path, 390, dark n.v. |
| OSM tile fallback preserved on upgrade | ora:map/map-tiles-survive-upgrade.md | C(eval), A | admin | n/a | n/a | real pre-1.6.0 upgrade n.v. |
| `wp listora repair-locations` | ora:map/repair-locations-command.md | C | admin | n/a | n/a | 100k batching gap (product) |
| Marker clustering setting vs block attribute | ora:map/map-clustering-site-default.md | admin, frontend, block, REST | admin, moderator, guest, member | both | n | steps 2-5, 7 not live |
| Near me radius, distance, unit | ora:map/near-me-radius-and-distance.md | frontend, admin settings, REST | guest, member, admin | both | both | unit iteration and scale (steps 3, 8) not live |
| Google vs OpenStreetMap provider | pro:map/google-maps-render.md | frontend, admin, REST | guest, member, editor, admin | both | both | Google render needs a key; popup image parity (expected-fail) |
