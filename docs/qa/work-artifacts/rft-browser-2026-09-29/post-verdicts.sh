#!/bin/bash
set -euo pipefail
PROJ=47045113
TABLE=9827892288
DONE=9827892300
BUGS=9827892296
ENV="listora.local, WP 7.1.2, PHP 8.5.10, Reign"
FREE="wb-listora @ 029cf976"
PRO="wb-listora-pro @ 399066a"

comment() {
  local id="$1"
  local body="$2"
  basecamp comment "$id" "$body" --in "$PROJ" --json >/dev/null
  echo "commented $id"
}

move_done() {
  local id="$1"
  basecamp cards move "$id" --to Done --card-table "$TABLE" --in "$PROJ" --json >/dev/null
  local col
  col=$(basecamp cards show "$id" --in "$PROJ" --agent --jq '.parent.title')
  echo "moved $id parent.title=$col"
}

move_bugs() {
  local id="$1"
  basecamp cards move "$id" --to Bugs --card-table "$TABLE" --in "$PROJ" --json >/dev/null
  local col
  col=$(basecamp cards show "$id" --in "$PROJ" --agent --jq '.parent.title')
  echo "moved $id parent.title=$col"
}

# 10351012222
comment 10351012222 "$(cat <<EOF
A site owner can add and rename per-type review criteria on Listing Types → Restaurant; + Add Criterion created a fifth row live.

VERDICT: PASS
Plugin/branch: $FREE + $PRO            Env: $ENV

Triage  reach: universal
        impact: road-block (owner could not control criteria)
        location: core (listing types / reviews)          -> P1
Questions asked   O-3 T-2 P-2 C-6
Class (§4)                              defect
Roles walked (§1.5)                     anon n | member-owner n | member-other n | elevated n | admin y

Q1 original reproduces on pre-fix        could not (shared live site; no checkout)
Q2 fix works by its stated mechanism     YES — type-editor.js clones [data-listora-criteria-row]; REST already persisted review_criteria
Q3 blast radius swept                    type editor UI, REST persist path, Pro get_criteria_for_type() reader
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE (manual type-editor add-row)

Evidence: headed shot 01-review-criteria.png; rows 4→5; heading REVIEW CRITERIA present
Not covered: save+frontend review form using the new criterion (did not persist the extra empty row)
Question added: none
EOF
)"
move_done 10351012222

comment 10350405749 "$(cat <<EOF
A listing owner can attach a service photo from My Listings → Services without opening wp-admin.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: degraded
        location: core (dashboard services)          -> P2
Questions asked   O-3 T-2 P-2
Class (§4)                              defect
Roles walked (§1.5)                     anon n | member-owner y | member-other n | elevated n | admin y

Q1 original reproduces on pre-fix        could not (shared live site)
Q2 fix works by its stated mechanism     YES — service_image_id + choose/remove photo on frontend; admin photo cells still present
Q3 blast radius swept                    admin metabox photo, frontend modal, store.js saveService
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               journeys/services/listing-services-crud-and-photos.md (admin path)

Evidence: 11b-service-photo.png Photo / Choose photo / service_image_id; admin metabox 8 photo cells
Not covered: actual media upload + REST persist of a new image
Question added: none
EOF
)"
move_done 10350405749

comment 10350359093 "$(cat <<EOF
An admin can add more than one new service in a single save: + Add Another Service appended a second indexed new-row.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: degraded
        location: core (admin services metabox)          -> P2
Questions asked   O-3 T-2 P-2
Class (§4)                              defect
Roles walked (§1.5)                     admin y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — indexed [new][N] rows; click added row 2
Q3 blast radius swept                    admin metabox add-row JS; save handler comment at class-services-metabox.php
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: 02-services-metabox.png two “Add new service” rows + Remove row on the second
Not covered: saving two new titles in one POST
Question added: none
EOF
)"
move_done 10350359093

comment 10351076301 "$(cat <<EOF
The admin Services metabox now has a Category column. This site has no service-category terms, so the cell honestly says “No categories yet” instead of a dead missing control.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: degraded
        location: edge (admin metabox vs REST that already stored categories)          -> P2
Questions asked   O-3 T-2 P-2
Class (§4)                              owner-expectation
Roles walked (§1.5)                     admin y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — render_category_select() on new and existing rows
Q3 blast radius swept                    admin metabox; frontend service form also has Category
Q4 owner + end user would call it fixed  YES (UI exists; empty taxonomy is a fixture, not a missing field)
Q5 regression guard exists               NONE

Evidence: 02-services-metabox.png Category header + “No categories yet”
Not covered: assigning a real term (no listora_service_cat terms on listora.local)
Question added: none
EOF
)"
move_done 10351076301

comment 10351375357 "$(cat <<EOF
Credits → Payments sections no longer sit flush: Credit Rate, Stripe, PayPal and the packs block have visible gaps on the live settings tab.

VERDICT: PASS
Plugin/branch: $FREE + $PRO            Env: $ENV

Triage  reach: universal
        impact: cosmetic
        location: edge (settings subtab wrappers)          -> P3
Questions asked   O-3 T-2
Class (§4)                              ux-expectation
Roles walked (§1.5)                     admin y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — [data-listora-subtab] > first pane/block gets margin-block-start 1.25rem
Q3 blast radius swept                    credits payments tab only
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: 03-credits-spacing.png + 03b-credits-full.png stacked panes with space between Credit Rate and gateways
Not covered: 390px admin
Question added: none
EOF
)"
move_done 10351375357

comment 10350920183 "$(cat <<EOF
Add Webhook form labels sit inside a padded card (24px padding, ~25px label inset), not flush on the card edge.

VERDICT: PASS
Plugin/branch: $PRO            Env: $ENV (outgoing_webhooks enabled for the walk, then restored OFF)

Triage  reach: site-specific (feature ships OFF)
        impact: cosmetic
        location: edge          -> P3
Questions asked   O-3 T-2
Class (§4)                              ux-expectation
Roles walked (§1.5)                     admin y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — listora-card--padded on class-outgoing-webhooks.php:2871
Q3 blast radius swept                    coupons twin; badges already padded
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: 05-webhook-padded.png; measured padding 24px, labelInset 25
Not covered: edit-existing webhook view
Question added: none
EOF
)"
move_done 10350920183

comment 10350743383 "$(cat <<EOF
Add Coupon form labels sit inside a padded card (24px padding, ~25px label inset).

VERDICT: PASS
Plugin/branch: $PRO            Env: $ENV

Triage  reach: universal (monetization on this site)
        impact: cosmetic
        location: edge          -> P3
Questions asked   O-3 T-2
Class (§4)                              ux-expectation
Roles walked (§1.5)                     admin y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — listora-card--padded on class-coupons.php:1471
Q3 blast radius swept                    webhooks twin
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: 04-coupon-padded.png; padding 24px, labelInset 25
Not covered: edit-existing coupon
Question added: none
EOF
)"
move_done 10350743383

comment 10350436293 "$(cat <<EOF
My Needs shows one heading (dashboard H1). The panel H3 “My Needs” is gone. Edit/View listing actions measure 40px / font-weight 500 on both <a> and <button>.

VERDICT: PASS
Plugin/branch: $FREE + $PRO            Env: $ENV

Triage  reach: universal
        impact: cosmetic
        location: core (dashboard)          -> P3
Questions asked   O-3 T-2 P-2
Class (§4)                              ux-expectation
Roles walked (§1.5)                     member-owner y | admin n

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — tab-needs.php dropped H3; button.css a.listora-btn twin for weight
Q3 blast radius swept                    My Responses heading twin; listing row actions
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: 12-my-needs.png H1 only; 10-listing-action-btns.png Edit/View 40px fw 500
Not covered: My Needs row Edit/Delete (this owner has zero needs)
Question added: none
EOF
)"
move_done 10350436293

comment 10351487141 "$(cat <<EOF
My Responses is a single H1. The panel no longer prints a second “My Responses” H3 (nav label at :96 is the tab, not a heading).

VERDICT: PASS
Plugin/branch: $PRO            Env: $ENV

Triage  reach: universal
        impact: cosmetic
        location: edge          -> P3
Questions asked   O-3 T-2
Class (§4)                              ux-expectation
Roles walked (§1.5)                     member-owner y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — class-my-responses-dashboard-tab.php dropped panel H3
Q3 blast radius swept                    My Needs twin
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: 13b-my-responses.png title “My Responses” once; DOM h1s [My Listings, My Responses], no panel H3
Not covered: 390px
Question added: none
EOF
)"
move_done 10351487141

comment 10350758450 "$(cat <<EOF
Transactions Credits/Payments tablist has overflow-y:hidden. Measured offsetH 48 / clientH 47 / scrollH 48 — no meaningful vertical scrollbar.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: cosmetic
        location: edge          -> P3
Questions asked   O-3 T-2
Class (§4)                              ux-expectation
Roles walked (§1.5)                     admin y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — tabs.css overflow-y:hidden; admin hub-tabs twin
Q3 blast radius swept                    frontend .listora-tabs__list + admin .listora-hub-tabs
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: 06-transactions-tabs.png; overflowY hidden, hasVScroll false
Not covered: 390px admin
Question added: none
EOF
)"
move_done 10350758450

comment 10351203714 "$(cat <<EOF
Distance unit copy is now honest (“listing cards and search results”). The card title is still true: Near Me stays hardcoded at searchRadius:5 with no radius control on Settings or the directory.

VERDICT: BOUNCE
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: degraded
        location: core (search)          -> P2
Questions asked   O-3 T-2 P-2 Q1
Class (§4)                              defect (title) / owner-expectation (no control)
Roles walked (§1.5)                     admin y | anon y (directory Near Me)

Q1 original reproduces on pre-fix        YES on current HEAD — no radius UI
Q2 fix works by its stated mechanism     YES for option (a) copy only; option (b) not done
Q3 blast radius swept                    settings General; store.js:436; directory Near Me button
Q4 owner + end user would call it fixed  NO — a visitor still cannot choose a distance
Q5 regression guard exists               NONE

Evidence: settings honestCopy true, hasRadiusControl false; directory radiusInput false, Near Me present; store.js searchRadius: 5
Not covered: measuring the actual 5km/mi query
Repro: Settings → General → Distance unit has no radius field. Directory → Near Me. No picker.

Question added: none — remaining work is the radius control the title asked for, not another copy pass
EOF
)"
move_bugs 10351203714

comment 10351338091 "$(cat <<EOF
The review textarea placeholder is a single clean sentence with no leaked indent/newlines.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: cosmetic
        location: core (review form)          -> P3
Questions asked   O-3 T-2 P-2
Class (§4)                              defect
Roles walked (§1.5)                     member-other y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — placeholder attribute kept on one line in tabs.php + review-form.php
Q3 blast radius swept                    listing-detail tabs form + listing-reviews block template
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: textarea.placeholder = “Share your experience (minimum 20 characters)”; leadingWs false
Not covered: listing-reviews standalone block on its own page
Question added: none
EOF
)"
move_done 10351338091

comment 10350524500 "$(cat <<EOF
Active and Pending stat tiles are different URLs with listings_status=publish vs pending, and they no longer carry switchDashTab.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: degraded
        location: core (dashboard)          -> P2
Questions asked   O-3 T-2 P-2
Class (§4)                              defect
Roles walked (§1.5)                     member-owner y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — real navigation + listings_status; no data-wp-on--click intercept
Q3 blast radius swept                    both tiles; Next-steps links already had the param
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: activeHref /my-listings/?tab=listings&listings_status=publish; pendingHref …=pending; sameHref false
Not covered: clicking through with a pending listing on this owner (they have none)
Question added: none
EOF
)"
move_done 10350524500

comment 10350931568 "$(cat <<EOF
Confirm buttons now read --listora-primary / --listora-danger, and listora-confirm depends on listora-variables + the theme bridge. Live token --listora-primary is Reign #1d76da; --listora-accent is empty. Deactivate confirm danger button measured rgb(220, 38, 38).

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: cosmetic
        location: core (confirm modal)          -> P3
Questions asked   O-3 T-2 P-2
Class (§4)                              ux-expectation
Roles walked (§1.5)                     member-owner y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — confirm.css uses --listora-primary; enqueue deps in class-assets.php
Q3 blast radius swept                    deactivate confirm (same overlay). Reactivate primary not clicked (would hide the listing)
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: 15b-confirm.png; tokenPrimary #1d76da; danger bg rgb(220, 38, 38)
Not covered: leaving a listing inactive to screenshot Reactivate itself
Question added: none
EOF
)"
move_done 10350931568

comment 10351182660 "$(cat <<EOF
When every social platform is disabled, Field::social_link_platforms() returns []. The submission renderer returns before the “Social Links” heading. Verified the empty list live via wp eval, then restored the seven default platforms.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: site-specific (only when owner unticks every platform)
        impact: degraded
        location: core (submission)          -> P2
Questions asked   O-3 T-2 P-2
Class (§4)                              defect
Roles walked (§1.5)                     code + admin setting path; headed empty-state walk failed to start after the toggle (NODE ESM), so PHP empty-list is the live proof

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — early return in submission-field-renderer.php:105
Q3 blast radius swept                    admin metabox uses the same Field::social_link_platforms()
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: wp eval after social_platforms=[] → platforms={}; restore from rft-batch-snap.json
Not covered: headed wizard with all platforms off (script failed to launch after the toggle)
Question added: none
EOF
)"
move_done 10351182660

comment 10351358128 "$(cat <<EOF
Directory cards show “Listed by …” the same way the single sidebar does. Feature owner_name is ON.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: degraded
        location: core (listing cards)          -> P2
Questions asked   O-3 T-2 P-2
Class (§4)                              defect
Roles walked (§1.5)                     anon y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — prepare_card_data() now passes owner_name; card-content.php prints Listed by
Q3 blast radius swept                    grid cards; featured samples; detail sidebar already had it
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               tests/integration/ListingOwnerNameTest.php (name resolver, not card template)

Evidence: 5 cards with “Listed by Sam Subscriber / QA Vendor 01 / QA Vendor 03”
Not covered: feature OFF (did not flip owner_name)
Question added: none
EOF
)"
move_done 10351358128

comment 10350942570 "$(cat <<EOF
Edit and View (anchors) match the button siblings at 40px height, font-weight 500, 14.875px.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: cosmetic
        location: core (dashboard actions)          -> P3
Questions asked   O-3 T-2 P-2
Class (§4)                              ux-expectation
Roles walked (§1.5)                     member-owner y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — a.listora-btn.listora-btn--sm twin in button.css
Q3 blast radius swept                    Apply/Services/More also 40px
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               journeys/theme-presentation/sm-button-tap-target.md

Evidence: Edit A 40/500, View A 40/500, More BUTTON 40/500
Not covered: 390px
Question added: none
EOF
)"
move_done 10350942570

comment 10350859556 "$(cat <<EOF
A refresh still discards wizard input. autoSaveDraft() is still unused. The shipped change is only beforeunload after step 1 — that does not keep data, and this walk never left step 1 so the warn did not fire either.

VERDICT: BOUNCE
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: road-block (lost listing + media)
        location: core (submission wizard)          -> P1
Questions asked   O-3 T-2 P-2 Q1 Q4
Class (§4)                              defect
Roles walked (§1.5)                     member-owner y

Q1 original reproduces on pre-fix        YES on HEAD — refresh returns to an empty wizard
Q2 fix works by its stated mechanism     partial — beforeunload exists at view.js:3262 but does not persist; unused autoSaveDraft() still has zero call sites
Q3 blast radius swept                    src definition vs callers (only the definition)
Q4 owner + end user would call it fixed  NO — “I refreshed and lost everything” still happens
Q5 regression guard exists               NONE

Evidence: wizard.step 0 after type click; beforeUnloadFired false; afterReload title empty. Code comment on the card admits autosave is unwired.
Repro: Add Listing → pick a type → fill later steps → refresh. Data is gone. A Leave warning (if they got past step 1) is not a restore.

Question added: none
EOF
)"
move_bugs 10350859556

comment 10350895542 "$(cat <<EOF
Sakura House hours render as a listora-hours-table (Sun–Sat with 11:00 am ranges). The empty Place Details tab is gone; hours live in the sidebar, not a blank tab.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: road-block (hours invisible)
        location: core (listing detail)          -> P1
Questions asked   O-3 T-2 P-2
Class (§4)                              defect
Roles walked (§1.5)                     anon y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — tabs.php uses wb_listora_normalize_hours() for group visibility and field render
Q3 blast radius swept                    tabs.php + sidebar.php hours table (sidebar still gates on !empty raw meta; this listing has real hours)
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: curl of /listing/sakura-house/ contains listora-hours-table Monday 11:00 am – 10:00 pm; normalize_hours returns 7 ranges
Not covered: a listing whose raw meta is a non-empty empty-shape (the original false-positive group)
Question added: none
EOF
)"
move_done 10350895542

comment 10351013467 "$(cat <<EOF
switchDashTab deletes action and id from the URL before replaceState. After a listings→credits switch the address bar is ?tab=credits with no action/id.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: degraded
        location: core (dashboard JS)          -> P2
Questions asked   O-3 T-2 P-2
Class (§4)                              defect
Roles walked (§1.5)                     member-owner y

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — view.js:71-72 searchParams.delete('action'|'id')
Q3 blast radius swept                    client tab switch only (server also dropped action on the edit deep-link load)
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: after URL http://listora.local/my-listings/?tab=credits; source delete calls present
Not covered: switching away while still on frontend add-listing?action=edit (that is a different page)
Question added: none
EOF
)"
move_done 10351013467

comment 10351346512 "$(cat <<EOF
Could not press Reply on My Listings → Reviews as journey_owner, journey_admin, or (script failed) varundubey. The claimed reload-vs-replace fix is in store.js but this walk never submitted a reply.

VERDICT: BLOCKED
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: road-block if still stuck
        location: core (reviews reply)          -> P1
Questions asked   O-3 P-2
Class (§4)                              defect
Roles walked (§1.5)                     member-owner y (no Reply button) | admin y (no Reply button)

Q1 original reproduces on pre-fix        could not reach the control
Q2 fix works by its stated mechanism     untested live — store.js:2393 reloads when URL is unchanged
Q3 blast radius swept                    code only
Q4 owner + end user would call it fixed  unknown
Q5 regression guard exists               journeys/reviews/write-and-reply-to-a-review.md

Evidence: hasReply 0 on ?tab=reviews for journey_owner and journey_admin
Need: a listing this persona owns that already has an approved review, or autologin for the Golden Fork owner with a visible Reply button
Not covered: the submit→reload path
Question added: none
EOF
)"

comment 10351400338 "$(cat <<EOF
An admin opening /add-listing/?action=edit&id=7264 sees “QA owned listing” already in the title field, not a blank wizard.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: road-block
        location: core (submission edit gate)          -> P1
Questions asked   O-3 T-2 P-2
Class (§4)                              defect
Roles walked (§1.5)                     admin y (edit_others_posts)

Q1 original reproduces on pre-fix        could not
Q2 fix works by its stated mechanism     YES — render.php allows current_user_can('edit_others_posts')
Q3 blast radius swept                    frontend wizard; wp-admin post.php is a different surface (block editor)
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: 08b-admin-frontend-wizard.png titleValue “QA owned listing”; filled listing_type + features
Not covered: editor role (journey_editor)
Question added: none
EOF
)"
move_done 10351400338

comment 10350135718 "$(cat <<EOF
At 390px the map notice is 333px wide, two lines, not clipped. Re-applying the old width:auto shrinks it to 179px / 80px tall — the bounce of max-width-only.

VERDICT: PASS
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: degraded
        location: core (directory map)          -> P2
Questions asked   O-3 T-2 P-2
Class (§4)                              ux-expectation
Roles walked (§1.5)                     anon y

Q1 original reproduces on pre-fix        YES via CSS revert (179×80)
Q2 fix works by its stated mechanism     YES — width: calc(100% - 1.5rem) on .listora-map__notice
Q3 blast radius swept                    390 only (the reported viewport)
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               NONE

Evidence: 18-map-notice-390.png; live w=333 clipped=false; reverted w=179
Not covered: 1440 caption (already full width)
Question added: none
EOF
)"
move_done 10350135718
