---
journey: claim-a-business
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber, listing-owner, other-member, editor, moderator, admin]
covers: [claim-modal, claims-rest, login-gate, claim-anon-cta, modal-getter-pattern, claim-proof-upload, claim-single-confirmation, claim-approval-transfer, claimed-flag, bc-10369254018, card-10327885984, card-10336063667, card-10336378720, C.member.claim]
prerequisites:
  - "Site reachable at $SITE_URL; dev auto-login mu-plugin installed"
  - "Features > Claims ON (`wb_listora_features.claims`, default on)"
  - "One published, UNCLAIMED {listing} (no `_listora_is_claimed`) owned by {owner}. CLAIMANT = {other}; THIRD = a third member; {editor}; {admin}"
  - "Debug logging on (`wp-content/debug.log`); a tiny PNG/JPEG/PDF under 5 MB for the proof file"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: ["ora:regression/claim-cta-visible-to-anonymous.md", "ora:regression/claim-proof-upload-no-recursion.md", "ora:regression/claim-report-single-notice.md (claim half)"]
fails_on: "see steps 1, 5, 6 (per-step shas where known; none recorded for the CTA and proof-upload sentinels)"
---

# A visitor claims a business: CTA, login gate, proof, one confirmation, approval hands over ownership

## Why
Claiming is how an owner takes over a listing and almost every first visit is logged out. The CTA must show for guests, gate on login, take a private proof file without a fatal, confirm once, and never flip ownership before a moderator approves.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `LISTING_ID` = the {listing}; page `$SITE_URL/listing/{listing}/`; claimant dashboard `$SITE_URL/my-listings/?tab=claims`.
- Clean: `DELETE FROM {prefix}listora_claims WHERE listing_id=LISTING_ID`; `wp post meta delete LISTING_ID _listora_is_claimed _listora_pre_claim_author`.
- Switching users: autologin is ignored while a session exists. Log out (`/wp-login.php?action=logout`, confirm) between roles.
- DOM probe:
  ```js
  ({ loggedIn: document.body.className.includes('logged-in'),
     claim: !!document.querySelector('[data-wp-on--click="actions.showClaimModal"]'),
     claimModal: !!document.getElementById('listora-claim-modal'),
     loginModal: !!document.getElementById('listora-login-modal') })
  ```
- Code map: `blocks/listing-detail/render.php:559-570` (`$listora_can_claim`), `:938`, `:951-980`; `src/interactivity/store.js:1836` (`showClaimModal`), `:2454` (`submitClaim`), `:482-492`, `:512-520`; `includes/rest/class-claims-controller.php:154-260`; `includes/core/class-claim-proofs.php:186`, `:203-211`.

## Steps

### 1. Anonymous sees the Claim CTA
- **Action**: logged out, open the listing, run the probe.
- **Expect**: `loggedIn` false, `claim` true (button "Claim" in `.listora-detail__actions`), `claimModal` false (a guest never opens it), `loginModal` true.
- **Read-back**: HTTP 200, no redirect.

### 2. Logged-out click opens the LOGIN modal, not the claim form
- **Action**: click "Claim", wait for the Interactivity directive to settle (a synchronous read in the same expression as `.click()` returns pre-click state; if Playwright reports `#listora-login-modal` intercepts pointer events, the modal opened).
- **Expect**: `#listora-login-modal.is-open`, `[role=dialog][aria-modal=true]`, `<h3 id="listora-login-modal-title">` "Log in to claim this listing" (server paints "Log in to save listings"; `data-wp-text="state.loginModalTitle"` swaps it), desc "Sign in to request ownership of this listing.", links "Log in" and "Create account"; focus inside; Esc closes.
- **Read-back**: `POST /wp-json/listora/v1/claims` without a session returns `401 listora_unauthorized`.

### 3. As CLAIMANT the claim form opens
- **Action**: `$SITE_URL/?autologin={other}`, open the listing, click "Claim".
- **Expect**: `#listora-claim-modal.is-open`, `role=dialog aria-modal=true`, heading "Claim This Business", "Prove you own or manage this business. Once approved, you can edit the listing and reply to its reviews."; `textarea[name="proof_text"]#listora-claim-proof` (required, "Proof of Ownership *") and `input[type=file][name="proof_file"]#listora-claim-proof-file` (JPEG/PNG/GIF/WebP/PDF, "... max 5 MB"); "Submit Claim", "Cancel"; focus inside; Esc closes.

### 4. Empty proof
- **Action**: "Submit Claim" with `proof_text` empty.
- **Expect**: browser validation "Please fill in this field."; no request.
- **Read-back**: REST `{"listing_id":..}` without proof returns `400 rest_missing_callback_param`.

### 5. Submit the claim: one confirmation, inside the dialog
- **Action**: `#listora-claim-proof`=`I am the owner. QA-J1 smoke claim.`; "Submit Claim".
- **Expect**: `POST /wp-json/listora/v1/claims` (multipart: `listing_id`, `proof_text`, optional `proof_file`) `201 {"id":N,"status":"pending","message":"Your claim has been submitted and is under review."}`; `.listora-detail__claim-body` and the submit button hidden; `.listora-detail__claim-message--success` "Claim submitted - we'll email you when it's reviewed." with a **View my claims** link (dashboard `#claims`); NO `.listora-toast` (toast only when a template override removed the message element). The report half of the contract: `customer/flag-a-listing.md` steps 2, 13.
- **Read-back**: one `listora_claims` row (step 7).

### 6. A claim with a proof file is saved, not a memory fatal
- **Action**: note `ls -l wp-content/debug.log`; on a second pending claim (other listing) choose a file in `#listora-claim-proof-file` and submit, or `curl -X POST $SITE_URL/wp-json/listora/v1/claims -H "X-WP-Nonce: <nonce>" -F listing_id=<id> -F "proof_text=QA-J1- probe" -F "proof_file=@proof.png;type=image/png"`.
- **Expect**: `201 {"id":N,"status":"pending",...,"proof_file_uploaded":true}`; the form is replaced by the success message. `debug.log` did not grow with `Allowed memory size` or `class-claim-proofs.php` frames (the `upload_dir` filter once re-entered itself and wrote ~90 MB). Ordinary uploads are unaffected: `wp eval 'echo wp_upload_dir()["subdir"];'` is the dated `/YYYY/MM` folder and a Media Library upload lands there.
- **Read-back**: `SELECT proof_files FROM {prefix}listora_claims WHERE id=N` is a JSON array of attachment IDs; each attachment `post_status='private'`, file under `uploads/listora-claim-proofs/` named `proof-<32 chars>.<ext>`, post meta `_listora_claim_proof=1`.
- **Role ladder for the file**: a direct request to the file URL as {guest} and as another member is refused by the server rule from `Claim_Proofs::protect()`; the admin Claims drawer (**Details**) links it through `admin-post.php?action=wb_listora_claim_proof` only for a user with `manage_listora_claims`; {admin} is the control. Not verified live (browser upload and URL refusal).

### 7. DB check and no premature flip
- **Action**: `SELECT id, listing_id, user_id, status, proof_text FROM {prefix}listora_claims WHERE listing_id=LISTING_ID`; `get_post_meta(LISTING_ID,'_listora_is_claimed',true)`.
- **Expect**: one row `status='pending'`, `proof_text` starts "I am the owner"; `_listora_is_claimed` empty; `post_author` still {owner}. The claimant's dashboard chip, copy and pagination: `customer/13-dashboard-claims-tab.md`.

### 8. Gates (role ladder)
- **Action**: calls per the table below.
- **Expect**: as in the table.

### 9. POSITIVE CONTROL: approval flips the flag
- **Action**: {admin} `PUT /claims/CLAIM_ID {"status":"approved"}` (full screen contract: `admin/03-approve-claim.md`).
- **Expect**: `200 {"id":N,"status":"approved","message":"Claim approved. Listing ownership transferred."}`; `_listora_is_claimed=1`; `post_author` = CLAIMANT; a third member no longer sees "Claim" and `POST /claims` returns `409 listora_already_claimed`.
- **Read-back**: restore `post_author` to {owner}, delete `_listora_is_claimed`, `_listora_pre_claim_author` and the claim row before re-running.

### 10. A claimed listing offers nothing to anyone
- **Action**: `wp post meta update LISTING_ID _listora_is_claimed 1`; reload as {guest}, {other}, {owner}.
- **Expect**: no Claim button and no claim modal in any of the three; sidebar badge "Claimed & Verified Business". Then delete the meta.
- **Read-back**: probe `claim:false, claimModal:false`. Control: step 1 (CTA on) on the same listing/session.

### 11. Layout
- **Action**: steps 1, 3, 5 at 390x844 and in dark mode.
- **Expect**: `scrollWidth <= 390`; modal about 351px wide; `body` `overflow:hidden` while open; Submit Claim, Cancel 46px and close 44px (>= 40px); CTA tappable; text/borders readable in dark mode.

### 12. Modal copy and docs name only fields and settings that exist
- **Action**: list every `input`/`textarea`/`select` name in `#listora-claim-modal form` and every setting/screen the modal text mentions; compare with the claims pages in `docs/website` and the Claims tab of Settings.
- **Expect**: form fields are exactly `proof_text` and `proof_file`; the diff of live fields vs docs is empty.
- **Read-back**: `POST /claims` accepted params (`proof_text`, `proof_file`, `listing_id`) equal the field list. **fails_on**: c5f94994 (parent of 636b523d). Not verified live.

## Role ladder
| Actor | Action | Expect |
|---|---|---|
| {guest} | open listing / click Claim / `POST /claims` | CTA present, login modal, `401` |
| CLAIMANT {other} | click Claim, submit; second `POST /claims` while pending | claim modal, `201`; `409 listora_claim_pending` "You already have a pending claim for this listing." |
| {owner} (post author) | open listing; `POST /claims` | no "Claim" button, no modal, no Report; `409 listora_own_listing` "You already own this listing." |
| Any member, missing proof | `POST /claims {"listing_id":..}` | `400 rest_missing_callback_param` |
| {editor} (non-author) | open listing, click Claim | claim modal like another member |
| Another member, claim pending by someone else | claim | accepted until one is approved |
| Member (non-admin) | `GET /claims` | `403 listora_forbidden` (list is admin/moderator only) |
| {admin} (control) | `GET /claims` | `200`, rows incl. `user_email`, `proof_text`, `status` |

## Pass criteria
1. Guests see the CTA; their click opens the login modal titled "Log in to claim this listing".
2. A logged-in non-owner (member or editor) gets the claim modal with the proof field; owner and claimed listings show no CTA.
3. Submit returns 201 `pending`, confirms once, one row, `_listora_is_claimed` unset; a proof file is stored privately with no fatal.
4. Duplicate, own-listing and missing-proof requests refused with the codes above; list admin-only.
5. Approval sets `_listora_is_claimed`, transfers `post_author`, hides the button.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| No Claim button for visitors | `is_user_logged_in()` back in the button gate | `blocks/listing-detail/render.php:561` |
| Logged-out click opens nothing / the claim form | getter regression / gate bypass | `src/interactivity/store.js:512-520`, `:1836`; rebuilt `build/` bundles |
| Login modal shows "save listings" copy | `loginReason` not `claim` | `store.js:482-492` |
| Owner sees Claim | `post_author` comparison dropped | `render.php:561-562` |
| Claim modal markup rendered for guests | modal gate | `render.php:938` |
| Fatal on submit with proof | `filter_upload_dir()` must read `$dirs`, not call `wp_upload_dir()` | `includes/core/class-claim-proofs.php:186` |
| Media Library uploads land in proofs folder | filter not removed in `finally` | `class-claim-proofs.php:203-211` |
| Attachment not private / `proof_file_uploaded` missing | upload path | `class-claims-controller.php` ~390, `:276` |
| Confirms twice | toast alongside message | `store.js:2454` (`submitClaim`, toast only when `! msgEl`) |
| 403 `listora_claims_disabled` | feature off | `class-claims-controller.php:166` |
| Flag set while pending | state machine | `class-claims-controller.php:660` (`change_status`) |
| PHPUnit | | `tests/integration/ClaimProofUploadDirTest.php` |

## Not verified
Steps 2, 3, 5, 11 click behaviour and 390 layout (source-verified; DOM presence verified live with curl); the modal upload in a browser and file URL refusal; step 12.

## Cleanup
Delete the claims (Claims screen **Delete** or `DELETE /claims/{id}`), `wp post delete <attachment> --force`, the matching `claim_submitted` audit rows, restore `post_author` and delete `_listora_is_claimed`/`_listora_pre_claim_author`.
