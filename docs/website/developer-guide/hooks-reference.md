# Hooks Reference (Actions & Filters)

Every `wb-listora` action and filter, generated from `audit/manifest.json` at 1.7.0. The plugin fires **328 hooks** - **151 actions** + **177 filters**.

Regenerate with `bin/build-hooks-reference.py <plugin-dir>` after a manifest
refresh. Do not hand-edit: this file is overwritten.

`Consumed by` lists sibling plugins already listening, so you can see which
hooks have proven wiring before you rely on one.

## Bootstrap (2)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_loaded` | action | _(none)_ | `includes/class-plugin.php:49` | `wb-listora-pro` |
| `wb_listora_rest_api_init` | action | _(none)_ | `includes/class-plugin.php:251` | `wb-listora-pro` |

## Listings (64)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_account_deactivate_listing_statuses` | filter | _(none)_ | `includes/privacy/class-account-manager.php:248` | - |
| `wb_listora_account_deletion_listing_strategy` | filter | string $strategy, int $user_id | `includes/privacy/class-account-manager.php` | - |
| `wb_listora_after_approve_listing` | action | WP_Post\|int $post->ID | `wb-listora.php:406` | - |
| `wb_listora_after_create_listing` | action | int $post_id, WP_REST_Request $request | `includes/rest/class-submission-controller.php:512` | `wb-listora-pro` |
| `wb_listora_after_dashboard_listings` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-listings.php:404` | - |
| `wb_listora_after_deactivate_listing` | action | int $post_id, WP_REST_Request $request | `includes/rest/class-listings-controller.php:970` | - |
| `wb_listora_after_delete_listing` | action | int $post_id, WP_REST_Request $request | `includes/rest/class-listings-controller.php:876` | `wb-listora-pro` |
| `wb_listora_after_feature_listing` | action | int $post_id, mixed($context) $context | `includes/core/class-featured.php:179` | - |
| `wb_listora_after_featured_listings` | action | mixed($featured_block_attributes) $featured_block_attributes | `blocks/listing-featured/render.php:96` | - |
| `wb_listora_after_listing_card` | action | mixed $id, mixed $layout, mixed $show_rating, mixed $show_favorite, mixed $show_type | `blocks/listing-card/render.php:197` | - |
| `wb_listora_after_listing_fields` | action | int $post_id, mixed($detail_type_slug) $detail_type_slug | `templates/blocks/listing-detail/sidebar.php:70` | `wb-listora-pro` |
| `wb_listora_after_listing_grid` | action | mixed($grid_block_attributes) $grid_block_attributes | `blocks/listing-grid/render.php:216` | - |
| `wb_listora_after_reactivate_listing` | action | int $post_id, WP_REST_Request $request | `includes/rest/class-listings-controller.php:1106` | - |
| `wb_listora_after_reject_listing` | action | WP_Post\|int $post->ID | `wb-listora.php:410` | - |
| `wb_listora_after_related_listings` | action | int $post_id, WP_Query $related_query | `blocks/listing-detail/render.php:995` | - |
| `wb_listora_after_renew_listing` | action | int $post_id, mixed | `includes/rest/class-listings-controller.php:1562` | `wb-listora-pro` |
| `wb_listora_after_unfeature_listing` | action | int $post_id, mixed($reason) $reason | `includes/core/class-featured.php:228` | - |
| `wb_listora_after_update_listing` | action | int $post_id, WP_REST_Request $request | `includes/rest/class-submission-controller.php:676` | `wb-listora-pro` |
| `wb_listora_before_create_listing` | filter | bool, mixed($title) $title, WP_REST_Request $request | `includes/rest/class-submission-controller.php:405` | - |
| `wb_listora_before_dashboard_listings` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-listings.php:24` | - |
| `wb_listora_before_delete_listing` | filter | bool, int $post_id, WP_REST_Request $request | `includes/rest/class-listings-controller.php:843` | - |
| `wb_listora_before_feature_listing` | filter | bool, int $post_id, mixed($context) $context | `includes/core/class-featured.php:156` | - |
| `wb_listora_before_featured_listings` | action | mixed($featured_block_attributes) $featured_block_attributes | `blocks/listing-featured/render.php:75` | - |
| `wb_listora_before_listing_card` | action | mixed $id, mixed $layout, mixed $show_rating, mixed $show_favorite, mixed $show_type | `blocks/listing-card/render.php:174` | - |
| `wb_listora_before_listing_grid` | action | mixed($grid_block_attributes) $grid_block_attributes | `blocks/listing-grid/render.php:209` | - |
| `wb_listora_before_related_listings` | action | int $post_id, WP_Query $related_query | `blocks/listing-detail/render.php:915` | - |
| `wb_listora_before_renew_listing` | filter | bool, int $post_id, mixed($context) $context | `includes/rest/class-listings-controller.php:1469` | `wb-listora-pro` |
| `wb_listora_before_unfeature_listing` | filter | bool, int $post_id, mixed($context) $context | `includes/core/class-featured.php:214` | - |
| `wb_listora_before_update_listing` | filter | bool, int $post_id, WP_REST_Request $request | `includes/rest/class-submission-controller.php:584` | - |
| `wb_listora_contact_form_per_listing_daily_cap` | filter | int $cap, int $listing_id | `includes/class-contact-form.php:198` | - |
| `wb_listora_default_listing_type` | filter | string $slug | `wb-listora.php:598` | - |
| `wb_listora_delete_listing_media` | filter | bool $delete, int $post_id | `includes/core/class-listing-data-eraser.php:231` | - |
| `wb_listora_expired_listing_notice` | filter | mixed($message) $message, mixed | `includes/class-plugin.php:432` | - |
| `wb_listora_listing_claimed` | action | int $listing_id, array $context | `includes/rest/class-claims-controller.php:512` | `wb-listora-pro` |
| `wb_listora_listing_data_deleted` | action | int $post_id, \WP_Post $post | `includes/core/class-listing-data-eraser.php:108` | `wb-listora-pro` |
| `wb_listora_listing_expiration_date` | filter | string $expiry_iso, int $post_id, array $context | `includes/workflow/class-status-manager.php:99,118; includes/rest/class-listings-controller.php:1654` | `wb-listora-pro` |
| `wb_listora_listing_expired` | action | int $post_id | `includes/workflow/class-expiration-cron.php:169` | `wb-listora-pro` |
| `wb_listora_listing_expiring` | action | int, mixed($days) $days | `includes/admin/class-listing-columns.php:524` | - |
| `wb_listora_listing_indexed` | action | int $post_id | `includes/search/class-search-indexer.php:224` | - |
| `wb_listora_listing_limit_counted_statuses` | filter | array | `includes/core/class-listing-limits.php:430` | - |
| `wb_listora_listing_limit_overflow` | action | int $user_id, mixed($overflow_cost) $overflow_cost | `includes/core/class-listing-limits.php:255` | - |
| `wb_listora_listing_media_deleted` | action | int $post_id, array $deleted, array $considered | `includes/core/class-listing-data-eraser.php:322` | - |
| `wb_listora_listing_pending_admin` | action | int $post_id | `includes/admin/class-listing-columns.php:472` | - |
| `wb_listora_listing_renewed` | action | int $post_id | `includes/rest/class-listings-controller.php:1572` | - |
| `wb_listora_listing_reported` | action | mixed $listing_id, mixed $report, mixed $reports, mixed $request | `includes/rest/class-listings-controller.php:1387` | - |
| `wb_listora_listing_reports_cleared` | action | mixed $post_id | `includes/admin/class-report-metabox.php:185` | - |
| `wb_listora_listing_status_changed` | action | WP_Post\|int $post->ID, mixed($new) $new, mixed($old) $old | `includes/search/class-search-indexer.php:553` | `wb-listora`, `wb-listora-pro` |
| `wb_listora_listing_submitted` | action | int $post_id, mixed($new_status) $new_status, mixed($synthetic_request) $synthetic_request | `includes/admin/class-listing-columns.php:470` | `wb-listora-pro` |
| `wb_listora_listing_timezone` | filter | _(none)_ | `includes/core/class-business-hours.php:228` | - |
| `wb_listora_listing_title_badges` | action | int $post_id, mixed($type ? $type->get_slug() : '') $type ? $type->get_... | `blocks/listing-detail/render.php:275` | `wb-listora-pro` |
| `wb_listora_listing_trashed` | action | int $post_id, mixed | `includes/rest/class-listings-controller.php:868` | - |
| `wb_listora_listing_type_changed` | action | int $post_id, string $slug, string $current_slug | `includes/admin/class-listing-type-metabox.php:189` | - |
| `wb_listora_listing_updated` | action | int $post_id, mixed, WP_REST_Request $request | `includes/rest/class-submission-controller.php:668` | - |
| `wb_listora_listing_verify_email` | action | int $post_id, mixed($token) $token | `includes/workflow/class-email-verification.php:223` | - |
| `wb_listora_listing_{$new_status}` | action | mixed $new_status, mixed $old_status, mixed $post_id, mixed $registry, mixed $type | `includes/workflow/class-status-manager.php:98` | - |
| `wb_listora_payment_listing_abandoned` | action | _(none)_ | `includes/workflow/class-expiration-cron.php:262` | - |
| `wb_listora_purge_orphaned_listing_data` | action | _(none)_ | `includes/core/class-listing-data-eraser.php:152` | `wb-listora-pro` |
| `wb_listora_register_listing_types` | action | mixed($this) $this | `includes/core/class-listing-type-registry.php:78` | - |
| `wb_listora_require_listing_type` | filter | (, b, o, o, l, , $, r, e, q, u, i, r, e, d, ,, , W, P, _, R, E, S, T, _, R, e, q, u, e, s, t, , $, r, e, q, u, e, s, t, ) | `includes/rest/class-submission-controller.php:573` | - |
| `wb_listora_rest_listing_response` | filter | mixed($listing) $listing, WP_Post\|int $post | `includes/rest/class-search-controller.php:461` | `wb-listora-pro` |
| `wb_listora_rest_prepare_listing` | filter | array\|mixed $data, WP_Post\|int $post, WP_REST_Request $request | `includes/rest/class-listings-controller.php:777` | `wb-listora-pro` |
| `wb_listora_rest_prepare_listing_type` | filter | mixed($type_data) $type_data, mixed($type) $type, WP_REST_Request $request | `includes/rest/class-listing-types-controller.php:152` | - |
| `wb_listora_unverified_listing_cleaned` | action | int $post_id, mixed($action) $action | `includes/workflow/class-email-verification.php:544` | - |
| `wb_listora_user_listing_limit` | filter | mixed($best) $best, int $user_id | `includes/core/class-listing-limits.php:382` | - |

## Submission (11)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_contact_form_submit` | action | int $listing_id, array $context, WP_REST_Request $request | `includes/class-contact-form.php:253` | - |
| `wb_listora_after_submit_claim` | action | int $claim_id, int $listing_id, WP_REST_Request $request | `includes/rest/class-claims-controller.php:219` | `wb-listora-pro` |
| `wb_listora_before_submit_claim` | filter | bool, int $listing_id, WP_REST_Request $request | `includes/rest/class-claims-controller.php:169` | - |
| `wb_listora_claim_submitted` | action | int $claim_id, int $listing_id, int $user_id | `includes/rest/class-claims-controller.php:210` | - |
| `wb_listora_review_submitted` | action | int $review_id, int $listing_id, int $user_id, mixed($criteria_ratings) $criteria_ratings, mixed($review_photos) $review_photos, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:524` | `wb-listora-pro` |
| `wb_listora_submission_captcha` | action | int $form_id | `includes/class-captcha.php:106` | - |
| `wb_listora_submission_layout_mode` | filter | string $layout_mode, array $attributes | `blocks/listing-submission/render.php:52` | - |
| `wb_listora_submission_plan_step` | action | mixed($listing_type) $listing_type | `templates/blocks/listing-submission/submission.php:111` | `wb-listora-pro` |
| `wb_listora_submission_register_url` | filter | mixed $submission_register_url, mixed $submission_current_permalink, mixed $wrapper_attrs, mixed $submission_login_url | `blocks/listing-submission/render.php:125` | - |
| `wb_listora_submission_steps` | filter | mixed($steps) $steps, mixed($listing_type) $listing_type, mixed($is_edit_mode) $is_edit_mode | `blocks/listing-submission/render.php:165` | `wb-listora-pro` |
| `wb_listora_submit_url` | filter | mixed($default) $default | `includes/class-template-helpers.php:211` | - |

## Media (7)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_card_image` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-image.php:75` | - |
| `wb_listora_after_detail_gallery` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/gallery.php:67` | - |
| `wb_listora_before_card_image` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-image.php:27` | - |
| `wb_listora_before_detail_gallery` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/gallery.php:26` | - |
| `wb_listora_demo_gallery_max` | filter | int $max, string $type | `demo/class-demo-seeder.php:663` | - |
| `wb_listora_demo_image_timeout` | filter | int $timeout, string $url | `demo/class-demo-seeder.php:558` | - |
| `wb_listora_restrict_media_to_own_uploads` | filter | _(none)_ | `includes/class-assets.php:299` | - |

## Reviews (22)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_create_review` | action | int $review_id, int $listing_id, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:533` | `wb-listora-pro` |
| `wb_listora_after_dashboard_reviews` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-reviews.php:80` | - |
| `wb_listora_after_delete_review` | action | int $review_id, int $review ? (int) $review->listing_id : null, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:682` | `wb-listora-pro` |
| `wb_listora_after_reviews` | action | mixed($view_data) $view_data | `templates/blocks/listing-reviews/reviews.php:128` | - |
| `wb_listora_after_update_review` | action | int $review_id, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:615` | `wb-listora-pro` |
| `wb_listora_before_create_review` | filter | bool, int $listing_id, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:455` | - |
| `wb_listora_before_dashboard_reviews` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-reviews.php:20` | - |
| `wb_listora_before_delete_review` | filter | bool, int $review_id, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:649` | - |
| `wb_listora_before_reviews` | action | mixed($view_data) $view_data | `templates/blocks/listing-reviews/reviews.php:31` | - |
| `wb_listora_before_update_review` | filter | bool, int $review_id, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:573` | - |
| `wb_listora_detail_reviews_limit` | filter | int, int $post_id | `blocks/listing-detail/render.php:424` | - |
| `wb_listora_rest_prepare_review` | filter | mixed($review_data) $review_data, int, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:342` | `wb-listora-pro` |
| `wb_listora_review_after_content` | action | mixed($review) $review | `templates/blocks/listing-reviews/review-card.php:54` | `wb-listora-pro` |
| `wb_listora_review_author_name` | filter | $name, $user_id, $user | `includes/class-template-helpers.php:1856` | - |
| `wb_listora_review_criteria` | filter | array, mixed($listing_type_slug) $listing_type_slug | `blocks/listing-reviews/render.php:78` | `wb-listora-pro` |
| `wb_listora_review_form_after_content` | action | int $post_id | `templates/blocks/listing-detail/tabs.php:399` | `wb-listora-pro` |
| `wb_listora_review_helpful_milestone` | action | int $review_id, mixed($new_count) $new_count | `includes/rest/class-reviews-controller.php:772` | - |
| `wb_listora_review_reminder` | action | int $listing_id, int $pending_count | `includes/workflow/class-expiration-cron.php:285` | `wb-listora` |
| `wb_listora_review_reminder_grace_hours` | filter | int $grace_hours=48 | `includes/workflow/class-expiration-cron.php:242` | - |
| `wb_listora_review_reply` | action | int $review_id | `includes/rest/class-reviews-controller.php:807` | - |
| `wb_listora_review_report_reasons` | filter | (, a, r, r, a, y, , $, r, e, a, s, o, n, s, ) | `includes/class-template-helpers.php:517` | - |
| `wb_listora_review_status_changed` | action | int $review_id, string $status, int $listing_id | `includes/rest/class-reviews-controller.php:650` | - |

## Claims (7)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_dashboard_claims` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-claims.php:123` | - |
| `wb_listora_after_update_claim` | action | int $claim_id, mixed($new_status) $new_status, WP_REST_Request $request | `includes/rest/class-claims-controller.php:549` | `wb-listora-pro` |
| `wb_listora_before_dashboard_claims` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-claims.php:36` | - |
| `wb_listora_before_update_claim` | filter | bool, int $claim_id, WP_REST_Request $request | `includes/rest/class-claims-controller.php:481` | - |
| `wb_listora_claim_approved` | action | int $claim_id, int $listing_id, mixed($claimant) $claimant | `includes/rest/class-claims-controller.php:531` | `wb-listora-pro` |
| `wb_listora_claim_rejected` | action | int $claim_id, int | `includes/rest/class-claims-controller.php:539` | - |
| `wb_listora_rest_prepare_claim` | filter | mixed($response_data) $response_data, int $claim_id, WP_REST_Request $request | `includes/rest/class-claims-controller.php:238` | - |

## Search & Filters (13)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_search_results` | action | _(none)_ | `templates/blocks/listing-search/search.php:85` | `wb-listora-pro` |
| `wb_listora_featured_query_args` | filter | mixed($featured_q_args) $featured_q_args, mixed($attributes) $attributes | `blocks/listing-featured/render.php:30` | - |
| `wb_listora_grid_query_args` | filter | mixed($search_args) $search_args, mixed($attributes) $attributes | `blocks/listing-grid/render.php:95` | - |
| `wb_listora_map_query_args` | filter | $map_search_args, $attributes | `blocks/listing-map/render.php:91` | - |
| `wb_listora_rest_prepare_search_result` | filter | mixed($response_data) $response_data, WP_REST_Request $request | `includes/rest/class-search-controller.php:290` | `wb-listora-pro` |
| `wb_listora_search_after_form` | action | mixed $layout, mixed $listing_type, mixed $search_url_keyword, mixed $search_url_type, mixed $search_url_location | `blocks/listing-search/render.php:210` | - |
| `wb_listora_search_args` | filter | array $args, WP_REST_Request $request | `includes/rest/class-search-controller.php:252` | `wb-listora-pro` |
| `wb_listora_search_author_filter_enabled` | filter | _(none)_ | `includes/rest/class-search-controller.php:317` | - |
| `wb_listora_search_before_form` | action | mixed $layout, mixed $listing_type, mixed $search_url_keyword, mixed $search_url_type, mixed $search_url_location | `blocks/listing-search/render.php:190` | - |
| `wb_listora_search_resolved` | action | array $args, int $total, array $context | `includes/search/class-search-engine.php:210` | `wb-listora-pro` |
| `wb_listora_search_resolved_context` | filter | array $context, array $args, array $result | `includes/search/class-search-engine.php:181` | - |
| `wb_listora_search_results` | filter | mixed($response_data) $response_data, array $args, WP_REST_Request $request | `includes/rest/class-search-controller.php:282` | - |
| `wb_listora_search_short_term_like_fallback` | filter | _(none)_ | `includes/search/class-search-engine.php:391` | - |

## Credits & Payments (12)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_abbreviate_price` | filter | _(none)_ | `includes/class-template-helpers.php:1030` | - |
| `wb_listora_after_dashboard_credits` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-credits.php:218` | - |
| `wb_listora_before_dashboard_credits` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-credits.php:24` | `wb-listora-pro` |
| `wb_listora_credit_pack_sizes` | filter | $sizes | `includes/class-cli-commands.php:1518` | - |
| `wb_listora_credits_purchase_url` | filter | mixed($override) $override | `wb-listora.php:198` | - |
| `wb_listora_dashboard_credit_row_actions` | action | array $entry | `templates/blocks/user-dashboard/tab-credits.php:349` | `wb-listora-pro` |
| `wb_listora_has_credit_purchase_path` | filter | _(none)_ | `wb-listora.php:443` | - |
| `wb_listora_has_payment_gateway` | filter | mixed $has_payment_gateway, mixed $user_id, mixed $credit_mappings, mixed $map, mixed $pack | `blocks/user-dashboard/render.php:300` | - |
| `wb_listora_payment_pending_max_days` | filter | _(none)_ | `includes/workflow/class-expiration-cron.php:228` | - |
| `wb_listora_purchasable_credit_packs` | filter | (, a, r, r, a, y, [, ], , $, p, a, c, k, s, ) | `includes/class-template-helpers.php:427` | - |
| `wb_listora_show_credits` | filter | bool $show | `blocks/user-dashboard/render.php:304; blocks/listing-submission/render.php:345` | `wb-listora-pro` |
| `wb_listora_user_credit_balance` | filter | mixed($balance) $balance, int $user_id | `includes/core/class-listing-limits.php:554` | `wb-listora.php:418` |

## Members & Roles (13)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_account_deletion_reassign_user_id` | filter | _(none)_ | `includes/privacy/class-account-manager.php:838` | - |
| `wb_listora_after_bulk_moderate` | action | string $action, array $ok, array $failed | `includes/rest/class-listings-controller.php:531` | - |
| `wb_listora_blocked_member_capabilities` | filter | _(none)_ | `includes/core/class-member-suspension.php:368` | - |
| `wb_listora_blocked_member_route_allowed` | filter | _(none)_ | `includes/core/class-member-suspension.php:332` | - |
| `wb_listora_blocked_members` | filter | $ids, $user_id | `includes/core/class-member-blocks.php:101` | - |
| `wb_listora_can_moderate_members` | filter | _(none)_ | `includes/admin/class-user-moderation.php:81` | - |
| `wb_listora_captcha_bypass` | filter | mixed($bypass) $bypass, mixed($provider) $provider | `includes/class-captcha.php:144` | - |
| `wb_listora_is_member_suspended` | filter | _(none)_ | `includes/core/class-member-suspension.php:118` | - |
| `wb_listora_member_blocked` | action | $user_id, $target | `includes/core/class-member-blocks.php:244` | - |
| `wb_listora_member_profile_url` | filter | mixed $user_profile_url, mixed $user_id, mixed $review_data, mixed $row, mixed $user | `includes/rest/class-reviews-controller.php:343` | `wb-listora-pro` |
| `wb_listora_member_suspended` | action | _(none)_ | `includes/core/class-member-suspension.php:208` | - |
| `wb_listora_member_unblocked` | action | $user_id, $target | `includes/core/class-member-blocks.php:280` | - |
| `wb_listora_member_unsuspended` | action | _(none)_ | `includes/core/class-member-suspension.php:236` | - |

## Notifications (18)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_email_verified` | action | int $post_id, mixed($new_status) $new_status | `includes/admin/class-listing-columns.php:468` | - |
| `wb_listora_contact_form_email_headers` | filter | array $headers, WP_Post $post | `includes/class-contact-form.php:239` | - |
| `wb_listora_email_content` | filter | mixed($body) $body, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:907` | - |
| `wb_listora_email_content_{$event}` | filter | mixed $body, mixed $event, mixed $vars, mixed $to, mixed $headers | `includes/workflow/class-notifications.php:1099` | - |
| `wb_listora_email_footer_text` | filter | string, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:881` | - |
| `wb_listora_email_from_address` | filter | mixed, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:941` | - |
| `wb_listora_email_from_name` | filter | mixed($site_name) $site_name, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:933` | - |
| `wb_listora_email_headers` | filter | mixed($headers) $headers, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:949` | - |
| `wb_listora_email_logo_url` | filter | string, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:870` | - |
| `wb_listora_email_palette` | filter | array | `includes/workflow/class-notifications.php:1064` | - |
| `wb_listora_email_subject` | filter | mixed($subject) $subject, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:891` | - |
| `wb_listora_email_subject_{$event}` | filter | mixed $subject, mixed $event, mixed $vars, mixed $body | `includes/workflow/class-notifications.php:1084` | - |
| `wb_listora_notification_created` | action | int $recipient_id, string $type, array $data | `includes/workflow/class-suite-notifications.php:210` | - |
| `wb_listora_notification_log_enabled` | filter | bool | `includes/workflow/class-notifications.php:1010` | - |
| `wb_listora_notification_recipients` | filter | mixed($to) $to, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:922` | - |
| `wb_listora_notification_skipped` | action | mixed($event_key) $event_key, string, mixed($context) $context | `includes/workflow/class-notifications.php:668` | - |
| `wb_listora_send_notification` | filter | bool, mixed($event) $event, mixed($vars) $vars, string($to) $to | `includes/workflow/class-notifications.php:840` | `wb-listora-pro` |
| `wb_listora_webhook_secret` | filter | string $default, array $context | `includes/admin/class-settings-page.php:998` | `wb-listora-pro` |

## Admin & Settings (16)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_reset_settings` | action | array $option_keys | `includes/rest/class-settings-controller.php:371` | `wb-listora-pro` |
| `wb_listora_dashboard_per_page` | filter | 20, $context, $user_id | `blocks/user-dashboard/render.php:240` | - |
| `wb_listora_hide_unavailable_pages` | filter | bool $enabled | `includes/core/class-page-availability.php:26` | - |
| `wb_listora_is_admin_screen` | filter | mixed $is_listora, mixed $screen | `includes/class-template-helpers.php:748` | - |
| `wb_listora_list_page_slugs` | filter | mixed $list_pages | `includes/admin/class-admin.php:213` | - |
| `wb_listora_page_created` | action | int $page_id, string $key | `includes/core/class-page-registry.php:688` | - |
| `wb_listora_page_id` | filter | mixed $id, mixed $key, mixed $context | `includes/core/class-page-registry.php:138` | - |
| `wb_listora_page_url` | filter | mixed $url, mixed $key, mixed $args, mixed $id, mixed $out | `includes/core/class-page-registry.php:190` | - |
| `wb_listora_privacy_erase_per_page` | filter | int $per_page, string $email_address, int $page | `includes/privacy/class-privacy-eraser.php:80` | - |
| `wb_listora_register_pages` | action | _(none)_ | `includes/page-registry-helpers.php:269` | `wb-listora-pro` |
| `wb_listora_settings_nav_groups` | filter | mixed($groups) $groups | `includes/admin/class-settings-page.php:288` | `wb-listora-pro` |
| `wb_listora_settings_skip_form_tabs` | filter | array | `includes/admin/class-settings-page.php:377` | `wb-listora-pro` |
| `wb_listora_settings_tab_content` | action | int $tab_id | `includes/admin/class-settings-page.php:469` | `wb-listora-pro` |
| `wb_listora_settings_tab_content_after_form` | action | mixed $tab_id, mixed $skip_form_tabs, mixed $groups, mixed $group, mixed $tab | `includes/admin/class-settings-page.php:565` | `wb-listora-pro` |
| `wb_listora_settings_tabs` | filter | mixed($tabs) $tabs | `includes/admin/class-settings-page.php:311` | `wb-listora-pro` |
| `wb_listora_skip_admin_header` | filter | mixed $screen, mixed $submenu, mixed $title, mixed $plugin, mixed $_GET | `includes/admin/class-admin.php:2057` | - |

## Templates & Display (31)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_card` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card.php:66` | - |
| `wb_listora_after_card_actions` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-actions.php:29` | - |
| `wb_listora_after_card_content` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-content.php:130` | - |
| `wb_listora_after_categories_grid` | action | mixed($attributes) $attributes | `blocks/listing-categories/render.php:89` | - |
| `wb_listora_after_detail_sidebar` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/sidebar.php:83` | - |
| `wb_listora_after_detail_tabs` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/tabs.php:464` | - |
| `wb_listora_after_map` | action | mixed($attributes) $attributes | `blocks/listing-map/render.php:155` | - |
| `wb_listora_after_service_detail` | action | mixed $svc, mixed $post_id, mixed $show_reviews, mixed $detail_reviews, mixed $avg | `templates/blocks/listing-detail/tabs.php:313` | `wb-listora-pro` |
| `wb_listora_after_template` | action | mixed($template_name) $template_name, mixed($template_path) $template_path, array $args | `includes/class-template-helpers.php:89` | - |
| `wb_listora_before_card` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card.php:43` | - |
| `wb_listora_before_card_actions` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-actions.php:18` | - |
| `wb_listora_before_card_content` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-content.php:31` | - |
| `wb_listora_before_categories_grid` | action | mixed($attributes) $attributes | `blocks/listing-categories/render.php:82` | - |
| `wb_listora_before_detail_sidebar` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/sidebar.php:24` | - |
| `wb_listora_before_detail_tabs` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/tabs.php:39` | - |
| `wb_listora_before_map` | action | mixed($attributes) $attributes | `blocks/listing-map/render.php:135` | - |
| `wb_listora_before_template` | action | mixed($template_name) $template_name, mixed($template_path) $template_path, array $args | `includes/class-template-helpers.php:87` | - |
| `wb_listora_card_actions` | action | int\|string $id | `templates/blocks/listing-card/card-actions.php:27` | `wb-listora-pro` |
| `wb_listora_card_view_data` | filter | mixed($card_data) $card_data, int $post_id, WP_Post\|int $post | `includes/class-template-helpers.php:517` | - |
| `wb_listora_category_card_data` | filter | array, mixed($cat) $cat | `templates/blocks/listing-categories/categories.php:49` | - |
| `wb_listora_detail_actions` | action | int $post_id | `blocks/listing-detail/render.php:340` | `wb-listora-pro` |
| `wb_listora_detail_owner_bar_actions` | action | mixed $breadcrumbs, mixed $i, mixed $crumb | `blocks/listing-detail/render.php:381` | - |
| `wb_listora_detail_tabs_view_data` | filter | mixed($tabs_view_data) $tabs_view_data, int $post_id | `blocks/listing-detail/render.php:479` | - |
| `wb_listora_erasure_map` | filter | array $map | `includes/privacy/privacy-helpers.php` | `pro` |
| `wb_listora_grid_after_card` | action | mixed($listing['id']) $listing['id'], mixed($grid_block_attributes) $grid_block_attributes | `templates/blocks/listing-grid/grid.php:71` | - |
| `wb_listora_locate_template` | filter | mixed($template) $template, mixed($template_name) $template_name, mixed($template_path) $template_path | `includes/class-template-helpers.php:51` | - |
| `wb_listora_map_config` | filter | mixed($map_config) $map_config | `blocks/listing-map/render.php:113` | `wb-listora-pro` |
| `wb_listora_map_provider` | filter | string $value | `wb-listora.php:288` | `wb-listora-pro` |
| `wb_listora_map_tiles` | filter | _(none)_ | `includes/class-template-helpers.php:905` | - |
| `wb_listora_render_contact_form` | filter | bool $should_render | `includes/class-contact-form.php:56` | `self — internal gate based on Pro lead_form toggle state` |
| `wb_listora_template_args` | filter | array $args, mixed($template_name) $template_name | `includes/class-template-helpers.php:81` | - |

## Other (112)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_account_deactivated` | action | _(none)_ | `includes/privacy/class-account-manager.php:162` | - |
| `wb_listora_account_deleted` | action | _(none)_ | `includes/privacy/class-account-manager.php:465` | - |
| `wb_listora_account_deletion_eraser_ids` | filter | _(none)_ | `includes/privacy/class-account-manager.php:508` | - |
| `wb_listora_account_reactivated` | action | _(none)_ | `includes/privacy/class-account-manager.php:216` | - |
| `wb_listora_after_add_favorite` | action | int $listing_id, int $user_id, WP_REST_Request $request | `includes/rest/class-favorites-controller.php:258` | `wb-listora-pro` |
| `wb_listora_after_bulk_edit` | action | string $action, int $ok, int $failed, int[] $ids | `includes/admin/class-listing-bulk-actions.php:212` | - |
| `wb_listora_after_calendar` | action | mixed($attributes) $attributes | `blocks/listing-calendar/render.php:215` | - |
| `wb_listora_after_create_service` | action | int $service_id, array\|mixed $data | `includes/core/class-services.php:196` | `wb-listora-pro` |
| `wb_listora_after_dashboard_favorites` | action | array $view_data | `templates/blocks/user-dashboard/tab-favorites.php:60` | - |
| `wb_listora_after_dashboard_nav` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/nav.php:113` | - |
| `wb_listora_after_dashboard_profile` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-profile.php:86` | - |
| `wb_listora_after_delete_service` | action | int $service_id, mixed($existing) $existing | `includes/core/class-services.php:342` | `wb-listora-pro` |
| `wb_listora_after_remove_favorite` | action | int $listing_id, int $user_id, WP_REST_Request $request | `includes/rest/class-favorites-controller.php:316` | `wb-listora-pro` |
| `wb_listora_after_unsubscribe` | action | int $user_id, string $event | `includes/rest/class-unsubscribe-controller.php:229` | - |
| `wb_listora_after_update_service` | action | int $service_id, array\|mixed $data | `includes/core/class-services.php:289` | `wb-listora-pro` |
| `wb_listora_allow_status_transition` | filter | _(none)_ | `includes/workflow/class-status-manager.php:193` | - |
| `wb_listora_analytics_is_bot` | filter | bool $is_bot, string $ua | `includes/features/class-analytics-lite.php:351` | - |
| `wb_listora_analytics_retention_days` | filter | int | `includes/workflow/class-expiration-cron.php:232` | - |
| `wb_listora_antispam_keyword_blacklist` | filter | mixed $patterns, mixed $default_patterns, mixed $haystack, mixed $context | `includes/class-anti-spam.php:125` | - |
| `wb_listora_antispam_max_urls` | filter | mixed $max, mixed $haystack, mixed $context, mixed $matches, mixed $url_count | `includes/class-anti-spam.php:173` | - |
| `wb_listora_antispam_should_check` | filter | mixed $should_check, mixed $context, mixed $blacklist_result, mixed $url_result | `includes/class-anti-spam.php:68` | - |
| `wb_listora_app_config` | filter | array $data, WP_REST_Request $request | `includes/rest/class-settings-controller.php:327` | `pro` |
| `wb_listora_app_connect_bridge` | filter | array $info {owner, connect_url, connect_schemes} | `includes/auth/class-app-connect.php` | - |
| `wb_listora_app_connect_schemes` | filter | string[] $schemes | `includes/auth/class-app-connect.php` | - |
| `wb_listora_app_credential_issued` | action | int $user_id, string $app_id, string $app_name | `includes/auth/class-app-credentials.php` | - |
| `wb_listora_app_password_login_enabled` | filter | bool $on | `includes/auth/class-app-credentials.php` | - |
| `wb_listora_app_scheme` | filter | string $scheme | `includes/auth/class-app-authorize-access.php` | - |
| `wb_listora_appointment_button` | action | int $post_id, mixed($detail_type_slug) $detail_type_slug | `templates/blocks/listing-detail/sidebar.php:79` | - |
| `wb_listora_automation_schema_dirs` | filter | string[] $dirs -- absolute paths, trailing slash, Free's... | `includes/automation/class-schema-loader.php:49` | `wb-listora-pro` |
| `wb_listora_before_account_delete` | filter | _(none)_ | `includes/rest/class-account-controller.php:239` | - |
| `wb_listora_before_account_deleted` | action | _(none)_ | `includes/privacy/class-account-manager.php:417` | - |
| `wb_listora_before_add_favorite` | filter | bool, int $listing_id, WP_REST_Request $request | `includes/rest/class-favorites-controller.php:205` | - |
| `wb_listora_before_calendar` | action | mixed($attributes) $attributes | `blocks/listing-calendar/render.php:190` | - |
| `wb_listora_before_create_service` | filter | array\|mixed $data | `includes/core/class-services.php:117` | `wb-listora-pro` |
| `wb_listora_before_dashboard_favorites` | action | array $view_data | `templates/blocks/user-dashboard/tab-favorites.php:20` | - |
| `wb_listora_before_dashboard_nav` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/nav.php:29` | - |
| `wb_listora_before_dashboard_profile` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-profile.php:19` | - |
| `wb_listora_before_delete_service` | filter | int $service_id | `includes/core/class-services.php:316` | - |
| `wb_listora_before_remove_favorite` | filter | bool, int $listing_id, WP_REST_Request $request | `includes/rest/class-favorites-controller.php:290` | - |
| `wb_listora_before_update_service` | filter | array\|mixed $data, int $service_id | `includes/core/class-services.php:227` | `wb-listora-pro` |
| `wb_listora_bg_import_batch` | action | string $run_id | `includes/import-export/class-background-import.php:971` | `wb-listora` |
| `wb_listora_bg_import_finalize` | action | string $run_id | `includes/import-export/class-background-import.php:981` | `wb-listora` |
| `wb_listora_bg_import_use_async` | filter | bool $use_async, array $state | `includes/import-export/class-background-import.php:916` | - |
| `wb_listora_bot_signatures` | filter | string[] $signatures | `includes/class-bot-detection.php:148` | - |
| `wb_listora_calendar_events` | filter | mixed($events) $events, mixed($attributes) $attributes | `blocks/listing-calendar/render.php:125` | - |
| `wb_listora_companions` | filter | _(none)_ | `includes/integrations/class-companion-registry.php:47` | - |
| `wb_listora_contact_rate_limit_identity` | filter | string $identity, array $context | `includes/class-template-helpers.php` | - |
| `wb_listora_csv_import_max_rows` | filter | _(none)_ | `includes/import-export/class-csv-importer.php:120` | - |
| `wb_listora_currency_format` | filter | _(none)_ | `includes/class-template-helpers.php:978` | - |
| `wb_listora_daily_cleanup` | action | _(none)_ | `includes/class-cli-commands.php:273` | - |
| `wb_listora_dashboard_header_actions` | action | mixed $user_id, mixed $user, mixed $default_tab | `blocks/user-dashboard/render.php:530` | `wb-listora-pro` |
| `wb_listora_dashboard_nav_items` | action | int $user_id | `templates/blocks/user-dashboard/nav.php:109` | `wb-listora-pro` |
| `wb_listora_dashboard_sections` | action | int $user_id | `blocks/user-dashboard/render.php:660` | `wb-listora-pro` |
| `wb_listora_dashboard_tab_labels` | filter | (, a, r, r, a, y, , $, l, a, b, e, l, s, ) | `includes/class-template-helpers.php:322` | `wb-listora-pro` |
| `wb_listora_dashboard_url` | filter | mixed($default) $default | `includes/class-template-helpers.php:236` | - |
| `wb_listora_dedupe_recurring_hooks` | filter | array<string,string> $known hook name => Action Scheduler... | `includes/class-plugin.php` | `wb-listora-pro` |
| `wb_listora_default_features` | filter | mixed($defaults) $defaults | `includes/class-features.php:120` | - |
| `wb_listora_default_hidden_columns` | filter | string[] $listora_hidden column ids hidden by default, WP_Screen $screen current screen | `includes/admin/class-listing-columns.php` | - |
| `wb_listora_demo_import_run` | action | string $run_id, string[] $packs | `includes/admin/class-settings-page.php:2843` | - |
| `wb_listora_directory_url` | filter | mixed($default) $default | `includes/class-template-helpers.php:188` | - |
| `wb_listora_docs_url` | filter | string $url, string $tab_id, string $section | `includes/admin/class-settings-page.php:377` | - |
| `wb_listora_draft_reminder` | action | int $post_id | `includes/workflow/class-expiration-cron.php:210` | - |
| `wb_listora_edit_auto_single_form` | filter | mixed $auto_single, mixed $edit_listing_id, mixed $layout_mode, mixed $visibility_classes, mixed $attributes | `blocks/listing-submission/render.php:287` | - |
| `wb_listora_favorite_added` | action | int $listing_id, int $user_id | `includes/rest/class-favorites-controller.php:249` | `wb-listora-pro` |
| `wb_listora_favorite_removed` | action | int $listing_id, int $user_id | `includes/rest/class-favorites-controller.php:307` | - |
| `wb_listora_feature_duration_days` | filter | mixed($days) $days, int $post_id | `includes/core/class-featured.php:140` | - |
| `wb_listora_feature_{$key}_enabled` | filter | mixed $key, mixed $enabled, mixed $features | `includes/class-features.php:182` | - |
| `wb_listora_features_category_labels` | filter | array $labels | `includes/class-features.php:178` | `wb-listora-pro` |
| `wb_listora_features_registry` | filter | mixed($registry) $registry | `includes/class-features.php:99` | `wb-listora-pro` |
| `wb_listora_field_default_descriptions` | filter | mixed $default_descriptions, mixed $key, mixed $description, mixed $type, mixed $style | `includes/submission-field-renderer.php:76` | - |
| `wb_listora_field_sanitize_callbacks` | filter | mixed($callbacks) $callbacks | `includes/core/class-field.php:247` | - |
| `wb_listora_field_types` | filter | mixed($types) $types | `includes/core/class-field-registry.php:208` | - |
| `wb_listora_fullwidth_blocks` | filter | mixed, WP_Post\|int $post | `includes/core/class-theme-defenses.php:77` | - |
| `wb_listora_import_json_max_bytes` | filter | _(none)_ | `includes/import-export/class-background-import.php:1324` | - |
| `wb_listora_is_account_deactivated` | filter | _(none)_ | `includes/privacy/privacy-helpers.php:361` | - |
| `wb_listora_is_bot_request` | filter | bool $is_bot=false, string $ua | `includes/class-bot-detection.php:104` | - |
| `wb_listora_is_verified` | filter | bool $verified, int $post_id | `includes/class-features.php:232` | `wb-listora-pro` |
| `wb_listora_late_print_script_modules` | filter | _(none)_ | `includes/class-plugin.php:660` | - |
| `wb_listora_login_modal_register_url` | filter | string $url, int $listing_id, string $current_permalink | `blocks/listing-detail/render.php:801` | - |
| `wb_listora_max_hours_slots` | filter | int 3 | `includes/search/class-search-indexer.php:706` | - |
| `wb_listora_migrated_hours_unreadable` | action | int $post_id, mixed $value, string $source_slug | `includes/import-export/class-migration-base.php:488` | - |
| `wb_listora_monetization_status` | filter | (, a, r, r, a, y, , $, s, t, a, t, u, s, ) | `includes/class-template-helpers.php:581` | `wb-listora-pro` |
| `wb_listora_onboarding_checklist` | filter | (, a, r, r, a, y, [, ], , $, i, t, e, m, s, ) | `includes/admin/class-admin.php:1044` | `wb-listora-pro` |
| `wb_listora_placeholder_url` | filter | mixed($url) $url | `includes/class-template-helpers.php:129` | - |
| `wb_listora_pro_cta_should_show` | filter | bool, mixed($surface) $surface | `includes/admin/class-pro-promotion.php:139` | - |
| `wb_listora_pro_owns_analytics` | filter | bool $pro_owns=false | `includes/features/class-analytics-lite.php:117` | `wb-listora-pro` |
| `wb_listora_rate_limit_bypass` | filter | bool, mixed($action) $action, int | `includes/class-rate-limiter.php:137` | - |
| `wb_listora_rate_limit_config` | filter | mixed($config) $config, mixed($action) $action | `includes/class-rate-limiter.php:188` | - |
| `wb_listora_refuse_disallowed_features` | filter | bool $refuse, int[] $disallowed, string $type_slug | `includes/rest/class-submission-controller.php:363` | - |
| `wb_listora_register_field_types` | action | mixed($this) $this | `includes/core/class-field-registry.php:54` | - |
| `wb_listora_register_triggers` | action | \WBListora\Contracts\Trigger_Registry_Interface $registry | `includes/automation/class-trigger-definitions.php:136` | `wb-listora-pro` |
| `wb_listora_renewal_cost` | filter | mixed($cost) $cost, int $post_id, int $plan_id | `includes/rest/class-listings-controller.php:1335` | - |
| `wb_listora_renewal_duration_days` | filter | mixed($duration_days) $duration_days, int $post_id, int $plan_id | `includes/rest/class-listings-controller.php:1338` | - |
| `wb_listora_repair_term_taxonomies` | filter | string[] $taxonomies | `includes/import-export/class-term-helper.php:84` | - |
| `wb_listora_require_rest_nonce` | filter | bool $require, string $action, array $context | `includes/class-template-helpers.php` | - |
| `wb_listora_require_terms_acceptance` | filter | bool $required, WP_REST_Request $request | `includes/rest/class-submission-controller.php:331` | - |
| `wb_listora_required_field_messages` | filter | array<string,string> $messages | `includes/class-assets.php:225` | - |
| `wb_listora_reset_option_keys` | filter | array $option_keys | `includes/rest/class-settings-controller.php:360` | `wb-listora-pro` |
| `wb_listora_rest_prepare_dashboard_stats` | filter | array\|mixed $data, int $user_id, WP_REST_Request $request | `includes/rest/class-dashboard-controller.php:284` | - |
| `wb_listora_rest_prepare_favorite` | filter | mixed($fav_data) $fav_data, int, WP_REST_Request $request | `includes/rest/class-favorites-controller.php:155` | - |
| `wb_listora_rest_prepare_service` | filter | array\|mixed $data, int, WP_REST_Request $request | `includes/rest/class-services-controller.php:421` | `wb-listora-pro` |
| `wb_listora_save_features_extra` | action | array $input | `includes/admin/class-settings-page.php:2342` | `wb-listora-pro` |
| `wb_listora_schema_data` | filter | array\|mixed $data, int $this->post_id | `includes/schema/class-schema-generator.php:166` | - |
| `wb_listora_seo_plugin_active` | filter | bool $active | `includes/class-features.php:354` | - |
| `wb_listora_site_health_checks` | filter | array $checks | `includes/core/class-site-health.php:67` | - |
| `wb_listora_social_link_platforms` | filter | array<string,string> $platforms platform slug => display... | `includes/core/class-field.php` | - |
| `wb_listora_theme_bridges` | filter | mixed $bridges, mixed $bridge_slug, mixed $child_theme, mixed $active_theme, mixed $bridge_path | `includes/class-assets.php:100` | - |
| `wb_listora_trust_proxy_headers` | filter | bool | `includes/class-rate-limiter.php:227` | - |
| `wb_listora_unsubscribable_events` | filter | string[] $events | `includes/rest/class-unsubscribe-controller.php:69` | - |
| `wb_listora_unsubscribe_url` | filter | string $url, int $user_id, string $event | `includes/rest/class-unsubscribe-controller.php:116` | - |
| `wb_listora_upgrade_url` | filter | mixed($default) $default | `includes/class-template-helpers.php:258` | - |
| `wb_listora_view_recorded` | action | int $listing_id | `includes/features/class-analytics-lite.php:206` | - |
