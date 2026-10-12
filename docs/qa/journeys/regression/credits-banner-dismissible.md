---
journey: credits-banner-dismissible
plugin: wb-listora
priority: normal
roles: [subscriber]
covers: [dashboard-credits, post-checkout-banner]
prerequisites:
  - "The dashboard Credits tab is reachable (see the note at the end)"
estimated_runtime_minutes: 6
covers_card: 10322935144
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The post-checkout banner can be closed

Regression sentinel for the credits banner dismiss on the dashboard Credits tab.

## Background

The banner renders from `?wbcom_credits=...` in the URL and had no close control, so it came back on every reload (and on every later visit from a bookmark or history entry still carrying that query). The control is a **link** (`a[data-listora-credits-banner-dismiss]`, `aria-label="Dismiss this message"`) whose `href` already drops the purchase query args (`wbcom_credits`, `credits`, `gateway`, `session_id`, `token`, `PayerID`), so it works with JavaScript off. With JS, the handler (`src/blocks/user-dashboard/view.js:696-720`) removes the node in place and calls `replaceState`, not `pushState`, so the dismissed URL does not become somewhere Back can return to.

**The first fix missed the path that matters most.** A real Stripe or PayPal return always carries a session token, so `view.js` always tries to claim. When that claim fails it rewrites the banner into the error state with `replaceChildren()`, which took the dismiss control with everything else: an error the member could not close and a URL that brought it back on reload. Steps 8 to 10 exist because of that bounce.

Roles: a member on the Credits tab (the actor); logged out cannot reach the tab (login prompt, no banner); admin as control (same banner on their own dashboard).

## Setup

- The dashboard Credits tab is reachable: `$SITE_URL/my-listings/?tab=credits` (the dashboard page is `wb_listora_get_dashboard_url()`). On the QA site it is, because a WooCommerce credit mapping is configured. Otherwise force it with an mu-plugin `add_filter( 'wb_listora_show_credits', '__return_true', 99 );` and remove it afterwards.
- A member (QA site `qa_vendor_01`). No gateway is needed: the banner is driven by the query string.

## Steps

### 1. The control exists on all three states
- **Action**: visit the Credits tab with `?wbcom_credits=success&credits=50&gateway=stripe`, then `?wbcom_credits=cancel`, then `?wbcom_credits=error`.
- **Expect**: `[data-listora-credits-banner]` has `--success` / `--cancel` / `--error` modifier classes and each carries `a[data-listora-credits-banner-dismiss]` with `aria-label="Dismiss this message"` and a hit area of at least 40x40 (`min-width/min-height: 40px`).
- **Texts**: success "Payment received." + "Adding your credits..." ; cancel "Checkout canceled." + "No charge was made - pick a pack below to try again."; error "We couldn't process your purchase." + "Please try again or contact support if the issue persists."

### 2. Its href strips every purchase arg
- **Expect**: the `href` is `.../my-listings/?tab=credits` (keeps `tab=credits`, drops the rest). That is the no-JS path, so check the attribute, not just the click.
- **PayPal half**: with `gateway=paypal&token=EC-TEST&PayerID=ABC` the `href` drops `token` and `PayerID` too (a payment token must not be left in a URL the member may copy or bookmark).

### 3. Clicking closes it without losing the page
- **Expect**: the banner disappears, the URL loses the purchase args, and the rest of the tab (balance card, packs, history) is untouched.

### 4. Reload does not bring it back
- **Expect**: refreshing after dismissal shows no banner.

### 5. Back does not resurrect the dismissed view
- **Expect**: the dismissed URL is not a history entry. Back lands on whatever preceded the checkout return, not on the banner just closed. (If you visited the banner URL several times while testing, Back legitimately reaches an earlier visit; check it skipped the dismissed entry.)

### 6. The copy never runs under the control
- **Action**: look at the longest variant (error / cancel, three lines at narrow widths).
- **Expect**: text stays clear of the control; the banner reserves inline-end padding for it. Compare rects, do not eyeball.

### 7. 390px
- **Expect**: the control stays in the corner, still >= 40x40, and the page does not scroll horizontally.

### 8. A FAILED claim is still dismissible (the bounced case)
- **Action**: `?wbcom_credits=success&credits=100&gateway=stripe&session_id=cs_test_unknown`. Wait ~2 s for the claim to come back (409 `gateway_unavailable` when no gateway is configured, as on the QA site; 409 or 4xx for an unknown session otherwise).
- **Expect**: the banner turns `--error`, a fresh `<span role="alert">` says "We could not find this payment on your account, so no credits were added. If you were charged, contact the site administrator." **and the dismiss control is still there**. Click it: banner gone, URL clean, reload stays clean.
- **Never**: an error banner with no close control.
- **On fail**: `src/blocks/user-dashboard/view.js:870-894` (the `claim === 'failed'` branch must re-append the dismiss node in `replaceChildren( alert, dismiss )`) and the rebuilt `build/blocks/user-dashboard/view.js`.

### 9. The same for PayPal
- **Action**: `?wbcom_credits=success&credits=100&gateway=paypal&token=EC-TESTTOKEN&PayerID=ABC123`.
- **Expect**: a `POST .../wbcom-credits/v1/wb-listora/claim/paypal` is sent, the claim fails, the banner rewrites to the error, the dismiss survives and its `href` strips `token` + `PayerID`.

### 10. 390px on the error state
- **Action**: step 8 at 390.
- **Expect**: the control is 40x40 and its bounding box does not overlap the `[role="alert"]` text, which wraps to about four lines.

### 11. Positive control, role ladder
- **Control**: step 1 (a banner exists and is closable) and `?tab=credits` with no query (NO banner: `0` matches of `data-listora-credits-banner`, verified) show dismissal removes the state and does not merely hide a permanent element.
- **Logged out**: the dashboard shows the login prompt; the banner markup is not rendered.
- **Admin**: same banner on their own Credits tab.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| No control | markup removed | `templates/blocks/user-dashboard/tab-credits.php:191-210` |
| href keeps `token` / `PayerID` | arg list shortened | `tab-credits.php:198-200` |
| Control gone after a failed claim | `replaceChildren()` drops it | `src/blocks/user-dashboard/view.js:870-894` |
| Back returns to the banner | `pushState` used | `src/blocks/user-dashboard/view.js:696-716` |
| Text under the control / <40px | CSS | `blocks/user-dashboard/style.css:1322-1345` |

## Not verified

Steps 1 and 2 (classes, attributes, texts and the `href` for success/cancel/error and the PayPal query) and the no-banner control were read live with curl as `qa_vendor_01`. The click, reload, Back, rect and 390 checks (steps 3 to 10) and the claim request in steps 8 to 9 (a 409 was confirmed by curl, not through the page) were NOT run in a browser.
