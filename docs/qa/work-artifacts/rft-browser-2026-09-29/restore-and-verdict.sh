#!/bin/bash
set -euo pipefail
export PATH="/usr/bin:/bin:/usr/sbin:/sbin:/opt/homebrew/bin:$HOME/.local/bin:$PATH"
ROOT="/Users/vapvarun/Local Sites/listora/app/public"
ART="$ROOT/wp-content/plugins/wb-listora/docs/qa/work-artifacts/rft-browser-2026-09-29"
SHOTS="$ART/shots-rft-two"
PROJ=47045113
TABLE=9827892288
cd "$ROOT"

wp eval '
global $wpdb;
$n = $wpdb->query("UPDATE {$wpdb->prefix}listora_reviews SET owner_reply = NULL, owner_reply_at = NULL WHERE id = 14");
echo "review14_cleared=$n\n";
foreach (array(7546,7547,7548) as $id) {
  $r = wp_delete_post($id, true);
  echo "deleted_$id=".($r ? "yes" : "no")."\n";
}
wb_listora_pro_set_feature("reverse_listings", false);
echo "reverse_after=". (wb_listora_pro_feature_enabled("reverse_listings") ? "YES" : "NO") ."\n";
' --path=. 2>/dev/null | grep -vE '^(Failed|Xdebug|Warning|Contact|The Zend|tried:|/Users|symbol|  dlopen|Deprecated|Notice)'

wp rewrite flush --path=. 2>/dev/null | grep -vE '^(Failed|Xdebug|Warning|Contact|The Zend|tried:|/Users|symbol|  dlopen|Deprecated|Notice)'

echo "===== post reply verdict ====="
basecamp comment 10351346512 "$(cat <<'EOF'
A listing owner who opens My Listings → Reviews the normal way (`?tab=reviews`, no hash) now sees the reply land. The button does not sit on “Submitting…”. After submit the page really reloads, “Your reply” is on the review, and the control becomes Edit reply.

VERDICT: PASS
Plugin/branch: wb-listora @ 3d1cfbe0 (contains 14e1a4ef)            Env: listora.local, WP 7.1.2, PHP 8.5.10 / nginx 8.2.29, Reign

Triage  reach: universal
        impact: road-block (reply saved, UI lied)
        location: core (dashboard reviews reply)          -> P1
Questions asked   O-3 P-33 C-47 P-2
Class (§4)                              defect
Roles walked (§1.5)                     member-owner y (subscriber2, the reporter) | journey_owner n (only Edit reply — P-33)

Q1 original reproduces on pre-fix        YES on b2b4a4ea (prior bounce: hash-only replace, marker survived, button stuck)
Q2 fix works by its stated mechanism     YES — store.js:2399-2406 compares pathname+search, then reload(); built store matches
Q3 blast radius swept                    dashboard submitReply; listing-reviews/view.js:229 already location.reload()
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               journeys/customer/03-write-and-reply-to-a-review.md + C-47

Evidence: headed Chromium, subscriber2, 1440. Start URL `/my-listings/?tab=reviews` hash empty, 10 Reply buttons. After submit: replyNav true, `window.__marker` gone, hasYourReply true, editReply true, not stuck. Edit reply also reloaded (marker wiped). DB review 14 listing 37 stored then edited the token; restored to NULL after this walk. Shots 01-reply-after.png (Your reply + Edit reply) and 02-edit-reply-after.png.
Not covered: 390px (same JS path); listing-page reply form not re-clicked this pass
Question added: none — P-33 and C-47 already in owner-questions from the bounce
EOF
)" --in "$PROJ" --attach "$SHOTS/01-reply-after.png" --attach "$SHOTS/02-edit-reply-after.png" --json >/dev/null
echo "commented 10351346512"

basecamp cards move 10351346512 --to Done --card-table "$TABLE" --in "$PROJ" --json >/dev/null
REPLY_COL=$(basecamp cards show 10351346512 --in "$PROJ" --agent --jq '.parent.title')
echo "moved 10351346512 parent.title=$REPLY_COL"

echo "===== post needs verdict ====="
basecamp comment 10350113286 "$(cat <<'EOF'
A buyer whose request has leftover count meta (2) and zero quote rows is no longer treated as if vendors quoted. My Needs says “0 responses” and offers Delete; Confirm trashes the request (`need_deleted=1`, “Your request was deleted.”). Edit says “You are editing your request.” with no quotes banner. Admin Quotes prints 0. REST PATCH does not stamp after-quotes; REST DELETE returns `{deleted: true}`.

VERDICT: PASS
Plugin/branch: wb-listora-pro @ 61ebf79 (contains 1cfb2e3)            Env: listora.local, WP 7.1.2, PHP 8.5.10 / nginx 8.2.29, Reign

Triage  reach: universal when reverse_listings is ON
        impact: road-block (empty request closed; fake “vendors were told”)
        location: core (need count gates)          -> P1
Questions asked   O-3 T-43 P-2
Class (§4)                              defect
Roles walked (§1.5)                     member-owner y (journey_owner) | admin y (user 1)

Q1 original reproduces on pre-fix        could not checkout; planted the reported fixture (meta=2 / 0 rows) on HEAD
Q2 fix works by its stated mechanism     YES — every leftover gate calls Need_Response_Manager::get_count_for_need() live COUNT, not `_listora_need_response_count`. Meta stays 2 while live total is 0
Q3 blast radius swept                    post-need.php:58 banner; class-reverse-listings.php:171 Delete; :849 admin Quotes batch; class-needs-controller.php:767 PATCH and :840 DELETE. `_listora_need_response_count` is write-only cache now (sync_response_count)
Q4 owner + end user would call it fixed  YES
Q5 regression guard exists               journeys/regression/stale-need-count-gates.md (T-43)

Evidence: headed Chromium 1440. reverse_listings was OFF on this site (pages 404); enabled for the walk, then restored OFF. Planted needs 7546/7547/7548, then deleted them.
- My Needs: “0 responses”, Delete shown, no quotes banner (03-needs-dash.png)
- Confirm label “Confirm”; redirect `?tab=needs&need_deleted=1`; notice “Your request was deleted.” (04-needs-after-delete.png)
- `/post-need/?edit_need=7548`: status “You are editing your request.” hasQuotesBanner false (05b-need-edit-direct.png)
- Admin Needs → search Open: Quotes column 0 (06-needs-admin-open.png)
- REST PUT 7546: `response_count: 0`; `_listora_need_updated_at` stayed empty
- REST DELETE 7547: `{deleted: true}` not `{closed: true}`
Not covered: 390px; positive control (a need that really has a quote row); dashboard Edit `<a>` click (title “QA-RFT2-stale-need-edit” matches /Edit/, so the walker hit the permalink — used the Edit URL the button points at instead)
Question added: none — T-43 already in owner-questions
EOF
)" --in "$PROJ" --attach "$SHOTS/03-needs-dash.png" --attach "$SHOTS/04-needs-after-delete.png" --attach "$SHOTS/05b-need-edit-direct.png" --attach "$SHOTS/06-needs-admin-open.png" --json >/dev/null
echo "commented 10350113286"

basecamp cards move 10350113286 --to Done --card-table "$TABLE" --in "$PROJ" --json >/dev/null
NEEDS_COL=$(basecamp cards show 10350113286 --in "$PROJ" --agent --jq '.parent.title')
echo "moved 10350113286 parent.title=$NEEDS_COL"

echo "===== RFT left ====="
basecamp cards list --in "$PROJ" --column "Ready for Testing" --card-table "$TABLE" --agent --jq 'length'
