# Coverage: plans-expiry-renewal

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Expiry off on free directory, dated types, new types, own switch | ora:plans-expiry-renewal/expiry-off-on-free-directory.md | A F R | admin, owner, other, guest | Y/Y | n | timezone and republish steps unrun |
| Owner renews, quote, modal live region, duration rules, failed renewal, expiry-off 409 | ora:plans-expiry-renewal/listing-renewal.md | F R E | owner, other, guest, admin | Y/Y | n | timezone half unrun; renew_buy link expected-fail |
| Awaiting-credits listing: route forward, resume on top-up/webhook | pro:plans-expiry-renewal/awaiting-credits-listing-route-forward.md | frontend dashboard, REST, webhook | member, other, guest, admin | 1440/390 | yes | 390/dark by DOM only; expected-fail items below |
| Monetization on turns expiry on, off keeps dates | pro:plans-expiry-renewal/monetization-expiry-follows-toggle.md | A F R | admin, owner, other, guest, editor | Y/Y | n | mobile/dark unrun |
| Monetization status one answer; Direct pack is a pack | pro:plans-expiry-renewal/monetization-status-is-one-answer.md | A F R | admin, owner, guest, moderator | Y/Y | n | needs gateway keys and WooCommerce dummy |
| Monetization toggle gates credits, plans, coupons, checkout; default OFF; upgrade preservation | pro:plans-expiry-renewal/monetization-toggle-gates-credits-and-plans.md | admin settings, frontend, REST, Woo/MemberPress/PMPro adapters | admin, members with/without ledger, guest | 1440/390 | yes | MemberPress/PMPro not exercised; toggling on shared site avoided |
| Plan auto-renew from credits or pause | pro:plans-expiry-renewal/plan-auto-renewal.md | R C | system, member, admin | -/- | - | pause step expected-fail (publish -> listora_payment refused) |
| Plan eligibility server gates (type/category, parent covers child, picker REST, upgrade heal) | pro:plans-expiry-renewal/plan-eligibility-server-gates.md | REST, CLI/migration | member, other, editor, guest, admin | n/a | n/a | >20 plans step added, not run |
| Plan required to publish; draft cannot skip; also: Plan charged once, to author, at coupon price; no plan-less route to review | pro:plans-expiry-renewal/plan-required-on-submit.md | F R | owner, other, guest, editor, admin | Y/Y | n | short-balance pause expected-fail (Free transition guard); wp-admin/Quick Edit/block-editor half partly not run |
| Wizard offers only eligible plans; no Plan step on live listing | pro:plans-expiry-renewal/plan-step-offers-only-eligible-plans.md | frontend wizard | member, other, guest, admin | 1440/390 | yes | step 12 (parent/child pick) and mobile/dark not run |
| Pricing plan editor (create, scope, clear, price label, expiry, perks, stop selling); also: Plan display price in site currency | pro:plans-expiry-renewal/pricing-plan-editor.md | admin, REST (/plans) | admin, editor, member, guest | 1440/390 | yes | 200-plan list not run; boundary step 3 unrun |
