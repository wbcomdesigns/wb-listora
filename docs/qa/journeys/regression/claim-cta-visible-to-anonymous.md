---
journey: claim-cta-visible-to-anonymous
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber]
covers: [claim-anon-cta, listing-detail, C.member.claim]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "An UNCLAIMED listing (no _listora_is_claimed meta) with a known post_author"
  - "A member who is NOT that listing's author"
estimated_runtime_minutes: 5
---

# Anonymous visitors must see the Claim CTA

Claiming is how a business owner takes over their listing, and almost every first visit to a
listing page is logged out. If the CTA only renders for logged-in users, the people the feature
exists for never learn it exists.

The gate carried `is_user_logged_in()` from commit `88829e2` (April 2026), whose stated purpose was
*"Claim button now hidden for listing authors"*. The author guard is the `post_author` comparison;
the login check was collateral and silently removed the CTA from every guest — the opposite of the
documented contract in runbook C.member.claim. It shipped that way for months without a report,
because the broken state looks like a design decision rather than a defect.

Three roles must behave differently, and a fix that gets one right by breaking another is the
failure this journey guards.

## Setup

```bash
# Find an unclaimed listing and its author
wp eval '$p = get_posts( array( "post_type" => "listora_listing", "post_status" => "publish", "numberposts" => 5 ) );
foreach ( $p as $x ) { printf( "%d %s author=%d claimed=%s\n", $x->ID, $x->post_name, $x->post_author,
  get_post_meta( $x->ID, "_listora_is_claimed", true ) ?: "unset" ); }'
```

- `LISTING_URL` — permalink of an unclaimed listing
- `OWNER` — that listing's `post_author` user_login
- `OTHER` — any member who is not the owner

**Switching users:** the dev autologin mu-plugin skips when already logged in. Visit
`/wp-login.php?action=logout` and click the confirm link between roles, or the second role silently
tests as the first.

## Steps

### 1. Anonymous sees the CTA
- **Action**: logged out, open `LISTING_URL`, then
  ```js
  ({ loggedIn: document.body.className.includes('logged-in'),
     claim: !!([...document.querySelectorAll('button')].find(b => /^\s*Claim\s*$/i.test(b.textContent))),
     claimModal: !!document.querySelector('[id*="claim-modal"]'),
     loginModal: !!document.getElementById('listora-login-modal') })
  ```
- **Expect**: `loggedIn` false, `claim` **true**, `claimModal` **false**, `loginModal` true
- **On fail**: `claim: false` is the regression — `blocks/listing-detail/render.php` re-acquired
  `is_user_logged_in()` in the button gate. It must read
  `( ! is_user_logged_in() || (int) $post->post_author !== get_current_user_id() )`, the same shape
  the Report control a few lines below already uses.
- **Note**: `claimModal: false` for a guest is CORRECT, not a second bug. A guest never opens it —
  they get the login modal — so rendering the claim form for them would ship unreachable markup.

### 2. Clicking it opens the login modal
- **Action**: click the Claim button, then read `#listora-login-modal`
- **Expect**: `is-open` class present, `display: flex`, `[role=dialog][aria-modal=true]`, focus moved inside
- **Timing**: read the state AFTER the Interactivity directive settles. A synchronous read in the
  same expression as the `.click()` returns the pre-click state and looks like a failure.
  If Playwright's click times out complaining that `#listora-login-modal ... intercepts pointer
  events`, that IS the pass — the modal opened and is covering the button.
- **On fail**: `actions.showClaimModal` in `src/interactivity/store.js` lost its
  `if ( ! state.isLoggedIn ) { actions.openModal( 'login' ); return; }` guard, which mirrors
  `openReportModal()`. **Check the build** — the store bundles into `build/interactivity/store.js`
  and several `build/blocks/*/view.js`; a `src/` fix alone ships nothing.

### 3. A logged-in non-owner gets the CLAIM modal, not the login one
- **Action**: log in as `OTHER`, open `LISTING_URL`, click Claim
- **Expect**: the claim modal is `is-open`; the login modal is NOT
- **On fail**: the `state.isLoggedIn` guard is inverted or always-true.

### 4. The listing's OWNER sees no CTA at all
- **Action**: log out, log in as `OWNER`, open `LISTING_URL`
- **Expect**: no Claim button, no claim modal in the DOM
- **On fail**: this is what commit `88829e2` existed to prevent — an owner offered the chance to
  claim a listing they already own. Restoring the anonymous CTA must not drop the `post_author`
  comparison.

### 5. A claimed listing offers nothing
- **Action**: set `_listora_is_claimed` on the listing, reload as anonymous and as `OTHER`
- **Expect**: no Claim button in either case; the sidebar shows the Claimed badge. Unset the meta afterwards.

### 6. Mobile
- **Action**: 390x844, repeat step 1 and step 2 as anonymous
- **Expect**: CTA visible in the action bar, login modal opens and fits, no horizontal overflow

## Pass criteria

ALL of the following hold:
1. Anonymous sees the Claim CTA; the claim modal is not in their DOM.
2. Their click opens the login modal with focus moved into it.
3. A logged-in non-owner gets the claim modal instead.
4. The owner sees no CTA and no modal.
5. A claimed listing offers no CTA to anyone.
6. Works at 390px.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| No Claim button when logged out | `is_user_logged_in()` back in the button gate | `blocks/listing-detail/render.php` (`$listora_can_claim`) |
| Guest click opens the claim form, or nothing | `showClaimModal` lost its `state.isLoggedIn` branch | `src/interactivity/store.js` + rebuilt bundles |
| Owner now sees a Claim button | `post_author` comparison dropped while fixing the guest path | `blocks/listing-detail/render.php` |
| Fix looks right in source, wrong in browser | bundles not rebuilt | `npm run build`, commit `build/` |
