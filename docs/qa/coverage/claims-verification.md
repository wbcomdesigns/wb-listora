# Coverage: claims-verification

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Admin approves/reverses/rejects/deletes a claim | ora:claims-verification/approve-claim.md | admin, REST, email, leads | admin, editor, claimant, member, guest | partial | n/a | screen delete in a browser |
| Claim a business (CTA, login gate, proof file, one confirmation, approval) | ora:claims-verification/claim-a-business.md | frontend, REST, DB | guest, member, owner, editor, admin | partial | partial | browser upload + file URL refusal |
| Admin claims list and REST list share one model and count | ora:claims-verification/claims-list-and-count-agree.md | admin, REST | admin, member, guest, editor | partial | n/a | search/empty/1-row, 2000+ scale |
| Member Claims tab (status chips, pagination, REST scope, no member writes) | ora:claims-verification/dashboard-claims-tab.md | dashboard, REST | member, other, guest, admin | partial | partial | feature OFF, dark, filter; sidebar says "Claimed & Verified" for a claimed, unverified listing |
| Badge delete one path (Pro) | pro:claims-verification/badge-delete-one-path.md | REST, admin | admin, member, moderator, guest | n/a | n/a | screen delete browser |
| Member claims through the modal while Pro visibility gate is on | pro:claims-verification/claim-business-with-visibility-gate-on.md | frontend, REST | guest, member, owner, moderator, admin | partial | partial | Private/Coming Soon modes (other journeys) |
| Badges: create, assign, cap, order, list count truthful | pro:claims-verification/create-badge.md | admin, frontend card/detail, REST | admin, editor, owner, other, guest | 1440+390 | light/dark | plan-perk assignment; parallel save |
| Verification badge flag, surfaces and feature-off hiding (Pro) | pro:claims-verification/verification-badge.md | admin, REST, search, cards, Quick View | admin, moderator, owner, member, guest | partial | partial | search with feature OFF, dashboard chip |
