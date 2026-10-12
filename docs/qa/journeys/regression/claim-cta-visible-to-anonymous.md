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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Anonymous visitors see the Claim CTA; owners and claimed listings do not

Claiming is how a business owner takes over a listing, and almost every first visit to a listing page is logged out. The CTA must render for guests, open the login modal for them, open the claim modal for a logged-in non-owner, and never render for the listing's own author or on a claimed listing. A fix that gets one role right by breaking another is the failure this journey guards.

Role ladder (all five are required): anonymous, another member (not the author), the listing author (owner), moderator/editor (a non-author, so it behaves like another member), admin as control.

## Setup

- `LISTING_URL`: a published, unclaimed listing. QA site: listing 746, `$SITE_URL/listing/central-park-the-mall-bethesda-terrace/` (author = `varundubey`, user 1, no `_listora_is_claimed` meta).
- `OWNER` = that listing's `post_author` login (`varundubey`); `OTHER` = any member who is not the owner (`author1`); `EDITOR` = `qa_editor`.
- The feature switch: the "Business Claims" feature (option `wb_listora_features`, key `claims`, default on; `wb_listora_feature_enabled( 'claims' )`) must be on. With it off no role sees the CTA.
- Switching users: autologin is ignored while a session exists. Log out (`/wp-login.php?action=logout`, confirm link) between roles.
- Probe used for the DOM checks below:
  ```js
  ({ loggedIn: document.body.className.includes('logged-in'),
     claim: !!document.querySelector('[data-wp-on--click="actions.showClaimModal"]'),
     claimModal: !!document.getElementById('listora-claim-modal'),
     loginModal: !!document.getElementById('listora-login-modal') })
  ```

## Steps

### 1. Anonymous sees the CTA
- **Action**: logged out, open `LISTING_URL` and run the probe.
- **Expect**: `loggedIn` false, `claim` **true** (button text "Claim" in the action bar), `claimModal` **false**, `loginModal` true.
- **Note**: `claimModal: false` for a guest is correct. A guest never opens it; they get the login modal.

### 2. Clicking it opens the login modal with the claim copy
- **Action**: click **Claim**, wait for the Interactivity directive to settle, then read `#listora-login-modal`.
- **Expect**: `is-open` class present, `[role=dialog][aria-modal=true]`, focus moved inside, title text "Log in to claim this listing" (the server paints "Log in to save listings"; `data-wp-text="state.loginModalTitle"` swaps it on click), a **Log in** link.
- **Timing**: a synchronous read in the same expression as `.click()` returns the pre-click state. If Playwright's click reports that `#listora-login-modal` intercepts pointer events, the modal opened and that is the pass.

### 3. A logged-in non-owner gets the CLAIM modal, not the login one
- **Action**: log in as `OTHER`, open `LISTING_URL`, click **Claim**.
- **Expect**: `#listora-claim-modal` is `is-open`, heading "Claim This Business", textarea `#listora-claim-proof` (name `proof_text`), file input `#listora-claim-proof-file` (name `proof_file`), buttons **Submit Claim** and **Cancel**; the login modal is NOT open. Repeat as `EDITOR`: same.

### 4. The listing's OWNER sees no CTA at all
- **Action**: log in as `OWNER`, open `LISTING_URL`, run the probe.
- **Expect**: `claim` false, `claimModal` false. The Report control is also absent for the owner.
- **Fail**: an owner offered the chance to claim a listing they already own (author guard dropped).

### 5. A claimed listing offers nothing to anyone
- **Action**: set `_listora_is_claimed` = 1 on the listing (`wp post meta update 746 _listora_is_claimed 1`), reload as anonymous, as `OTHER` and as `OWNER`.
- **Expect**: no Claim button and no claim modal in any of the three; the sidebar shows the badge "Claimed & Verified Business". Then `wp post meta delete 746 _listora_is_claimed`.

### 6. Positive control, mobile
- **Control**: step 1 (CTA on) is the positive control for steps 4 and 5 (CTA off). Steps 4 and 5 are only meaningful if step 1 passed on the same listing in the same session.
- **Action**: 390x844, anonymous, repeat steps 1 and 2.
- **Expect**: CTA visible in the action bar and tappable (height >= 40px), the login modal opens and fits the viewport, no horizontal page scroll.

## Pass criteria

1. Anonymous sees the Claim CTA; the claim modal is not in their DOM.
2. Their click opens the login modal titled "Log in to claim this listing" with focus inside.
3. A logged-in non-owner (member or editor) gets the claim modal instead.
4. The owner sees no CTA and no modal.
5. A claimed listing offers no CTA to anyone and shows the Claimed badge.
6. Works at 390px.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| No Claim button when logged out | `is_user_logged_in()` back in the button gate | `blocks/listing-detail/render.php:561` (`$listora_can_claim`) |
| Guest click opens the claim form, or nothing | `showClaimModal` lost its `state.isLoggedIn` branch | `src/interactivity/store.js:1836` and the rebuilt `build/` bundles |
| Login modal shows "save listings" copy | `loginReason` not set to `claim` | `src/interactivity/store.js:482-492` |
| Owner now sees a Claim button | `post_author` comparison dropped | `blocks/listing-detail/render.php:561-562` |
| Claim modal markup rendered for guests | modal gate lost `is_user_logged_in()` | `blocks/listing-detail/render.php:938` |
| Fix right in source, wrong in browser | bundles not rebuilt | `npm run build`, check `build/interactivity/store.js` |

## Not verified

DOM presence of the CTA and modals was verified live with curl as anonymous, `author1`, `qa_editor` and the owner (`varundubey`), and with the claimed meta on and off. The click behaviour in steps 2 and 3 (modal opening, title swap, focus) and the 390 layout in step 6 were NOT run in a browser; they are verified against source only.
