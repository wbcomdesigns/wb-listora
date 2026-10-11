#!/bin/bash
set -euo pipefail
export PATH="/usr/bin:/bin:/usr/sbin:/sbin:/opt/homebrew/bin:$HOME/.local/bin:$PATH"
PROJ=47045113
TABLE=9827892288
SHOTS="/Users/vapvarun/Local Sites/listora/app/public/wp-content/plugins/wb-listora/docs/qa/work-artifacts/rft-browser-2026-09-29/shots-rel-block"
ENV="listora.local, WP 7.1.2, PHP 8.5.10 / nginx 8.2.29, Reign"
FREE="wb-listora @ 3d1cfbe0"
PRO="wb-listora-pro @ 61ebf79"

comment() {
  local id="$1"
  shift
  basecamp comment "$id" "$1" --in "$PROJ" --json >/dev/null
  echo "commented $id"
}

move_to() {
  local id="$1"
  local col="$2"
  basecamp cards move "$id" --to "$col" --card-table "$TABLE" --in "$PROJ" --json >/dev/null
  local got
  got=$(basecamp cards show "$id" --in "$PROJ" --agent --jq '.parent.title')
  echo "moved $id parent.title=$got"
}

comment 10354947265 "$(cat <<EOF
**RELEASE BLOCK.** An admin who adds a service in wp-admin and clicks Save does not get a row — and this walk also soft-deleted the two services that were already on the listing.

VERDICT: CONFIRMED
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal (block editor is the listing edit screen)
        impact: road-block / data loss
        location: core (Services metabox + save_post)          -> P1
Questions asked   O-3 C-1
Class (§4)                              defect
Roles walked (§1.5)                     admin y (user 1) | member-owner y (dashboard modal)

Repro (listora.local, listing 7281 Core Claim Target, restaurant, services ON):
1. wp-admin → Listings → Core Claim Target (block editor). post_ID=7281.
2. Services metabox: “No services yet” even though wp_listora_services had 292 + 293 active.
3. Type a title in Add new services (QA-REL-SVC-…). Category is a 3-row listbox.
4. Click the editor Save. No error. Title stays in the new row. No new table row.
5. After that Save, 292 and 293 were status=deleted. Dashboard modal then said “No services added yet.”
6. Restored 292/293 to active after the walk.

file:line: \`class-services-metabox.php:458\` \`save_post()\` ignores \`create_service()\` WP_Error; Gutenberg Save did not persist \`wb_listora_services[new]\`. Collateral delete of existing rows is worse than the card text.

Acceptance bar: Add a titled new row, Save. Row appears in existing-services table, in \`wp_listora_services\`, on the listing Services tab, and in the dashboard modal. Existing services survive a Save that only adds one. A failed create shows an admin notice, not “Listing updated” alone.

Not covered: classic-editor save path; why get_services() rendered empty before the Save (post_ID was already 7281).
EOF
)"
basecamp comment 10354947265 "Shot: metabox after Save — title still in the new row, empty existing list." --in "$PROJ" --attach "$SHOTS/02-admin-after-update.png" --json >/dev/null
move_to 10354947265 "Bugs"

comment 10354998955 "$(cat <<EOF
Frontend Edit Service is a single \`<select>\`. Save sends \`categories: [ firstId ]\`. \`Services::update_service()\` then \`set_service_categories()\` deletes every other term. Planted QA-Svc-A+B on service 293 (659+660); \`update_service(293, {title, categories:[659]})\` left only 659. Restored both, then deleted the throwaway terms.

VERDICT: CONFIRMED
Plugin/branch: $FREE            Env: $ENV

Triage  reach: any listing whose services have 2+ categories (admin multi-select can set them)
        impact: road-block / data loss
        location: core (dashboard saveService + set_service_categories)          -> P1
Questions asked   O-3
Class (§4)                              defect
Roles walked (§1.5)                     code + live write as varundubey; dashboard modal for 7281 showed empty because the admin Save above had already tombstoned the rows

file:line: \`store.js:3242-3244\` one-element array; \`class-services.php:359-360\` replace; \`tab-listings.php:643\` single select.

Acceptance bar: A service with two categories, opened and saved from My Listings → Services without changing category, still has two categories.

This is a ship-stopper only on sites that actually assign multiple service categories. This QA site had zero service-cat terms until planted.
EOF
)"
move_to 10354998955 "Bugs"

comment 10354973676 "$(cat <<EOF
CONFIRMED, not a release block. The Category cell is a \`<select multiple size=\"3\">\` listbox (QA-Svc-A / QA-Svc-B visible as two stacked lines) while Type and Status are single-line dropdowns. When there are no terms the cell is “No categories yet”, so row height still differs.

VERDICT: CONFIRMED
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal when service cats exist
        impact: cosmetic
        location: edge (admin metabox)          -> P3
Class (§4)                              ux-expectation
Roles walked (§1.5)                     admin y

file:line: \`class-services-metabox.php\` \`render_category_select()\` (multiple + size 3).

Not a same-day ship-stopper.
EOF
)"
move_to 10354973676 "Bugs"

comment 10355037108 "$(cat <<EOF
Not a plugin defect on this site / WP 7.1.2.

VERDICT: REFUTED
Plugin/branch: $FREE            Env: $ENV

Triage  reach: admin only
        impact: degraded if it were true
        location: edge (core taxonomy UI)          -> not our bug
Class (§4)                              preference / core metabox
Roles walked (§1.5)                     REST live + taxonomy registry

1. Classic Locations box is WordPress \`post_categories_meta_box\` — no search by design. \`class-taxonomies.php:198-221\` sets no custom \`meta_box_cb\`.
2. \`GET /wp/v2/listing-locations?search=Brooklyn\` returns Brooklyn + Brooklyn Heights.
3. Planted unused term QA-Empty-Loc (count=0). Default REST search still returned it. \`hide_empty=true\` is not hiding search hits here.
4. This site has 45 locations, all non-empty; no Lucknow term exists.

Deleted QA-Empty-Loc after the probe.
EOF
)"
move_to 10355037108 "Done"

comment 10355019529 "$(cat <<EOF
CONFIRMED as two stores, not a broken save. Not a release block.

VERDICT: CONFIRMED
Plugin/branch: $FREE            Env: $ENV

Triage  reach: universal
        impact: degraded (admin thinks they changed the pin)
        location: core (address meta vs location taxonomy)          -> P2
Class (§4)                              owner-expectation
Roles walked (§1.5)                     code + listing 37

Listing 37 Flames & Smoke BBQ: \`_listora_address\` is the street JSON (“215 Flatbush Avenue, Brooklyn…”). Taxonomy is Brooklyn / New York / United States. Sidebar prints the address line (\`sidebar.php:120\`), not the term names. Changing the Locations checkboxes updates terms + search facets; it does not rewrite \`_listora_address\`.

Acceptance bar (if product wants them coupled): ticking a city either updates the visible address or the metabox is labeled so an admin knows it only drives Browse-by-Location.
EOF
)"
move_to 10355019529 "Bugs"

comment 10351026889 "$(cat <<EOF
Leaving Possible Bugs: already REFUTED 2026-09-29. Re-checked today as varundubey on \`/my-listings/?tab=profile\`: “Change photo” is \`https://gravatar.com/profile\`, hint mentions Gravatar. BuddyPress is not active on this site, so that is the designed fallback. Not a release block.

VERDICT: REFUTED
Plugin/branch: $FREE            Env: $ENV
Roles walked (§1.5)                     member-owner y (varundubey)
EOF
)"
move_to 10351026889 "Done"

comment 10350563750 "$(cat <<EOF
Release-day wrap: owner already closed this as a plugin scope-out (Reign page-title banner, not Listora markup). Moving to Not Now so it does not sit in Bugs as a ship item.

VERDICT: already closed by owner — not a release block
Plugin/branch: $FREE + $PRO            Env: $ENV
EOF
)"
move_to 10350563750 "Not Now"

comment 10351203714 "$(cat <<EOF
Release-day triage: still true (searchRadius:5, no picker). Copy on Distance unit is honest. **Not a release block** — visitors can Near Me, they just cannot choose 10/25. Keep in Bugs as P2 after today.
EOF
)"

comment 10350972300 "$(cat <<EOF
Release-day triage: confirmed live. Listings = 20 rows, no view toggle. Favorites = card grid, no toggle. **Not a release block** — both tabs work. P2 UX. Stays in Bugs.
EOF
)"

comment 10351203844 "$(cat <<EOF
Release-day triage: token-vocabulary debt. **Not a same-day release block** — directory already renders on Reign. Keep in Bugs for the next token pass.
EOF
)"

comment 10351167457 "$(cat <<EOF
Release-day triage: Analytics admin theming. Feature is often off; screen still works. **Not a release block.** Stays in Bugs.
EOF
)"

echo "===== columns ====="
basecamp cards columns --in "$PROJ" --card-table "$TABLE" --md
echo "===== Bugs ====="
basecamp cards list --in "$PROJ" --column "Bugs" --card-table "$TABLE" --agent --jq '.[] | {id, title}'
echo "===== Possible ====="
basecamp cards list --in "$PROJ" --column "Possible Bugs" --card-table "$TABLE" --md
