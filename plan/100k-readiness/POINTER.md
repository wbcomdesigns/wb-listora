# 100K-Readiness Plan — Pointer

The consolidated 100K-readiness plan covering both Free and Pro lives in the Pro repo (since Pro extends Free, and the architecture contract enforcing it lives there too):

→ [`../../wb-listora-pro/plan/100k-readiness/CONSOLIDATED-PLAN.md`](../../../wb-listora-pro/plan/100k-readiness/CONSOLIDATED-PLAN.md)

**Performance budgets (quantitative TTFB + Lighthouse targets):**

→ [`../../wb-listora-pro/plan/100k-readiness/PERFORMANCE-BUDGETS.md`](../../../wb-listora-pro/plan/100k-readiness/PERFORMANCE-BUDGETS.md)

**Free-only sections in that plan:**

- Phase 0.2 W.1, W.2, W.3 — wppqa hard blockers in Free's JS + admin
- Phase 1.1 — Free cron migration (3 jobs)
- Phase 1.2 — N+1 prefetch in `class-listings-controller.php`
- Phase 1.3 — 43 apiFetch hang risks in Free
- Phase 1.4 — 16 CSS grid `1fr` overflow risks
- Phase 1.5 — Missing block render hooks (`listing-card`, `listing-search`)
- Phase 1.6 — Asset gating for `listora-submit-lock.js`
- Phase 2.8 — Free needs its own `Settings_Helper` (Pro already has one)

**Audit baselines (Free):**

- `audit/wppqa-baseline-2026-08-12/SUMMARY.md` — the current baseline (the 2026-05-07 one this line used to cite was deleted; older baselines are not kept, git history has them)
- `audit/manifest.json` v2.1 — see the manifest itself for current counts; the figures once inlined here were three releases stale
- `audit/derived/cross-plugin-coupling.json` — 25 Free→Pro hook pairs

Don't duplicate plan content here; refresh both manifests after each phase.
