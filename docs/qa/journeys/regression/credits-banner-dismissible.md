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
---

# The post-checkout banner can be closed

Regression sentinel for the credits banner dismiss.

## Background

The banner renders from `?wbcom_credits=…` in the URL and had no close control, so it came back on every reload — and on every later visit from a bookmark or history entry still carrying that query. A member who had read "Payment received" was left with a purchase announcement on their dashboard indefinitely.

The control is a **link** whose `href` already drops the purchase query args, so it works with JavaScript off. With JS, the handler removes the node in place and calls `replaceState` — not `pushState` — so the dismissed URL does not become somewhere the Back button can return to.

**The first fix missed the path that matters most.** A real Stripe or PayPal return always carries a session token, so `view.js` always tries to claim. When that claim fails it rewrites the banner into the error state with `replaceChildren()` — which took the dismiss control with everything else. The member was left with an error they could not close and a URL that brought it back on reload: the original defect, surviving on the one path where something has actually gone wrong. Steps 8 and 9 exist because of that bounce; they are the reason this journey is not just about first paint.

## Steps

### 1. The control exists on all three states
- **Action**: visit the Credits tab with `?wbcom_credits=success&credits=50&gateway=stripe`, then `=cancel`, then `=error`.
- **Expect**: each banner carries a dismiss control with `aria-label="Dismiss this message"` and a tap target of at least 40×40.

### 2. Its href strips every purchase arg
- **Expect**: the `href` drops `wbcom_credits`, `credits`, `gateway` and `session_id` while keeping `tab=credits`. That is the no-JS path, so check the attribute, not just the click.
- **Also PayPal's half**: with `gateway=paypal&token=EC-TEST&PayerID=ABC`, the `href` drops `token` and `PayerID` too. The banner keys off `wbcom_credits` alone, so leaving them would not resurrect it - but a payment token in a URL the member may copy or bookmark is not something to leave there on purpose.

### 3. Clicking closes it without losing the page
- **Expect**: the banner disappears, the URL loses the purchase args, and the rest of the tab — balance card, packs, history — is untouched.

### 4. Reload does not bring it back
- **Expect**: refreshing after dismissal shows no banner. This was the reported symptom.

### 5. Back does not resurrect the dismissed view
- **Expect**: the dismissed URL is not a history entry. Going back lands on whatever preceded the checkout return, not on the banner you just closed.
- **Note**: if you visited the banner URL several times while testing, Back will legitimately reach one of those *earlier* visits. That is not a regression — check that it skipped the entry you dismissed.

### 6. The copy never runs under the control
- **Action**: look at the longest variant (cancel, which wraps to three lines at narrow widths).
- **Expect**: text stays clear of the ×; the banner reserves inline-end padding for it.

### 7. 390px
- **Expect**: the control stays in the corner, still at least 40px, and the banner does not scroll horizontally.

### 8. A FAILED claim is still dismissible - the bounced case
- **Action**: `?wbcom_credits=success&credits=100&gateway=stripe&session_id=cs_test_unknown`. Wait ~2s for the claim to come back 409.
- **Expect**: the banner turns red (`--error`), says the payment could not be found - **and still carries the dismiss control**. Click it: banner gone, URL clean, reload stays clean.
- **Never**: an error banner with no ×. That is the original bug on the only path a real return takes when something is wrong.
- **On fail**: `src/blocks/user-dashboard/view.js` → the `claim === 'failed'` branch; `replaceChildren()` must re-append the dismiss node.

### 9. The same for PayPal
- **Action**: `?wbcom_credits=success&credits=100&gateway=paypal&token=EC-TESTTOKEN&PayerID=ABC123`.
- **Expect**: identical - claim fails, banner rewrites to error, dismiss survives, href strips `token` + `PayerID`.

### 10. 390px on the error state
- **Action**: step 8 at 390px.
- **Expect**: the × is still 40×40 and its bounding box does not overlap the `[role="alert"]` text, which wraps to four lines. Compare rects rather than eyeballing - the error copy is longer than the success copy the first pass was checked against.

## Reaching the Credits tab at all

The credits surfaces are gated on `wb_listora_should_show_member_credits()`, which requires a real purchase path — on a sandbox with no registered gateway the tab does not render. Force it with an mu-plugin, then remove it:

```php
add_filter( 'wb_listora_show_credits', '__return_true', 99 );
```

## Automated coverage

None — it is markup and a click handler, and the meaningful assertion is the rendered banner. Walk it in a browser.
