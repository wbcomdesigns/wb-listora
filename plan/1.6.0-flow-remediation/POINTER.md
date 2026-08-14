# 1.6.0 Flow Remediation — Pointer

The consolidated 1.6.0 flow-remediation plan covering both Free and Pro lives in the Pro repo
(since Pro extends Free, and the architecture contract ordering the work lives there too):

→ [`../../wb-listora-pro/plan/1.6.0-flow-remediation.md`](../../../wb-listora-pro/plan/1.6.0-flow-remediation.md)

**What it covers:** the 21 cards in Possible Bugs + Bugs on board `9827892288`, collapsed into the
seven flows behind them, plus six new `bin/audit-guardrails.sh` detectors (G5–G10) so those classes
cannot be re-filed.

**Free-owned work in that plan:**

- Wave 1.1 — `agree_terms` enforced in `class-submission-controller.php` + `handleSubmission`
- Wave 1.2 — fire `wb_listora_after_update_claim` from the admin claim paths (`class-admin.php`)
- Wave 3.6 — `listing-card` stylesheet declared by every server-side card surface (100K Phase 1.5)
- Wave 3.7 — Leaflet + picker init on `listora_listing` edit screens
- Wave 3.8 — hooks around the related-listings section
- Wave 4.9 — `wb_listora_render_icon()` so Pro renders badge icons without breaching INV-3
- Wave 4.10 — canonical icon set: map expansion **before** picker constraint
- Wave 5 — `review_criteria` readers, tags end-to-end, video render, Features in submission,
  dashboard service CRUD, gallery carousel
- Wave 6 — G5–G10 in `bin/audit-guardrails.sh`

Don't duplicate plan content here; update the Pro-side file and refresh both manifests after each
wave.
