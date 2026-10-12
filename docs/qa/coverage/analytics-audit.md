# Coverage: analytics-audit

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Free view count: record once, show to owner/admin only | ora:analytics-audit/owner-sees-view-count.md | F, R, A | guest, owner, other, admin | Y | light/dark | timezone near midnight; admin Views column is hidden by default (Screen Options) |
| Analytics screen numbers, search report, retention | pro:analytics-audit/analytics-dashboard.md | A, R | admin, owner, other, editor, guest | both | not run | timezone matrix; 50k-row run |
| Analytics favourited / saved / zero-result totals | pro:analytics-audit/analytics-favourited-saved-zero-result.md | admin, REST | admin, owner, member, editor, guest | both | both | timezone matrix and scale not live |
| One recorded view per window when Pro owns analytics | pro:analytics-audit/analytics-no-double-count.md | R, F | guest, owner, other, admin | n/a | n/a | toggle OFF, parallel burst, timezone not live |
| Audit log screen, CSV, moderator clamp, retention, plain titles, IP spoof guard | pro:analytics-audit/audit-log.md | admin, REST, CSV, CLI | admin, other, editor, owner, guest | 1440+390 | light/dark | timezone retention matrix |
| Claim audit rows and webhooks on every path (Pro) | pro:analytics-audit/claim-audit-trail-both-paths.md | admin, REST, audit log, webhooks | admin, member, editor, guest | partial | n/a | bulk and webhook not verified live |
