---
group: claims-verification
plugin: wb-listora
scope: Claim a business (CTA, login gate, proof, one confirmation), the admin Claims queue (approve, reverse, reject, delete), the claimant's My Claims tab and the claimed state of a listing
journeys: 4
site_profile: required
---
# Claims and verification (Free): what an agent checks here

## Scope
Covers the Claim button on a listing page, the login modal it opens for guests, the claim modal with proof text and proof file, the success state, the wp-admin Claims screen and its drawer, the claim notices, ownership transfer and reversal, the claimant email, the member's "My Claims" dashboard tab and its pagination, and the claimed marker on the listing (`_listora_is_claimed`, sidebar badge).

Not covered here: the Verified badge, Verification metabox and Custom Badges (Pro half of this group, run `wb-listora-pro/docs/qa/journeys/claims-verification/_group.md` afterwards), the audit rows and webhooks a claim writes (`analytics-audit`), generic admin table behaviour such as bulk Apply and per-page (`admin-listings`), the page-access matrix for editors and moderators (`access-security`), the rest of the listing page (`listing-detail`), dashboard tab paging in general (`dashboard-account`), the report-a-listing modal that shares the modal markup (`listing-detail`).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Claim button in the listing action bar | `$SITE_URL/listing/{listing}/` (`.listora-detail__actions`) | guest, member, editor; hidden for the post author and when claimed | `blocks/listing-detail/render.php:559-570` |
| 2 | Login modal opened by Claim for a guest | click Claim logged out | guest | `blocks/listing-detail/render.php:1074` (login modal), `src/interactivity/store.js:482-492`, `:1836` |
| 3 | Claim modal (proof text, proof file) | click Claim logged in | logged-in non-owner when listing unclaimed and Claims ON | `blocks/listing-detail/render.php:938-980` |
| 4 | Claim success state inside the modal | after Submit Claim | claimant | `store.js:2454` (`submitClaim`), `.listora-detail__claim-message--success` |
| 5 | Claimed marker on the listing (sidebar badge, no Claim button) | listing page after approval | all roles | `templates/blocks/listing-detail/sidebar.php:170-174` |
| 6 | Member dashboard My Claims tab | `$SITE_URL{dashboard}?tab=claims[&claims_page=N]` | claimant (own claims), guest sees sign-in | `templates/blocks/user-dashboard/tab-claims.php`, `blocks/user-dashboard/render.php:354` |
| 7 | wp-admin Listora > Claims queue | `$SITE_URL/wp-admin/admin.php?page=listora-claims[&status=pending|approved|rejected|all]` | {admin}, {editor} (cap `manage_listora_claims`) | `includes/admin/class-admin.php:2053` (`render_claims_page`), `:2190` (`claim_row`), notices `:2151` |
| 8 | Claim Details drawer (inside 7) | row **Details** | {admin}, {editor} | `class-admin.php:2297` (`claim_detail`) |
| 9 | Proof file download | drawer link `admin-post.php?action=wb_listora_claim_proof` | users with `manage_listora_claims` only | `includes/core/class-claim-proofs.php` |
| 10 | Settings > Features > Business Claims | `admin.php?page=listora-settings` Features tab | {admin} | `includes/class-features.php:27` |
| 11 | Claimant email and "claim rejected" email | Email Log screen or Mailpit | claimant | `includes/workflow` notifications, `claim_approved` event key |

## Components on each surface
**1 Claim button**: `button.listora-btn.listora-btn--secondary[data-wp-on--click="actions.showClaimModal"]` with check-circle icon and label "Claim", in `.listora-detail__actions` beside Report and Share.

**2 Login modal**: `#listora-login-modal` (`.is-open`), `[role=dialog][aria-modal=true]`, title `#listora-login-modal-title` (swapped to "Log in to claim this listing"), description "Sign in to request ownership of this listing.", links "Log in" and "Create account", close button, backdrop.

**3 Claim modal**: `#listora-claim-modal` > `.listora-detail__modal-backdrop`, `.listora-detail__modal-content[role=dialog][aria-labelledby=claim-modal-title]`, close button `.listora-detail__modal-close`, `h3#claim-modal-title` "Claim This Business", `.listora-detail__modal-desc`, `form.listora-detail__claim-form[enctype=multipart/form-data]` with `.listora-detail__claim-body` = `textarea#listora-claim-proof[name=proof_text][required]` ("Proof of Ownership *"), `input#listora-claim-proof-file[type=file][name=proof_file][accept=image/jpeg,image/png,image/gif,image/webp,.pdf]` with hint "JPEG, PNG, GIF, WebP or PDF, max 5 MB", `.listora-detail__claim-actions` (Submit Claim, Cancel); message line `.listora-detail__claim-message[hidden]`.

**4 Success**: body and Submit hidden, `.listora-detail__claim-message--success` text "Claim submitted - we'll email you when it's reviewed." plus **View my claims** link; no toast.

**5 Claimed badge**: `.listora-detail__claimed-badge` with icon and text "Claimed & Verified Business".

**6 My Claims**: nav `#dash-tab-claims` "My Claims" (badge), panel `#dash-panel-claims`; `ul.listora-dashboard__claims-list` of `li.listora-dashboard__claim-row`: title link `.listora-dashboard__claim-title a`, chip `.listora-dashboard__status` ("Under review", "Approved", "Not approved"), `.listora-dashboard__claim-submitted` ("Submitted <date>"), note `.listora-dashboard__claim-notes` (pending: "We will email you as soon as the review is complete."; approved `--success`: "You now own this listing. Open My Listings to edit it."; rejected: "Reviewer notes:" + text), action `.listora-dashboard__claim-actions` (Edit Listing for approved, View Listing otherwise); pager `claims_page`; empty state "No claim requests yet" with "Browse the directory".

**7 Admin screen**: shared header "Claims" with icon and description; notice area (`notice-success`, `notice-info`, `notice-warning`); filter tabs All / Pending / Approved / Rejected with counts; search "Search by listing, name or email"; bulk bar (Approve, Reject, Delete) with select-all; table columns Listing (link), Claimant (name, email, "N other claims" warn badge when flagged), Proof (18-word clamp, "N documents", or "None given"), Status badge (Pending, Approved, Rejected), Submitted (sortable); row actions Approve (primary), Reject (pending only), Reverse approval (approved only, in the `...` menu with confirm), Details, Delete (confirm "The claim record is removed. Who owns the listing does not change."); footer count "N claims", per-page, pagination; empty state "No claims waiting" / "No claims yet".

**8 Drawer**: native `<dialog class="listora-drawer">`: Claimant name and mailto, full proof text, document links, the member's other claims list.

## States to force
| Surface | State | How |
|---|---|---|
| 1-3 | guest / member / post author / editor / listing already claimed | autologin one persona per context; `wp post meta update LISTING_ID _listora_is_claimed 1` |
| 1-3 | Claims feature OFF | Settings > Features untick Business Claims (Claim button absent, REST `403 listora_claims_disabled`) |
| 3 | empty proof, 1 char, 20 chars, max length, markup, emoji, whitespace only | type into `#listora-claim-proof` |
| 3 | proof file: PNG, PDF, 5 MB + 1, `.php`, SVG, zero-byte | file input |
| 4 | success, 409 pending, 409 own listing, 409 already claimed, 429 rate limit (`claim_submit`) | repeat submits, claim your own listing, approve then claim |
| 6 | 0 claims, approved + pending + rejected, more than 20 claims, rejected with notes, listing deleted ("(listing removed)") | seed QA claims; delete a listing |
| 7 | 0 rows, 1 row, pending default tab, > 20 rows, stale link, second claim on an already-claimed listing, claimant with two other claims | seed `${PREFIX}listora_claims`; reuse an Approve link |
| all | 390, dark, unbridged theme, RTL if the site is RTL | profile themes, resize |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| `wb_listora_features['claims']` (Settings > Features > Business Claims) | on / off | surfaces 1-4 and 6 vanish when off; admin queue (7) stays reachable to process a backlog; REST `403 listora_claims_disabled` |
| `wb_listora_dashboard_per_page` filter (default 20) | int | My Claims pager size |
| Rate limit `claim_submit` (daily window) | per user | `429 listora_rate_limit` with `Retry-After` |
| Dashboard page / Directory page (Settings > Pages) | page ids | "View my claims", "Edit Listing" and "Browse the directory" links |
| Pro Verification feature (Pro) | on / off | claimed badge wording versus Verified badge (see the Pro group) |
| `wb_listora_rest_prepare_claim` filter | callable | extra keys on claim payloads |

## Fixtures and setup
- Seed: `wp eval-file $WP_PATH/wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed` then pick an UNCLAIMED {listing} owned by {owner}. Capture `CLAIM_ID`, `LISTING_ID`, `ORIG_AUTHOR_ID`, `MAIL_BASE` as in `approve-claim.md` Setup.
- Seed paging data with QA-prefixed claims (`proof_text` starting `QA-J1`); remove with `DELETE /claims/{id}` or `DELETE FROM ${PREFIX}listora_claims WHERE proof_text LIKE 'QA-J1%'`.
- Debug logging on to prove the proof upload does not recurse.
- Restore afterwards: `post_author` back to {owner}; `wp post meta delete LISTING_ID _listora_is_claimed _listora_pre_claim_author`; delete proof attachments (`wp post delete <id> --force`); fixtures `clean`; Claims feature ON.

## Run order
1. `claims-verification/claim-a-business.md` - browser, critical: CTA, login gate, modal, proof, one confirmation, approval flip.
2. `claims-verification/dashboard-claims-tab.md` - mixed, normal: My Claims chips, empty state, REST scope, feature switch.
3. `claims-verification/approve-claim.md` - mixed, critical: admin queue, ownership transfer, email, leads, reversal, delete.
4. `claims-verification/claims-list-and-count-agree.md` - mixed, high: admin and REST share one model and count.
Then run: `wb-listora-pro/docs/qa/journeys/claims-verification/_group.md`.

## Group-specific micro checks
- G-01 Sidebar wording: a claimed listing shows "Claimed & Verified Business" (`sidebar.php:173`) even when Pro Verification is OFF or Pro is absent and `is_verified` is false. Compare with the approval email and the Pro Verified badge: the page must not say "Verified" unless the listing is verified. Report any claimed-but-unverified listing that reads "Verified".
- G-02 After approval the Claim button is gone for guest, member and owner within one reload, and `#listora-claim-modal` is not in the DOM (`render.php:938`); the guest login-modal markup stays available only where Claim is offered.
- G-03 The claim modal fits at 390 (about 351 px wide), scrolls inside itself when the keyboard is up, locks `body` scroll while open, and Submit Claim, Cancel and the close button are each at least 40 px tall.
- G-04 Focus: opening the claim modal moves focus inside (not the page behind), Tab cycles close, textarea, file input, Submit, Cancel, Escape closes, and focus returns to the exact Claim button; the same for the login modal.
- G-05 Proof file hint reads "max 5 MB" and the server limit is the same number: a 5 MB + 1 file shows a visible refusal in the message line (not a silent reset), the text typed is kept, and the form stays usable.
- G-06 Double submit: two quick clicks on Submit Claim create one row (the button disables), the second request, if sent, returns 409 `listora_claim_pending` and the UI keeps the success state rather than flipping to an error.
- G-07 The dashboard nav badge on `#dash-tab-claims` equals the total claims of the member (all statuses), the pager shows `Page X of Y` for the same total, and page 999 clamps to the last page, never an empty list.
- G-08 Status chip text and colour classes map one to one: "Under review" = `--pending`, "Approved" = `--publish`, "Not approved" = `--rejected`; the admin badge says "Pending" / "Approved" / "Rejected" for the same claim (the two vocabularies are intentionally different: assert both, do not file as a mismatch).
- G-09 "Edit Listing" on an approved row links to the submit page with `?edit={listing id}` (not to wp-admin); "View Listing" appears for pending and rejected; a deleted listing shows "(listing removed)" with no link and no action button.
- G-10 Admin "N other claims" badge appears only when the same member has two or more other claims or any rejected one, and the drawer lists exactly those other claims.
- G-11 Admin tab counts: All = Pending + Approved + Rejected; the "N claims" footer equals the active tab count; after Approve the redirect lands with Pending minus one, Approved plus one, and the notice text uses the plural form for n > 1 ("2 claims approved. Each listing now belongs to its claimant.").
- G-12 Reverse approval confirm text names the listing, the claimant and the previous owner (or "you" when none is stored), and after confirming the claimant sees "Not approved" and the listing's Claim button returns.
- G-13 Skipped and stale notices: approving a second claim on an already-claimed listing shows the warning notice (class `notice-warning`), a stale link shows the info notice (class `notice-info`), and neither changes `post_author`.
- G-14 Delete row action confirms before acting, removes only the claim row, leaves `post_author` and `_listora_is_claimed` as they were, and a repeat delete from a stale tab reports no fatal and no notice change.
- G-15 Proof column: "None given" in muted text when empty, "N documents" singular for 1 ("1 document"); the proof file link in the drawer is refused (403 or redirect) for a member and for a guest, and works for the admin.
- G-16 The claim success message and the dashboard row agree on the same date format; "Submitted <date>" uses the site date format and timezone near midnight at UTC+14 and UTC-12.

## Known defects (expected-fail)
None. No step in the four Free journeys is marked `expected-fail`; G-01 is a candidate to turn into one if confirmed.

## Not covered yet
- Withdraw or edit a pending claim: no member surface exists; `dashboard-claims-tab.md` asserts only that member writes are refused. Confirm that is intended.
- Claim ownership of a listing that is `pending`, `draft`, `expired` or trashed (does the Claim button render, does approval transfer a non-published listing?).
- Claimant who is also the previous owner's editor/moderator, and a claim by a user whose account is later deleted (admin "Deleted user" row, approve still possible?).
- The rejected-claim email and "Reviewer notes": where a moderator writes the note (admin has Reject but no note field in `claim_row`), and whether the note ever appears in My Claims.
- Claim submitted while Claims is ON then switched OFF before approval; and the 429 `claim_submit` daily limit message in the modal.
- The listing card / grid: does a claimed listing show any marker, and does `search_index.is_claimed` filter or sort anything (`directory-search` / `browse-blocks`)?
