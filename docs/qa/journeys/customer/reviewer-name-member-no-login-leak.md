---
journey: reviewer-name-member-no-login-leak
plugin: wb-listora
priority: high
roles: [anonymous, member-owner, member-other, administrator]
covers: [reviewer-name-member, no-login-in-profile-links, oembed-author, feed-creator, listing-owner-name, privacy, 2.0.0-security, bc-10393672565]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Fixtures seeded (docs/qa/fixtures/2.0-fixtures.php seed): '{listing} published' owned by a NAMED member {owner} with one approved review by the UNNAMED member {other}; '{listing} unnamed' owned by {other}"
  - "{other} is UNNAMED: display name equals login, so the public name is empty. Control: {owner} is named"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: ["ora:customer/2.0-reviewer-name-member-no-login-leak.md"]
fails_on: 2394e3c (step 2 oEmbed/profile links)
---

# A member who never chose a display name is "Member", with no login in links, embeds or feeds

## Why
A WordPress login is half of someone's credentials and profile URLs are built from it. Every public member name resolves through `wb_listora_user_public_name()` (display name, or empty while it is still the login); a surface that bypasses it leaks the login.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `LOGIN_UNNAMED` = {other}'s login.
- Code map: `wb_listora_review_author_name()` (`includes/class-template-helpers.php` ~2205), `templates/blocks/listing-detail/tabs.php`, `templates/blocks/listing-reviews/review-card.php` (~32), `Plugin::listing_oembed_author` (`includes/class-plugin.php` ~463), `Plugin::hide_unnamed_member_profile_url` (priority 20).

## Steps

### 1. Reviewer reads "Member" on the listing page (anonymous, 1440)
- **Action**: open `$SITE_URL/listing/{listing}/`, Reviews tab.
- **Expect**: `strong.listora-detail__review-author`="Member"; avatar `img.listora-detail__review-avatar` `alt="Member"`; author is NOT a link; time reads "x seconds ago"/"x minutes ago" for a fresh review (never "4 hours ago"); stars `Rated 4 out of 5`.
- **Read-back**: DOM/view-source search for `LOGIN_UNNAMED` in the visible page = 0 matches. Positive control: a review by a NAMED member shows their real display name.

### 2. Same on the Reviews block, REST, oEmbed
- **Action**: a page with the Reviews block (`.listora-reviews__reviewer`); `GET /wp-json/listora/v1/listings/<id>/reviews` anonymous; `GET /wp-json/oembed/1.0/embed?url=$SITE_URL/listing/{listing}/`.
- **Expect**: block shows "Member" as `span.listora-reviews__reviewer` (no `--link`, no `href`); JSON `user_name`="Member", `user_profile_url`=""; oEmbed `author_name`/`author_url` do not contain the login. The JSON still exposes a numeric `user_id` (log it; it is not a login).
- **Read-back**: `curl -s $SITE_URL/wp-json/listora/v1/listings/<id>/reviews | grep -c 'LOGIN_UNNAMED'` = 0; `jq '.reviews[]|select(.user_name=="Member")|.user_profile_url'` prints only `""`. Pro's `customer/09` asserts the link only for a NAMED member.
- **fails_on**: 2394e3c. Not verified live.

### 3. Former and deleted members keep their stand-ins
- **Action**: `wp eval 'global $wpdb; $wpdb->update($wpdb->prefix."listora_reviews",["user_id"=>999999],["id"=><review id>]);'`, reload; later `user_id=0`.
- **Expect**: "Former member"; a `user_id` 0 row reads "Anonymous".
- **Read-back**: restore the row; DOM text matches.

### 4. The listing owner's name (anonymous)
- **Action**: open `$SITE_URL/listing/{listing}-unnamed/` (owner {other}, no public contact name) and the named listing.
- **Expect**: no `.listora-detail__owner-name`, no link to `/author/<login>/`, no login in the page, no `author` in JSON-LD. Control: the named listing DOES show {owner}'s name. Opt-in `add_filter( 'wb_listora_owner_name_use_real_name', '__return_true' )` shows first+last name, never the login.
- **Read-back**: `curl` the page and grep the login = 0.

### 5. oEmbed and feeds
- **Action**: `curl -s "$SITE_URL/wp-json/oembed/1.0/embed?url=<unnamed listing url>" | jq '{author_name,author_url}'` and for the named listing; `curl -s "$SITE_URL/listing/feed/"`.
- **Expect**: unnamed: `author_name`/`author_url` absent; named: `author_name`={owner}'s display name, `author_url` = the LISTING URL (not `/author/...`); feed `<dc:creator>` empty for unnamed, named for the other; the login appears nowhere in the XML; theme bylines (`the_author()`) follow the same rule.
- **Read-back**: `grep -c LOGIN_UNNAMED` on the feed = 0.

### 6. Profile links suppressed for the unnamed
- **Action**: with BuddyPress active open a listing whose owner/reviewer is unnamed; `wp eval 'echo apply_filters("wb_listora_member_profile_url","http://x/members/<login>/", <unnamed user id>);'`.
- **Expect**: no `href` to `/members/<login>/` for reviewer, owner or reply author; filter prints an empty string; named member's link may exist.

### 7. 390px
- **Expect**: the name sits beside the avatar without wrapping into the stars; no horizontal scroll.

## Role ladder
| Rung | Expect |
|---|---|
| anonymous | steps 1-6 |
| member-owner ({owner}) | same strings (owner is not shown reviewers' logins either) |
| member-other | same |
| {admin} (control) | public pages identical; wp-admin Reviews table MAY show the real login/name (moderation tool) |

## Pass criteria
1. Unnamed reviewer reads "Member" with no link; named reads their display name.
2. No public surface (page, block, REST, oEmbed, feed, JSON-LD, byline) contains the unnamed member's login.
3. Former/deleted/guest authors read "Former member" / "Anonymous".
4. Named members are unaffected.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Reviewer shows the login | a surface bypasses `wb_listora_review_author_name` | grep `display_name` in `templates/` and `includes/rest/class-reviews-controller.php` |
| `author_name` in oEmbed | filter not registered | `class-plugin.php` ~416 |
| `/author/<login>/` link | `wb_listora_get_listing_owner_url` ignoring public name | `includes/helpers.php` ~388-460 |

## Not verified
Steps 2 (fails_on run), 3, 6 and 7 live.

## Cleanup
`wp eval-file $WP_PATH/wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php clean`; restore any edited review `user_id`.
