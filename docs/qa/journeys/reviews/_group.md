---
group: reviews
plugin: wb-listora
scope: Write, edit, reply to, vote on, flag, block and moderate reviews; the one public rating number; the Reviews switches, settings and per-type criteria
journeys: 11
site_profile: required
---
# Reviews: what an agent checks here

## Scope
Covers everything a person does with a review: the Reviews tab on the listing page, the standalone Reviews block, the Write a Review form, Helpful / Report / Block / owner Reply, the report dialog, the member dashboard Reviews tab, the wp-admin Reviews moderation screen, the Reviews switches (site Features switch, per-type Reviews and Moderation, Settings > Reviews) and per-type review criteria. It also owns the "one public rating number" contract (header, tab count, card badge, REST, JSON-LD).

Not covered here: the rest of the listing page (`listing-detail`), cards and grids as such (`browse-blocks`), schema output beyond `aggregateRating` (`seo-schema`), the member Block action's effect on contact (`contact-leads`), generic admin table behaviour such as bulk Apply and per-page (`admin-listings`), screen capability matrix (`access-security`), the per-type moderation of LISTINGS (`submission`). Pro photos and aspect averages: `wb-listora-pro/docs/qa/journeys/reviews/_group.md` (run this Free group first).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Listing page, Reviews tab (summary, bars, list, actions) | `$SITE_URL/listing/{listing}/` then `#tab-reviews`; deep links `#reviews`, `#review-{id}`, `#oldest-unanswered` | guest, member, owner, editor, admin | `templates/blocks/listing-detail/tabs.php:457-740` |
| 2 | Listing page header rating and Reviews tab count | same page, header and tab bar | all | `blocks/listing-detail/render.php:500-506`, `tabs.php:114-121` |
| 3 | Reviews block (summary, sort, Write button, list, Load more) | any page holding the `listora/listing-reviews` block on a single listing context | all | `blocks/listing-reviews/render.php`, `templates/blocks/listing-reviews/reviews.php`, `review-card.php` |
| 4 | Write a Review form (hidden until the button) | button `.listora-reviews__write-btn` on 1 or 3 | logged-in non-owner member without a review | `templates/blocks/listing-reviews/review-form.php` (ids `listora-detail-review-*` on 1, `listora-review-*` on 3) |
| 5 | Report This Review dialog | `.listora-reviews__report-btn` / `.listora-detail__review-report-btn` | logged-in member (guest gets the login modal) | `templates/blocks/reviews/report-modal.php` |
| 6 | Member dashboard Reviews tab | `$SITE_URL{dashboard}?tab=reviews[&reviews_page=N&received_page=N]` | {owner}, {other} (own data), guest sees sign-in | `templates/blocks/user-dashboard/tab-reviews.php`, `blocks/user-dashboard/render.php:303-325` |
| 7 | wp-admin Listora > Reviews | `$SITE_URL/wp-admin/admin.php?page=listora-reviews[&status=pending|approved|rejected|all&reported=1]` | {admin}, {editor} (cap `moderate_listora_reviews`) | `includes/admin/class-admin.php:1618` (`render_reviews_page`), `:1818` (`review_row`), notices `:1784-1808` |
| 8 | Review Details drawer (inside 7) | row button **Details** | {admin}, {editor} | `class-admin.php:1915` (`review_detail`) |
| 9 | Listing Types editor, Review criteria, Reviews and Moderation selects | `admin.php?page=listora-listing-types&action=edit&type={type}` | {admin} (`manage_listora_types`) | `includes/admin/class-type-editor.php:443-446` |
| 10 | Settings > Reviews and Settings > Features > Reviews & Ratings | `admin.php?page=listora-settings` (tabs Reviews, Features) | {admin} | `includes/admin/class-settings-page.php`, `class-features-settings-tab.php` |
| 11 | Rating on a listing card and in JSON-LD (read only here, to prove the one number) | `$SITE_URL{directory}` card, page source | all | `blocks/listing-card/render.php`, `includes/schema/class-schema-generator.php` |

## Components on each surface
**1 Reviews tab (`#panel-reviews`, `role=tabpanel`, hidden until selected)**: anchor `span#reviews.listora-detail__review-anchor`; summary `.listora-detail__reviews-summary` (score `.listora-detail__reviews-avg`, stars `.listora-detail__reviews-stars`, total `.listora-detail__reviews-total`, five bar rows `.listora-detail__reviews-bar-row`); list `.listora-detail__reviews-list` with, per review, `div.listora-detail__review#review-{id}`: avatar `img.listora-detail__review-avatar`, author `strong.listora-detail__review-author` or link `--link`, stars, `time` ("x ago"), title `h4`, content `p`, `wb_listora_review_after_content` slot (Pro photos and aspects), Owner Response `.listora-detail__review-reply`, actions row `.listora-detail__review-actions` with Helpful `button.listora-detail__review-helpful-btn`, Block `button.listora-detail__review-block-btn`, Report `button.listora-detail__review-report-btn`, "N people found this helpful" `.listora-detail__review-helpful-summary`; empty state `.listora-detail__reviews-empty`; pending notice `p.listora-reviews__pending-notice`; Write button `.listora-reviews__write-btn`; form (4); login notice `.listora-reviews__login-notice`.

**2 Header**: `span.listora-rating` (aria-label "Rated X out of 5 based on N reviews"), `.listora-rating__star`, value span, `.listora-rating__count`; tab `#tab-reviews` with `.listora-detail__tab-count`.

**3 Reviews block**: `.listora-reviews` wrapper (`data-wp-interactive="listora/directory"`); summary `.listora-reviews__summary` (`.listora-reviews__avg`, `.listora-reviews__avg-stars`, `.listora-reviews__total`, `.listora-reviews__distribution` with five `.listora-reviews__bar-row` = `.listora-reviews__bar-label`, `.listora-reviews__bar-fill`, `.listora-reviews__bar-count`); toolbar `.listora-reviews__toolbar` with `select.listora-reviews__sort` (Most Recent, Highest Rated, Lowest Rated, Most Helpful) and `.listora-reviews__write-btn`; pending notice; form; list `.listora-reviews__list` of `.listora-reviews__review#review-{id}` (header avatar `.listora-reviews__avatar`, `.listora-reviews__reviewer`, `.listora-reviews__review-rating`, `.listora-reviews__review-date`, `h4.listora-reviews__review-title`, `p.listora-reviews__review-content`, actions `.listora-reviews__helpful-btn`, `.listora-reviews__report-btn`, `.listora-reviews__block-btn`, owner reply `.listora-reviews__owner-reply` or `.listora-reviews__reply-btn` for the owner); `.listora-reviews__load-more` when total > per page; empty state `.listora-reviews__empty`; the report modal (5) when any review exists.

**4 Form**: `.listora-reviews__form-wrapper` (hidden), `form.listora-reviews__form`, heading "Write a Review", Your Rating fieldset `.listora-reviews__star-input` (5 radios `name=overall_rating`), Review Title `input[name=title]`, Your Review `textarea[name=content]` (required and minlength from Settings > Reviews), `wb_listora_review_form_after_content` slot (Pro photos), "Rate each aspect" `.listora-reviews__criteria` rows `.listora-reviews__criterion` (radios `criteria_ratings[key]`), CAPTCHA widget, Submit Review, Cancel, message line `.listora-reviews__form-message`.

**5 Dialog**: `#listora-report-review-modal` > `.listora-detail__modal-backdrop`, `#listora-report-review-dialog[role=dialog][aria-modal=true]`, close button, title "Report This Review", description, `select#listora-report-review-reason` (7 reasons), Submit Report, Cancel.

**6 Dashboard tab**: nav `#dash-tab-reviews` (badge), panel `#dash-panel-reviews`; "Reviews I've Written" list `.listora-dashboard__review-list` rows `.listora-dashboard__review-row` (stars, listing link `.listora-dashboard__review-listing-link`, date, status chip `.listora-dashboard__status--pending|--rejected`, title, 30-word excerpt); pager "Reviews written pagination"; "Reviews on My Listings" rows with Reply trigger `.listora-dashboard__reply-trigger`, owner reply blockquote `.listora-dashboard__owner-reply`, inline form `.listora-dashboard__reply-form` (textarea `.listora-dashboard__reply-textarea`, error `.listora-dashboard__reply-error`, Submit reply, Cancel); pager "Reviews received pagination"; empty state "No reviews yet" with "Find a listing to review".

**7 Admin screen**: shared header "Reviews"; notice area; filter tabs All / Pending / Approved / Rejected with counts; search "Search listing or review text"; filter selects Any rating, Any listing type, Any time, Owner reply: any, Reported or not; bulk bar (Approve, Reject, Delete) + select-all; table columns Review (title, 18-word clamp, "Owner replied" and "Reported" badges), Listing, Author (name + email, "Deleted user (#id)", "Guest"), Rating (sortable), Status badge, Date (sortable); row actions Approve, Reject, Details, `...` menu with Delete (confirm); footer count "N reviews", per-page control, pagination; empty state "No reviews waiting" / "No reviews yet".

**8 Drawer**: native `<dialog class="listora-drawer" id="reviews-detail-{id}">`: `.listora-rating[role=img]`, reviewer name and email, title `h3`, full text, Owner reply block or "You can reply once the review is approved." / `.listora-reply-form` (textarea `.listora-reply-textarea`, Post reply `.listora-reply-submit`).

**9 Type editor**: Review criteria repeater, `#listora-type-reviews` (Use the site setting / On / Off), `#listora-type-moderation`, Save Type.

## States to force
| Surface | State | How |
|---|---|---|
| 1, 3 | empty (N=0), one review, many (> per page 20 on tab / 10 in block) | clean `${PREFIX}listora_reviews` for a QA listing; seed QA-prefixed rows (`title LIKE 'QA-J1 %'`) |
| 1, 3 | logged-out, member, member who already reviewed (pending notice), owner (no Write), blocked reviewer | autologin one persona per browser context; `POST /me/blocks` |
| 1, 3 | reviews off for the type / site | type select "Off for this type"; Settings > Features untick Reviews & Ratings |
| 1, 3 | unnamed reviewer, deleted reviewer (`user_id` 999999), anonymised (`user_id` 0) | `wp eval` update of one review row, restore after |
| 1, 3 | long title (120 chars), long unbroken word, emoji, markup in content | seed a review row |
| 4 | min length N / 1 / 0, auto-approve on/off, one-per-listing on/off | Settings > Reviews |
| 5 | open, reason empty, success, 409 duplicate | per `flag-a-review-dialog-and-reasons` |
| 6 | mixed statuses, > 20 written, > 20 received, none | seed per `member-reviews-tab-agrees` |
| 7 | pending default, empty status, reported filter, 2000+ rows, stale link | seed; reuse an Approve link twice |
| all | dark, 390, RTL if the site is RTL | profile themes; resize; `data-bx-mode=dark` |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| `wb_listora_features['reviews']` (Settings > Features > Reviews & Ratings) | on / off | surfaces 1-4, 11 lose tab, header rating, card badge, JSON-LD `aggregateRating`; REST `403 listora_reviews_disabled` |
| Listing type Reviews (`global` / on / off) | 3 | same as above, for that type only; site switch wins over "On" |
| Listing type Moderation (`global` / manual / auto) | 3 | review is unaffected; LISTING status on submit (see `reviews-and-moderation-switches-take-effect`) |
| `wb_listora_settings['reviews']['min_length']` | 0, 1, N | textarea `required`/`minlength`, placeholder text, REST 400 |
| `...['auto_approve']` | on / off | 201 `approved` + page reload vs `pending` + "Awaiting approval" |
| `...['one_per_listing']` | on / off | 409 `listora_already_reviewed` vs 201; Write button visibility |
| `...['allow_reply']` | on / off | Reply UI on dashboard tab and block, `403 listora_replies_disabled` |
| `wb_listora_dashboard_per_page` filter | int | dashboard pagers |
| `wb_listora_detail_reviews_limit` filter (default 20) | int | number of reviews on surface 1 |
| `wb_listora_review_report_reasons` filter | array | dialog options and REST enum together |

## Fixtures and setup
- `wp eval-file $WP_PATH/wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed` creates the {listing} with `review_published` and `review_pending`; run `clean` afterwards.
- Seed extra QA rows with titles `QA-J1 <status>`; remove with `DELETE FROM ${PREFIX}listora_reviews WHERE title LIKE 'QA-J1 %'` then `Listing_Data::recompute_rating_aggregate( LISTING_ID )`.
- Flush the object cache before reading dashboard counts (`wp cache flush`). One browser context per persona (autologin is ignored while a session cookie exists).
- Restore afterwards: `reviews.min_length=20`, `auto_approve=false`, `one_per_listing`, `allow_reply`, the type Reviews/Moderation selects, the Features switch, any block, any edited review `user_id`.

## Run order
1. `reviews/write-and-reply-to-a-review.md` - browser, critical: write, approve, vote, reply, edit, criteria, scale.
2. `reviews/review-form-settings-take-effect.md` - browser, high: min length, auto-approve, one per listing, replies.
3. `reviews/reviews-deep-link-tab-switching.md` - browser, high: hash targets and tab switching.
4. `reviews/flag-a-review-dialog-and-reasons.md` - mixed, high: report dialog, reason enum.
5. `reviews/reviewer-name-member-no-login-leak.md` - mixed, high: "Member", no login in links, embeds, feeds.
6. `reviews/blocking-a-reviewer-hides-review-not-total.md` - mixed, high: one rating number, block filtering.
7. `reviews/member-reviews-tab-agrees.md` - mixed, high: dashboard counts and chips.
8. `reviews/moderate-review.md` - browser, critical: admin screen, drawer, aggregate recompute, hook.
9. `reviews/reviews-and-moderation-switches-take-effect.md` - mixed, critical: type and site switches on every surface.
10. `reviews/listing-type-review-criteria-unique-keys.md` - mixed, normal: duplicate criteria keys.
11. `reviews/review-actions-gated-on-every-route.md` - api, critical: one gate on every route.
Then run: `wb-listora-pro/docs/qa/journeys/reviews/_group.md`.

## Group-specific micro checks
- G-01 The Reviews block average reads one decimal like the listing page. `.listora-reviews__avg` prints the raw float (`$avg ?: '—'`, `reviews.php:44`) while `.listora-detail__reviews-avg` uses `number_format( $avg, 1 )`: a 4.0 average must read "4.0" on both, not "4" on one.
- G-02 N=1 plural: header `span.listora-rating[aria-label]` must read "based on 1 review", not "1 reviews" (`blocks/listing-detail/render.php:502` has no `_n`). Tab total `.listora-detail__reviews-total` and `.listora-reviews__total` read "1 review".
- G-03 Bar rows: the five counts in `.listora-detail__reviews-bar-row` (or `.listora-reviews__bar-count`) sum to the total in `.listora-detail__reviews-total`; each fill width equals `round( count / total * 100 )`; a 0-count row has width 0 and stays visible.
- G-04 The `#tab-reviews` count badge equals `.listora-detail__reviews-total` and the header `.listora-rating__count` for a guest; for a viewer who blocked a reviewer the badge and header keep the full count while the list shows one fewer (by design: assert they differ by exactly the blocked count).
- G-05 When a blocking viewer has blocked every reviewer, surface 1 shows the empty state ("No reviews yet") while the header still shows the rating and count (the summary is skipped when the list is empty, `tabs.php:469`). Expect a coherent message; report the mismatch.
- G-06 The listing-page Reviews tab lists at most 20 reviews (`wb_listora_detail_reviews_limit`) with no sort and no Load more, while the block has sort and Load more: with 25 approved reviews the tab count says 25 but 20 rows render. Report as a gap if no hint explains the missing five.
- G-07 Review date format differs by surface: tab shows relative `time` ("3 days ago", `datetime` attribute in UTC), block and dashboard show `date_format`. Check `datetime` equals the stored `created_at` in UTC and the visible text follows the site timezone at UTC+14 and UTC-12.
- G-08 Helpful: after a vote the button text reads "Helpful (n)", is disabled and `is-voted`; the "n people found this helpful" line on the tab agrees with n and uses the singular for n=1; reload keeps the count but a returning voter's button state is the server's answer (second click 409, message shown, no duplicate).
- G-09 Own-review controls: on a member's own review there is no Block and no Report button (`tabs.php` Block and Report conditions); Helpful is shown but returns `403 listora_own_review` with a visible message, not a silent failure.
- G-10 Report dialog focus: opening focuses `#listora-report-review-dialog` (tabindex -1), Tab stays inside (select, Submit, Cancel, close), Escape and backdrop restore focus to the exact `.listora-reviews__report-btn` that opened it, and two reviews opened in sequence report their own ids (check the POST url ids).
- G-11 Star radios: each rating radio group exposes "N star(s)" via `.listora-sr-only` with the correct singular ("1 star", "2 stars"); the selected star and every lower star fill together on hover and checked; keyboard arrows change the value; visible focus ring on the checked star.
- G-12 Textarea contract: `required` and `minlength` appear only when `min_length` > 0; placeholder reads "(minimum 1 character)" singular for 1; label asterisk disappears at 0; the counter/placeholder alignment and one-line placeholder at 1440 (card 10351338091).
- G-13 Pending states agree: after submit with auto-approve off, the form shows "Review submitted and pending approval." and the button label "Awaiting approval" and stays disabled; on reload the tab shows `.listora-reviews__pending-notice` and no Write button; the dashboard row shows the "Awaiting approval" chip; the admin Pending tab count rose by exactly one.
- G-14 Dashboard badge arithmetic: `#dash-tab-reviews` badge equals visible written + received (spam excluded, pending and rejected included for the author); both pagers carry separate args (`reviews_page`, `received_page`) and paging one never resets the other.
- G-15 Dashboard chips: only pending ("Awaiting approval") and rejected ("Not published") rows carry a chip; an approved row has none; a deleted listing row reads "Deleted listing" without a link.
- G-16 Reply form: opening hides the Reply trigger and the existing "Your reply:" blockquote; Cancel restores both with the text intact; the submit label swaps between "Submit reply" and "Submitting..." with exactly one visible; a 500 shows `.listora-dashboard__reply-error` and re-enables the button.
- G-17 Admin tab counts: All = Pending + Approved + Rejected; the "N reviews" line under the table equals the active tab count (or the filtered count); after Approve the Pending tab drops by one and Approved rises by one on the redirect page.
- G-18 Admin row badges: a review with an owner reply shows "Owner replied", a reported one "Reported"; the "Reported by visitors" filter lists exactly the rows with the Reported badge; Approve is absent on approved rows and Reject absent on rejected rows.
- G-19 Rating column sort: ascending/descending sort by Rating orders by stars and breaks ties by id; the header's `aria-sort` follows; the sort survives a filter change and the status tab.
- G-20 Deep links: `#review-{id}` scrolls the review into view below any sticky header (not hidden under it at 390), and the opened Reviews tab has `aria-selected=true` while Overview is hidden; the URL normalises to `#reviews`.
- G-21 Unnamed reviewer: avatar `alt="Member"`, the name is plain text with no `href`, and the `title`/tooltip does not contain the login; the admin Author cell may show the real name (control).
- G-22 Criteria stars on a published review: one `li.listora-review-criteria__item` per stored key, label from the type config, stars `aria-label="Rated N out of 5"`; a review without criteria adds no empty `ul`.

## Known defects (expected-fail)
| Journey | Step | Defect |
|---|---|---|
| `reviews/review-form-settings-take-effect.md` | 3 | Listing-page form ignores `min_length` 0: `submitDetailReviewForm()` returns early on empty content (`src/interactivity/store.js:2870`) while the block form accepts a rating-only review |
| `reviews/write-and-reply-to-a-review.md` | none | no `expected-fail` steps; G-01 and G-02 are new candidates found in the templates, not yet in a journey |

## Not covered yet
- Review EDIT from the UI: no surface renders an edit control for the author (only `PUT /reviews/{id}`), and `reviews/review-actions-gated-on-every-route.md` covers REST only. Expected owner: a browser journey once an edit UI exists, or a documented exception.
- Sort control on the block (`select.listora-reviews__sort`, `?review_sort=`) and Load More Reviews (`actions.loadMoreReviews`): no journey asserts order for newest, highest, lowest, helpful, nor the appended page or the end of the list.
- Pagination of the REST list (`has_more`, `cursor`) and the 100+ review scale seed are Not verified in `write-and-reply-to-a-review.md` step 15.
- Owner Reply from the Reviews block (`.listora-reviews__reply-btn`, `actions.showReplyForm`) and from the listing page: only the dashboard inline reply is journeyed.
- CAPTCHA widget on the review form (`review` and `review-detail` contexts) and the review rate limits (`review_create`, `review_vote`, `review_reply`).
- RTL layout of the Reviews tab, form and dialog (the group lists RTL, no journey runs it) and the Block button's effect on the dashboard counts.
