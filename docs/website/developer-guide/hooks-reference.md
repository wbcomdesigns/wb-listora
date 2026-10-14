# Hooks Reference (Actions & Filters)

Every action and filter Listora fires, read from the plugin source with arg
signatures from `audit/manifest.json`. Pro hooks fire only when Listora Pro is active.

Regenerate with `bin/build-hooks-reference.py <free-dir> <pro-dir>`.
Do not hand-edit: this file is overwritten.

`Consumed by` lists sibling plugins already listening, so you can see which
hooks have proven wiring before you rely on one.

## `wb-listora` hooks

**414 hooks** - **166 actions** + **248 filters**.

### Bootstrap (2)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_loaded` | action | _(none)_ | `includes/class-plugin.php:53` | `wb-listora-pro` |
| `wb_listora_rest_api_init` | action | _(none)_ | `includes/class-plugin.php:643` | `wb-listora-pro` |

### Listings (80)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_account_deactivate_listing_statuses` | filter | _(none)_ | `includes/privacy/class-account-manager.php:219` | - |
| `wb_listora_account_deletion_listing_strategy` | filter | string $strategy, int $user_id | `includes/privacy/class-account-manager.php:782` | - |
| `wb_listora_after_approve_listing` | action | WP_Post\|int $post->ID | `wb-listora.php:1368` | - |
| `wb_listora_after_create_listing` | action | int $post_id, WP_REST_Request $request | `includes/rest/class-submission-controller.php:913` | `wb-listora-pro` |
| `wb_listora_after_dashboard_listings` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-listings.php:814` | - |
| `wb_listora_after_deactivate_listing` | action | int $post_id, WP_REST_Request $request | `includes/rest/class-listings-controller.php:1353` | - |
| `wb_listora_after_delete_listing` | action | int $post_id, WP_REST_Request $request | `includes/rest/class-listings-controller.php:1265` | `wb-listora-pro` |
| `wb_listora_after_feature_listing` | action | int $post_id, mixed($context) $context | `includes/core/class-featured.php:159` | - |
| `wb_listora_after_featured_listings` | action | mixed($featured_block_attributes) $featured_block_attributes | `blocks/listing-featured/render.php:136` | - |
| `wb_listora_after_listing_card` | action | mixed $id, mixed $layout, mixed $show_rating, mixed $show_favorite, mixed $show_type | `blocks/listing-card/render.php:224` | - |
| `wb_listora_after_listing_fields` | action | int $post_id, mixed($detail_type_slug) $detail_type_slug | `templates/blocks/listing-detail/sidebar.php:178` | `wb-listora-pro` |
| `wb_listora_after_listing_grid` | action | mixed($grid_block_attributes) $grid_block_attributes | `blocks/listing-grid/render.php:284` | - |
| `wb_listora_after_reactivate_listing` | action | int $post_id, WP_REST_Request $request | `includes/rest/class-listings-controller.php:1568` | - |
| `wb_listora_after_reject_listing` | action | WP_Post\|int $post->ID | `wb-listora.php:1372` | - |
| `wb_listora_after_related_listings` | action | int $post_id, WP_Query $related_query | `blocks/listing-detail/render.php:912` | - |
| `wb_listora_after_renew_listing` | action | int $post_id, mixed | `includes/rest/class-listings-controller.php:2316` | `wb-listora-pro` |
| `wb_listora_after_unfeature_listing` | action | int $post_id, mixed($reason) $reason | `includes/core/class-featured.php:208` | - |
| `wb_listora_after_update_listing` | action | int $post_id, WP_REST_Request $request | `includes/rest/class-submission-controller.php:1320` | `wb-listora-pro` |
| `wb_listora_before_create_listing` | filter | bool, mixed($title) $title, WP_REST_Request $request | `includes/rest/class-submission-controller.php:749` | - |
| `wb_listora_before_dashboard_listings` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-listings.php:48` | - |
| `wb_listora_before_delete_listing` | filter | bool, int $post_id, WP_REST_Request $request | `includes/rest/class-listings-controller.php:1232` | - |
| `wb_listora_before_feature_listing` | filter | bool, int $post_id, mixed($context) $context | `includes/core/class-featured.php:136` | - |
| `wb_listora_before_featured_listings` | action | mixed($featured_block_attributes) $featured_block_attributes | `blocks/listing-featured/render.php:115` | - |
| `wb_listora_before_listing_card` | action | mixed $id, mixed $layout, mixed $show_rating, mixed $show_favorite, mixed $show_type | `blocks/listing-card/render.php:201` | - |
| `wb_listora_before_listing_grid` | action | mixed($grid_block_attributes) $grid_block_attributes | `blocks/listing-grid/render.php:277` | - |
| `wb_listora_before_related_listings` | action | int $post_id, WP_Query $related_query | `blocks/listing-detail/render.php:821` | - |
| `wb_listora_before_renew_listing` | filter | bool, int $post_id, mixed($context) $context | `includes/rest/class-listings-controller.php:2160` | `wb-listora-pro` |
| `wb_listora_before_system_renew_listing` | action | int $listing_id, array $context | `includes/workflow/renewal-helpers.php:63` | - |
| `wb_listora_before_unfeature_listing` | filter | bool, int $post_id, mixed($context) $context | `includes/core/class-featured.php:194` | - |
| `wb_listora_before_update_listing` | filter | bool, int $post_id, WP_REST_Request $request | `includes/rest/class-submission-controller.php:1097` | - |
| `wb_listora_contact_form_per_listing_daily_cap` | filter | int $cap, int $listing_id | `includes/class-contact-form.php:282` | - |
| `wb_listora_default_listing_type` | filter | string $slug | `wb-listora.php:650` | - |
| `wb_listora_delete_listing_media` | filter | bool $delete, int $post_id | `includes/core/class-listing-data-eraser.php:245` | - |
| `wb_listora_expired_listing_notice` | filter | mixed($message) $message, mixed | `blocks/listing-detail/render.php:417` | - |
| `wb_listora_listing_approved_in_space` | action | int $listing, int $space_id, int $actor_id | `includes/rest/class-space-listings-controller.php:398` | - |
| `wb_listora_listing_claimed` | action | int $listing_id, array $context | `includes/rest/class-claims-controller.php:822` | `wb-listora-pro` |
| `wb_listora_listing_data_deleted` | action | int $post_id, \WP_Post $post | `includes/core/class-listing-data-eraser.php:122` | `wb-listora-pro` |
| `wb_listora_listing_expiration_date` | filter | string $expiry_iso, int $post_id, array $context | `includes/rest/class-listings-controller.php:2245` | `wb-listora-pro` |
| `wb_listora_listing_expired` | action | int $post_id | `includes/workflow/class-expiration-cron.php:218` | `wb-listora-pro` |
| `wb_listora_listing_expiring` | action | int, mixed($days) $days | `includes/admin/class-listing-columns.php:869` | - |
| `wb_listora_listing_indexed` | action | int $post_id | `includes/search/class-search-indexer.php:284` | - |
| `wb_listora_listing_limit_counted_statuses` | filter | array | `includes/core/class-listing-limits.php:439` | - |
| `wb_listora_listing_limit_overflow` | action | int $user_id, mixed($overflow_cost) $overflow_cost | `includes/core/class-listing-limits.php:255` | - |
| `wb_listora_listing_media_deleted` | action | int $post_id, array $deleted, array $considered | `includes/core/class-listing-data-eraser.php:340` | - |
| `wb_listora_listing_owner_name` | filter | string $name, int $post_id | `includes/helpers.php:462` | - |
| `wb_listora_listing_owner_url` | filter | string $url, int $post_id, int $author | `includes/helpers.php:494` | - |
| `wb_listora_listing_pending_admin` | action | int $post_id | `includes/admin/class-listing-columns.php:822` | - |
| `wb_listora_listing_rejected_in_space` | action | int $listing, int $space_id, int $actor_id | `includes/rest/class-space-listings-controller.php:473` | - |
| `wb_listora_listing_removed_from_space` | action | int $listing, int $space_id, int $actor_id, string $prior_status, string $context | `includes/rest/class-space-listings-controller.php:457` | - |
| `wb_listora_listing_renewed` | action | int $post_id | `includes/rest/class-listings-controller.php:2330` | - |
| `wb_listora_listing_report_notify_interval` | filter | int | `includes/workflow/class-notifications.php:1033` | - |
| `wb_listora_listing_report_recipients` | filter | array | `includes/workflow/class-notifications.php:1079` | - |
| `wb_listora_listing_reported` | action | int $listing_id, array $report, int $count | `includes/rest/class-listings-controller.php:1485` | `wb-listora` |
| `wb_listora_listing_reports_cleared` | action | mixed $post_id | `includes/admin/class-report-metabox.php:191` | - |
| `wb_listora_listing_status_changed` | action | WP_Post\|int $post->ID, mixed($new) $new, mixed($old) $old | `includes/search/class-search-indexer.php:763` | `wb-listora`, `wb-listora-pro` |
| `wb_listora_listing_submission_charge` | action | int $post_id | `includes/rest/class-submission-controller.php:1000` | - |
| `wb_listora_listing_submission_release` | action | int $post_id | `wb-listora.php:1401` | - |
| `wb_listora_listing_submission_settle` | action | int $post_id | `wb-listora.php:1392` | - |
| `wb_listora_listing_submitted` | action | int $post_id, mixed($new_status) $new_status, mixed($synthetic_request) $synthetic_request | `includes/admin/class-listing-columns.php:820` | `wb-listora-pro` |
| `wb_listora_listing_submitted_to_space` | action | int $listing, int $space_id, int $actor_id | `includes/rest/class-space-listings-controller.php:285` | - |
| `wb_listora_listing_timezone` | filter | _(none)_ | `includes/core/class-business-hours.php:192` | - |
| `wb_listora_listing_title_badges` | action | int $post_id, mixed($type ? $type->get_slug() : '') $type ? $type->get_... | `blocks/listing-detail/render.php:497` | `wb-listora-pro` |
| `wb_listora_listing_trashed` | action | int $post_id, mixed | `includes/rest/class-listings-controller.php:1257` | - |
| `wb_listora_listing_type_changed` | action | int $post_id, string $slug, string $current_slug | `includes/admin/class-listing-type-metabox.php:202` | - |
| `wb_listora_listing_updated` | action | int $post_id, mixed, WP_REST_Request $request | `includes/rest/class-submission-controller.php:1312` | - |
| `wb_listora_listing_verify_email` | action | int $post_id, mixed($token) $token | `includes/workflow/class-email-verification.php:216` | - |
| `wb_listora_listing_{$new_status}` | action | mixed $new_status, mixed $old_status, mixed $post_id, mixed $registry, mixed $type | `includes/workflow/class-status-manager.php:395` | - |
| `wb_listora_max_stored_listing_reports` | filter | int | `includes/rest/class-listings-controller.php:1470` | - |
| `wb_listora_media_attached_to_listing` | action | int $listing_id, array $attachment_ids, int $attached | `includes/media-helpers.php:239` | - |
| `wb_listora_member_listing_statuses` | filter | string[] $statuses | `includes/helpers.php:355` | - |
| `wb_listora_payment_listing_abandoned` | action | _(none)_ | `includes/workflow/class-expiration-cron.php:273` | - |
| `wb_listora_purge_orphaned_listing_data` | action | _(none)_ | `includes/core/class-listing-data-eraser.php:209` | `wb-listora-pro` |
| `wb_listora_register_listing_types` | action | mixed($this) $this | `includes/core/class-listing-type-registry.php:149` | - |
| `wb_listora_require_listing_type` | filter | (, b, o, o, l, , $, r, e, q, u, i, r, e, d, ,, , W, P, _, R, E, S, T, _, R, e, q, u, e, s, t, , $, r, e, q, u, e, s, t, ) | `includes/rest/class-submission-controller.php:639` | - |
| `wb_listora_rest_listing_response` | filter | mixed($listing) $listing, WP_Post\|int $post | `includes/rest/class-search-controller.php:767` | `wb-listora-pro` |
| `wb_listora_rest_prepare_listing` | filter | array\|mixed $data, WP_Post\|int $post, WP_REST_Request $request | `includes/rest/class-listings-controller.php:1121` | `wb-listora-pro` |
| `wb_listora_rest_prepare_listing_type` | filter | mixed($type_data) $type_data, mixed($type) $type, WP_REST_Request $request | `includes/rest/class-listing-types-controller.php:159` | - |
| `wb_listora_should_expire_listing` | filter | bool $should_expire, int $post_id | `includes/workflow/class-expiration-cron.php:202` | `wb-listora-pro` |
| `wb_listora_unverified_listing_cleaned` | action | int $post_id, mixed($action) $action | `includes/workflow/class-email-verification.php:549` | - |
| `wb_listora_user_listing_limit` | filter | mixed($best) $best, int $user_id | `includes/core/class-listing-limits.php:391` | - |

### Submission (14)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_contact_form_submit` | action | int $listing_id, array $context, WP_REST_Request $request | `includes/class-contact-form.php:331` | - |
| `wb_listora_after_submit_claim` | action | int $claim_id, int $listing_id, WP_REST_Request $request | `includes/rest/class-claims-controller.php:263` | `wb-listora-pro` |
| `wb_listora_before_contact_form_submit` | filter | true\|WP_Error $check, int $listing_id, array $lead, WP_REST_Request $request | `includes/class-contact-form.php:247` | `wb-listora-pro` |
| `wb_listora_before_submit_claim` | filter | bool, int $listing_id, WP_REST_Request $request | `includes/rest/class-claims-controller.php:208` | - |
| `wb_listora_claim_submitted` | action | int $claim_id, int $listing_id, int $user_id | `includes/rest/class-claims-controller.php:254` | - |
| `wb_listora_review_submitted` | action | int $review_id, int $listing_id, int $user_id, mixed($criteria_ratings) $criteria_ratings, mixed($review_photos) $review_photos, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:616` | `wb-listora-pro` |
| `wb_listora_space_pending_submission_limit` | filter | int $limit, int $space_id, int $user_id | `includes/rest/class-space-listings-controller.php:258` | - |
| `wb_listora_submission_captcha` | action | int $form_id | `includes/class-captcha.php:106` | - |
| `wb_listora_submission_layout_mode` | filter | string $layout_mode, array $attributes | `blocks/listing-submission/render.php:52` | - |
| `wb_listora_submission_no_permission_message` | filter | string $message | `blocks/listing-submission/render.php:226` | - |
| `wb_listora_submission_plan_step` | action | mixed($listing_type) $listing_type | `templates/blocks/listing-submission/submission.php:96` | `wb-listora-pro` |
| `wb_listora_submission_register_url` | filter | mixed $submission_register_url, mixed $submission_current_permalink, mixed $wrapper_attrs, mixed $submission_login_url | `blocks/listing-submission/render.php:191` | - |
| `wb_listora_submission_steps` | filter | mixed($steps) $steps, mixed($listing_type) $listing_type, mixed($is_edit_mode) $is_edit_mode | `blocks/listing-submission/render.php:382` | `wb-listora-pro` |
| `wb_listora_submit_url` | filter | mixed($default) $default | `includes/class-template-helpers.php:256` | - |

### Media (7)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_card_image` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-image.php:100` | - |
| `wb_listora_after_detail_gallery` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/gallery.php:220` | - |
| `wb_listora_before_card_image` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-image.php:42` | - |
| `wb_listora_before_detail_gallery` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/gallery.php:53` | - |
| `wb_listora_demo_gallery_max` | filter | int $max, string $type | `demo/class-demo-seeder.php:732` | - |
| `wb_listora_demo_image_timeout` | filter | int $timeout, string $url | `demo/class-demo-seeder.php:610` | - |
| `wb_listora_restrict_media_to_own_uploads` | filter | _(none)_ | `includes/class-plugin.php:163` | - |

### Reviews (25)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_create_review` | action | int $review_id, int $listing_id, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:625` | `wb-listora-pro` |
| `wb_listora_after_dashboard_reviews` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-reviews.php:238` | - |
| `wb_listora_after_delete_review` | action | int $review_id, int $review ? (int) $review->listing_id : null, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:852` | `wb-listora-pro` |
| `wb_listora_after_reviews` | action | mixed($view_data) $view_data | `templates/blocks/listing-reviews/reviews.php:157` | - |
| `wb_listora_after_update_review` | action | int $review_id, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:780` | `wb-listora-pro` |
| `wb_listora_before_create_review` | filter | bool, int $listing_id, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:534` | - |
| `wb_listora_before_dashboard_reviews` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-reviews.php:38` | - |
| `wb_listora_before_delete_review` | filter | bool, int $review_id, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:818` | - |
| `wb_listora_before_reviews` | action | mixed($view_data) $view_data | `templates/blocks/listing-reviews/reviews.php:32` | - |
| `wb_listora_before_update_review` | filter | bool, int $review_id, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:665` | - |
| `wb_listora_detail_reviews_limit` | filter | int, int $post_id | `blocks/listing-detail/render.php:683` | - |
| `wb_listora_notify_owner_on_pending_review` | filter | bool $notify, int $review_id, int $listing_id | `includes/workflow/class-notifications.php:485` | - |
| `wb_listora_rest_prepare_review` | filter | mixed($review_data) $review_data, int, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:441` | `wb-listora-pro` |
| `wb_listora_review_after_content` | action | mixed($review) $review | `templates/blocks/listing-detail/tabs.php:571` | `wb-listora`, `wb-listora-pro` |
| `wb_listora_review_author_name` | filter | $name, $user_id, $user | `includes/class-template-helpers.php:2246` | - |
| `wb_listora_review_criteria` | filter | array, mixed($listing_type_slug) $listing_type_slug | `includes/class-template-helpers.php:2518` | `wb-listora-pro` |
| `wb_listora_review_edit_requires_moderation` | filter | bool $requires_remoderation, int $review_id, WP_REST_Request $request | `includes/rest/class-reviews-controller.php:720` | - |
| `wb_listora_review_form_after_content` | action | int $post_id | `templates/blocks/listing-reviews/review-form.php:92` | `wb-listora-pro` |
| `wb_listora_review_helpful_milestone` | action | int $review_id, mixed($new_count) $new_count | `includes/rest/class-reviews-controller.php:941` | - |
| `wb_listora_review_reminder` | action | int $listing_id, int $pending_count | `includes/workflow/class-expiration-cron.php:384` | `wb-listora` |
| `wb_listora_review_reminder_grace_hours` | filter | int $grace_hours=48 | `includes/workflow/class-expiration-cron.php:341` | - |
| `wb_listora_review_reply` | action | int $review_id | `includes/rest/class-reviews-controller.php:978` | - |
| `wb_listora_review_report_reasons` | filter | (, a, r, r, a, y, , $, r, e, a, s, o, n, s, ) | `includes/class-template-helpers.php:828` | - |
| `wb_listora_review_status_changed` | action | int $review_id, string $status, int $listing_id | `includes/rest/class-reviews-controller.php:766` | - |
| `wb_listora_type_reviews_enabled` | filter | bool $on, Listing_Type $type | `includes/core/class-listing-type.php:310` | - |

### Claims (9)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_dashboard_claims` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-claims.php:149` | - |
| `wb_listora_after_delete_claim` | action | _(none)_ | `includes/core/class-claims-model.php:107` | - |
| `wb_listora_after_update_claim` | action | int $claim_id, mixed($new_status) $new_status, WP_REST_Request $request | `includes/admin/class-admin.php:1998` | `wb-listora-pro` |
| `wb_listora_before_dashboard_claims` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-claims.php:43` | - |
| `wb_listora_before_delete_claim` | filter | _(none)_ | `includes/core/class-claims-model.php:86` | - |
| `wb_listora_before_update_claim` | filter | bool, int $claim_id, WP_REST_Request $request | `includes/rest/class-claims-controller.php:559` | - |
| `wb_listora_claim_approved` | action | int $claim_id, int $listing_id, mixed($claimant) $claimant | `includes/rest/class-claims-controller.php:835` | `wb-listora-pro` |
| `wb_listora_claim_rejected` | action | int $claim_id, int | `includes/rest/class-claims-controller.php:722` | - |
| `wb_listora_rest_prepare_claim` | filter | mixed($response_data) $response_data, int $claim_id, WP_REST_Request $request | `includes/rest/class-claims-controller.php:291` | - |

### Search & Filters (19)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_search_results` | action | _(none)_ | `templates/blocks/listing-search/search.php:94` | `wb-listora-pro` |
| `wb_listora_featured_query_args` | filter | mixed($featured_q_args) $featured_q_args, mixed($attributes) $attributes | `blocks/listing-featured/render.php:33` | - |
| `wb_listora_grid_query_args` | filter | mixed($search_args) $search_args, mixed($attributes) $attributes | `blocks/listing-grid/render.php:67` | - |
| `wb_listora_map_query_args` | filter | $map_search_args, $attributes | `blocks/listing-map/render.php:91` | - |
| `wb_listora_rest_prepare_search_result` | filter | mixed($response_data) $response_data, WP_REST_Request $request | `includes/rest/class-search-controller.php:518` | `wb-listora-pro` |
| `wb_listora_search_after_form` | action | mixed $layout, mixed $listing_type, mixed $search_url_keyword, mixed $search_url_type, mixed $search_url_location | `blocks/listing-search/render.php:241` | - |
| `wb_listora_search_args` | filter | array $args, WP_REST_Request $request | `includes/rest/class-search-controller.php:451` | `wb-listora-pro` |
| `wb_listora_search_author_filter_enabled` | filter | _(none)_ | `includes/rest/class-search-controller.php:351` | - |
| `wb_listora_search_before_form` | action | mixed $layout, mixed $listing_type, mixed $search_url_keyword, mixed $search_url_type, mixed $search_url_location | `blocks/listing-search/render.php:221` | - |
| `wb_listora_search_features_visible` | filter | _(none)_ | `templates/blocks/listing-search/filters.php:161` | - |
| `wb_listora_search_orderby` | filter | string $order_by, array $args, array $order_params | `includes/search/class-search-engine.php:806` | - |
| `wb_listora_search_parse_args` | filter | array $args | `includes/search/class-search-engine.php:306` | - |
| `wb_listora_search_resolved` | action | array $args, int $total, array $context | `includes/search/class-search-engine.php:238` | `wb-listora-pro` |
| `wb_listora_search_resolved_context` | filter | array $context, array $args, array $result | `includes/search/class-search-engine.php:209` | - |
| `wb_listora_search_result` | filter | array $result, array $args | `includes/search/class-search-engine.php:1886` | - |
| `wb_listora_search_results` | filter | mixed($response_data) $response_data, array $args, WP_REST_Request $request | `includes/rest/class-search-controller.php:510` | - |
| `wb_listora_search_short_term_like_fallback` | filter | _(none)_ | `includes/search/class-search-engine.php:411` | - |
| `wb_listora_search_where_clauses` | filter | string[] $where, array $args | `includes/search/class-search-engine.php:559` | - |
| `wb_listora_search_where_params` | filter | array $params, array $args | `includes/search/class-search-engine.php:569` | - |

### Credits & Payments (19)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_abbreviate_price` | filter | _(none)_ | `includes/class-template-helpers.php:1879` | - |
| `wb_listora_after_dashboard_credits` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-credits.php:512` | - |
| `wb_listora_before_dashboard_credits` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-credits.php:50` | `wb-listora-pro` |
| `wb_listora_credit_entry_types` | filter | array $types | `includes/helpers.php:577` | - |
| `wb_listora_credit_gateway_labels` | filter | array $labels | `includes/helpers.php:611` | - |
| `wb_listora_credit_pack_sizes` | filter | $sizes | `includes/class-cli-commands.php:1487` | - |
| `wb_listora_credit_purchase_paths` | filter | array<string,bool> $paths | `wb-listora.php:398` | `wb-listora-pro` |
| `wb_listora_credit_reason_label` | filter | string $label, string $reason, string $reference | `includes/helpers.php:669` | `wb-listora-pro` |
| `wb_listora_credits_purchase_url` | filter | mixed($override) $override | `wb-listora.php:300` | - |
| `wb_listora_credits_subtabs` | filter | array<string,string> $subtabs | `includes/admin/class-credits-settings-tab.php:343` | `wb-listora-pro` |
| `wb_listora_dashboard_credit_row_actions` | action | array $entry | `templates/blocks/user-dashboard/tab-credits.php:490` | `wb-listora-pro` |
| `wb_listora_has_credit_purchase_path` | filter | _(none)_ | `wb-listora.php:434` | - |
| `wb_listora_has_payment_gateway` | filter | mixed $has_payment_gateway, mixed $user_id, mixed $credit_mappings, mixed $map, mixed $pack | `wb-listora.php:351` | - |
| `wb_listora_payment_cleanup_max_per_run` | filter | int | `includes/workflow/class-expiration-cron.php:114 (name passed at :289)` | - |
| `wb_listora_payment_pending_max_days` | filter | _(none)_ | `includes/workflow/class-expiration-cron.php:239` | - |
| `wb_listora_purchasable_credit_packs` | filter | (, a, r, r, a, y, [, ], , $, p, a, c, k, s, ) | `includes/class-template-helpers.php:734` | - |
| `wb_listora_show_credit_record` | filter | bool $show, int $user_id | `wb-listora.php:538` | - |
| `wb_listora_show_credits` | filter | bool $show | `wb-listora.php:465` | `wb-listora-pro` |
| `wb_listora_user_credit_balance` | filter | mixed($balance) $balance, int $user_id | `includes/core/class-listing-limits.php:568` | `wb-listora.php:418` |

### Members & Roles (21)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_account_deletion_reassign_user_id` | filter | _(none)_ | `includes/privacy/class-account-manager.php:798` | - |
| `wb_listora_after_bulk_moderate` | action | string $action, array $ok, array $failed | `includes/rest/class-listings-controller.php:609` | - |
| `wb_listora_blocked_member_capabilities` | filter | _(none)_ | `includes/core/class-member-suspension.php:397` | - |
| `wb_listora_blocked_member_route_allowed` | filter | _(none)_ | `includes/core/class-member-suspension.php:365` | - |
| `wb_listora_blocked_members` | filter | $ids, $user_id | `includes/core/class-member-blocks.php:71` | - |
| `wb_listora_can_moderate_members` | filter | _(none)_ | `includes/admin/class-user-moderation.php:72` | - |
| `wb_listora_captcha_bypass` | filter | mixed($bypass) $bypass, mixed($provider) $provider | `includes/class-captcha.php:144` | - |
| `wb_listora_is_member_suspended` | filter | _(none)_ | `includes/core/class-member-suspension.php:87` | - |
| `wb_listora_member_blocked` | action | $user_id, $target | `includes/core/class-member-blocks.php:214` | - |
| `wb_listora_member_notification_events` | filter | array $events | `includes/rest/class-dashboard-controller.php:63` | `wb-listora-pro` |
| `wb_listora_member_profile_url` | filter | mixed $user_profile_url, mixed $user_id, mixed $review_data, mixed $row, mixed $user | `includes/rest/class-reviews-controller.php:409` | `wb-listora-pro` |
| `wb_listora_member_suspended` | action | _(none)_ | `includes/core/class-member-suspension.php:231` | - |
| `wb_listora_member_unblocked` | action | $user_id, $target | `includes/core/class-member-blocks.php:250` | - |
| `wb_listora_member_unsuspended` | action | _(none)_ | `includes/core/class-member-suspension.php:259` | - |
| `wb_listora_rate_limit_ip_counts_members` | filter | bool $count, string $action, int $user_id | `includes/class-rate-limiter.php:276` | - |
| `wb_listora_type_moderation` | filter | string $value, Listing_Type $type | `includes/core/class-listing-type.php:335` | - |
| `wb_listora_user_can_act` | filter | bool $allowed, int $user_id | `includes/core/class-member-suspension.php:156` | - |
| `wb_listora_user_can_moderate_space` | filter | bool $can, int $space_id, int $user_id | `includes/rest/class-space-listings-controller.php:208` | `BuddyNext Pro` |
| `wb_listora_user_can_view_space` | filter | bool $can, int $space_id, int $user_id | `includes/rest/class-space-listings-controller.php:198` | `BuddyNext Pro` |
| `wb_listora_user_is_space_member` | filter | bool, int, int | `includes/rest/class-space-listings-controller.php:182` | - |
| `wb_listora_user_public_name` | filter | _(none)_ | `includes/helpers.php:405` | - |

### Notifications (22)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_email_verified` | action | int $post_id, mixed($new_status) $new_status | `includes/admin/class-listing-columns.php:815` | - |
| `wb_listora_contact_form_email_headers` | filter | array $headers, WP_Post $post | `includes/workflow/class-notifications.php:1572` | - |
| `wb_listora_email_change_confirmed` | action | int $user_id, string $new_email | `includes/auth/class-email-change.php:194` | - |
| `wb_listora_email_content` | filter | mixed($body) $body, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:1529` | - |
| `wb_listora_email_content_{$event}` | filter | mixed $body, mixed $event, mixed $vars, mixed $to, mixed $headers | `includes/workflow/class-notifications.php:1539` | - |
| `wb_listora_email_footer_text` | filter | string, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:1503` | - |
| `wb_listora_email_from_address` | filter | mixed, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:1563` | - |
| `wb_listora_email_from_name` | filter | mixed($site_name) $site_name, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:1555` | - |
| `wb_listora_email_headers` | filter | mixed($headers) $headers, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:1578` | - |
| `wb_listora_email_logo_url` | filter | string, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:1492` | - |
| `wb_listora_email_palette` | filter | array | `includes/workflow/class-notifications.php:1694` | - |
| `wb_listora_email_subject` | filter | mixed($subject) $subject, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:1513` | - |
| `wb_listora_email_subject_{$event}` | filter | mixed $subject, mixed $event, mixed $vars, mixed $body | `includes/workflow/class-notifications.php:1524` | - |
| `wb_listora_notification_created` | action | int $recipient_id, string $type, array $data | `includes/workflow/class-suite-notifications.php:203` | - |
| `wb_listora_notification_events` | filter | array $map | `includes/admin/class-email-templates-page.php:429` | `wb-listora-pro` |
| `wb_listora_notification_log_enabled` | filter | bool | `includes/rest/class-settings-controller.php:326` | - |
| `wb_listora_notification_preview` | filter | array\|null $preview, string $event_key | `includes/workflow/class-notifications.php:1344` | `wb-listora-pro` |
| `wb_listora_notification_recipients` | filter | mixed($to) $to, mixed($event) $event, mixed($vars) $vars | `includes/workflow/class-notifications.php:1544` | - |
| `wb_listora_notification_skipped` | action | mixed($event_key) $event_key, string, mixed($context) $context | `includes/workflow/class-notifications.php:1114` | - |
| `wb_listora_send_notification` | filter | bool, mixed($event) $event, mixed($vars) $vars, string($to) $to | `includes/workflow/class-notifications.php:1377` | `wb-listora-pro` |
| `wb_listora_should_send_notification` | filter | _(none)_ | `includes/workflow/class-notifications.php:1139` | - |
| `wb_listora_webhook_secret` | filter | string $default, array $context | `includes/admin/class-credits-settings-tab.php:65` | `wb-listora-pro` |

### Admin & Settings (18)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_admin_features_checkbox_grid` | filter | bool $enabled | `includes/admin/class-listing-fields-metabox.php:256` | - |
| `wb_listora_after_reset_settings` | action | array $option_keys | `includes/rest/class-settings-controller.php:805` | `wb-listora-pro` |
| `wb_listora_dashboard_per_page` | filter | 20, $context, $user_id | `blocks/user-dashboard/render.php:142` | - |
| `wb_listora_hide_unavailable_pages` | filter | bool $enabled | `includes/core/class-page-availability.php:71` | - |
| `wb_listora_is_admin_screen` | filter | mixed $is_listora, mixed $screen | `includes/class-template-helpers.php:2347` | - |
| `wb_listora_list_page_slugs` | filter | mixed $list_pages | `includes/admin/class-admin.php:260` | - |
| `wb_listora_page_created` | action | int $page_id, string $key | `includes/core/class-page-registry.php:665` | - |
| `wb_listora_page_id` | filter | mixed $id, mixed $key, mixed $context | `includes/core/class-page-registry.php:154` | - |
| `wb_listora_page_mapping_forgotten` | action | string $key, int $post_id | `includes/core/class-page-registry.php:286` | - |
| `wb_listora_page_url` | filter | mixed $url, mixed $key, mixed $args, mixed $id, mixed $out | `includes/core/class-page-registry.php:206` | - |
| `wb_listora_privacy_erase_per_page` | filter | int $per_page, string $email_address, int $page | `includes/privacy/class-privacy-eraser.php:80` | - |
| `wb_listora_register_pages` | action | _(none)_ | `includes/page-registry-helpers.php:430` | `wb-listora-pro` |
| `wb_listora_settings_general_rows` | action | _(none)_ | `includes/admin/class-settings-page.php:872` | `wb-listora-pro` |
| `wb_listora_settings_nav_groups` | filter | mixed($groups) $groups | `includes/admin/class-settings-page.php:456` | `wb-listora-pro` |
| `wb_listora_settings_skip_form_tabs` | filter | array | `includes/admin/class-settings-page.php:575` | `wb-listora-pro` |
| `wb_listora_settings_tab_content` | action | int $tab_id | `includes/admin/class-settings-page.php:758` | `wb-listora-pro` |
| `wb_listora_settings_tab_content_after_form` | action | mixed $tab_id, mixed $skip_form_tabs, mixed $groups, mixed $group, mixed $tab | `includes/admin/class-settings-page.php:786` | `wb-listora-pro` |
| `wb_listora_settings_tabs` | filter | mixed($tabs) $tabs | `includes/admin/class-settings-page.php:492` | `wb-listora-pro` |

### Templates & Display (33)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_card` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card.php:68` | - |
| `wb_listora_after_card_actions` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-actions.php:41` | - |
| `wb_listora_after_card_content` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-content.php:166` | - |
| `wb_listora_after_categories_grid` | action | mixed($attributes) $attributes | `blocks/listing-categories/render.php:199` | - |
| `wb_listora_after_detail_sidebar` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/sidebar.php:191` | - |
| `wb_listora_after_detail_tabs` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/tabs.php:743` | - |
| `wb_listora_after_map` | action | mixed($attributes) $attributes | `blocks/listing-map/render.php:292` | - |
| `wb_listora_after_service_detail` | action | mixed $svc, mixed $post_id, mixed $show_reviews, mixed $detail_reviews, mixed $avg | `templates/blocks/listing-detail/tabs.php:450` | `wb-listora-pro` |
| `wb_listora_after_template` | action | mixed($template_name) $template_name, mixed($template_path) $template_path, array $args | `includes/class-template-helpers.php:115` | - |
| `wb_listora_before_card` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card.php:43` | - |
| `wb_listora_before_card_actions` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-actions.php:18` | - |
| `wb_listora_before_card_content` | action | mixed($view_data) $view_data | `templates/blocks/listing-card/card-content.php:34` | - |
| `wb_listora_before_categories_grid` | action | mixed($attributes) $attributes | `blocks/listing-categories/render.php:192` | - |
| `wb_listora_before_detail_sidebar` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/sidebar.php:34` | - |
| `wb_listora_before_detail_tabs` | action | mixed($view_data) $view_data | `templates/blocks/listing-detail/tabs.php:91` | - |
| `wb_listora_before_map` | action | mixed($attributes) $attributes | `blocks/listing-map/render.php:232` | - |
| `wb_listora_before_template` | action | mixed($template_name) $template_name, mixed($template_path) $template_path, array $args | `includes/class-template-helpers.php:113` | - |
| `wb_listora_card_actions` | action | int\|string $id | `templates/blocks/listing-card/card-actions.php:33` | `wb-listora-pro` |
| `wb_listora_card_view_data` | filter | mixed($card_data) $card_data, int $post_id, WP_Post\|int $post | `includes/class-template-helpers.php:1374` | - |
| `wb_listora_category_card_data` | filter | array, mixed($cat) $cat | `templates/blocks/listing-categories/categories.php:77` | - |
| `wb_listora_dashboard_period_cards` | filter | array $cards | `includes/admin/class-admin.php:1440` | `wb-listora-pro` |
| `wb_listora_detail_actions` | action | int $post_id | `blocks/listing-detail/render.php:595` | `wb-listora-pro` |
| `wb_listora_detail_owner_bar_actions` | action | mixed $breadcrumbs, mixed $i, mixed $crumb | `blocks/listing-detail/render.php:401` | - |
| `wb_listora_detail_tabs_view_data` | filter | mixed($tabs_view_data) $tabs_view_data, int $post_id | `blocks/listing-detail/render.php:743` | - |
| `wb_listora_erasure_map` | filter | array $map | `includes/privacy/privacy-helpers.php:279` | `pro` |
| `wb_listora_grid_after_card` | action | mixed($listing['id']) $listing['id'], mixed($grid_block_attributes) $grid_block_attributes | `templates/blocks/listing-grid/grid.php:76` | - |
| `wb_listora_locate_template` | filter | mixed($template) $template, mixed($template_name) $template_name, mixed($template_path) $template_path | `includes/class-template-helpers.php:71` | - |
| `wb_listora_map_block_clustering` | filter | bool $show_clustering, array<string,mixed> $attributes | `blocks/listing-map/render.php:39` | - |
| `wb_listora_map_config` | filter | mixed($map_config) $map_config | `blocks/listing-map/render.php:199` | `wb-listora-pro` |
| `wb_listora_map_provider` | filter | string $value | `wb-listora.php:612` | `wb-listora-pro` |
| `wb_listora_map_tiles` | filter | _(none)_ | `includes/class-template-helpers.php:1679` | - |
| `wb_listora_render_contact_form` | filter | bool $should_render | `includes/class-contact-form.php:81` | - |
| `wb_listora_template_args` | filter | array $args, mixed($template_name) $template_name | `includes/class-template-helpers.php:101` | - |

### Other (145)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_account_deactivated` | action | _(none)_ | `includes/privacy/class-account-manager.php:133` | - |
| `wb_listora_account_deleted` | action | _(none)_ | `includes/privacy/class-account-manager.php:426` | - |
| `wb_listora_account_deletion_eraser_ids` | filter | _(none)_ | `includes/privacy/class-account-manager.php:466` | - |
| `wb_listora_account_reactivated` | action | _(none)_ | `includes/privacy/class-account-manager.php:187` | - |
| `wb_listora_active_integrations` | filter | array $integrations | `includes/admin/views/integrations.php:67` | `wb-listora-pro` |
| `wb_listora_after_add_favorite` | action | int $listing_id, int $user_id, WP_REST_Request $request | `includes/rest/class-favorites-controller.php:305` | `wb-listora-pro` |
| `wb_listora_after_bulk_edit` | action | string $action, int $ok, int $failed, int[] $ids | `includes/admin/class-listing-bulk-actions.php:322` | - |
| `wb_listora_after_calendar` | action | mixed($attributes) $attributes | `blocks/listing-calendar/render.php:227` | - |
| `wb_listora_after_create_service` | action | int $service_id, array\|mixed $data | `includes/core/class-services.php:266` | `wb-listora-pro` |
| `wb_listora_after_dashboard_favorites` | action | array $view_data | `templates/blocks/user-dashboard/tab-favorites.php:78` | - |
| `wb_listora_after_dashboard_nav` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/nav.php:196` | - |
| `wb_listora_after_dashboard_profile` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-profile.php:298` | - |
| `wb_listora_after_delete_service` | action | int $service_id, mixed($existing) $existing | `includes/core/class-services.php:412` | `wb-listora-pro` |
| `wb_listora_after_remove_favorite` | action | int $listing_id, int $user_id, WP_REST_Request $request | `includes/rest/class-favorites-controller.php:371` | `wb-listora-pro` |
| `wb_listora_after_unsubscribe` | action | int $user_id, string $event | `includes/rest/class-unsubscribe-controller.php:234` | - |
| `wb_listora_after_update_service` | action | int $service_id, array\|mixed $data | `includes/core/class-services.php:359` | `wb-listora-pro` |
| `wb_listora_allow_status_transition` | filter | _(none)_ | `includes/workflow/class-status-manager.php:235` | - |
| `wb_listora_analytics_is_bot` | filter | bool $is_bot, string $ua | `includes/features/class-analytics-lite.php:401` | - |
| `wb_listora_analytics_retention_days` | filter | int | `includes/workflow/class-expiration-cron.php:407` | - |
| `wb_listora_antispam_keyword_blacklist` | filter | mixed $patterns, mixed $default_patterns, mixed $haystack, mixed $context | `includes/class-anti-spam.php:148` | - |
| `wb_listora_antispam_max_urls` | filter | mixed $max, mixed $haystack, mixed $context, mixed $matches, mixed $url_count | `includes/class-anti-spam.php:199` | - |
| `wb_listora_antispam_should_check` | filter | mixed $should_check, mixed $context, mixed $blacklist_result, mixed $url_result | `includes/class-anti-spam.php:91` | - |
| `wb_listora_app_config` | filter | array $data, WP_REST_Request $request | `includes/rest/class-settings-controller.php:697` | `pro` |
| `wb_listora_app_connect_bridge` | filter | array $info {owner, connect_url, connect_schemes} | `includes/auth/class-app-connect.php:115` | - |
| `wb_listora_app_connect_schemes` | filter | string[] $schemes | `includes/auth/class-app-connect.php:71` | - |
| `wb_listora_app_credential_issued` | action | int $user_id, string $app_id, string $app_name | `includes/auth/class-app-credentials.php:166` | - |
| `wb_listora_app_password_login_enabled` | filter | bool $on | `includes/auth/class-app-credentials.php:75` | - |
| `wb_listora_app_scheme` | filter | string $scheme | `includes/auth/class-app-authorize-access.php:84` | - |
| `wb_listora_appointment_button` | action | int $post_id, mixed($detail_type_slug) $detail_type_slug | `templates/blocks/listing-detail/sidebar.php:187` | - |
| `wb_listora_automation_schema_dirs` | filter | string[] $dirs -- absolute paths, trailing slash, Free's... | `includes/automation/class-schema-loader.php:49` | `wb-listora-pro` |
| `wb_listora_before_account_delete` | filter | _(none)_ | `includes/rest/class-account-controller.php:363` | - |
| `wb_listora_before_account_deleted` | action | _(none)_ | `includes/privacy/class-account-manager.php:378` | - |
| `wb_listora_before_add_favorite` | filter | bool, int $listing_id, WP_REST_Request $request | `includes/rest/class-favorites-controller.php:252` | - |
| `wb_listora_before_calendar` | action | mixed($attributes) $attributes | `blocks/listing-calendar/render.php:202` | - |
| `wb_listora_before_create_service` | filter | array\|mixed $data | `includes/core/class-services.php:183` | `wb-listora-pro` |
| `wb_listora_before_dashboard_favorites` | action | array $view_data | `templates/blocks/user-dashboard/tab-favorites.php:20` | - |
| `wb_listora_before_dashboard_nav` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/nav.php:111` | - |
| `wb_listora_before_dashboard_profile` | action | mixed($view_data) $view_data | `templates/blocks/user-dashboard/tab-profile.php:24` | - |
| `wb_listora_before_delete_service` | filter | int $service_id | `includes/core/class-services.php:386` | - |
| `wb_listora_before_remove_favorite` | filter | bool, int $listing_id, WP_REST_Request $request | `includes/rest/class-favorites-controller.php:345` | - |
| `wb_listora_before_update_service` | filter | array\|mixed $data, int $service_id | `includes/core/class-services.php:297` | `wb-listora-pro` |
| `wb_listora_bg_import_batch` | action | string $run_id | `includes/import-export/class-background-import.php:1164` | `wb-listora` |
| `wb_listora_bg_import_finalize` | action | string $run_id | `includes/import-export/class-background-import.php:1174` | `wb-listora` |
| `wb_listora_bg_import_use_async` | filter | bool $use_async, array $state | `includes/import-export/class-background-import.php:1104` | - |
| `wb_listora_bot_signatures` | filter | string[] $signatures | `includes/class-bot-detection.php:135` | - |
| `wb_listora_brand_name` | filter | string $name | `includes/helpers.php:840` | `wb-listora-pro` |
| `wb_listora_calendar_events` | filter | mixed($events) $events, mixed($attributes) $attributes | `blocks/listing-calendar/render.php:137` | - |
| `wb_listora_categories_hide_empty` | filter | bool $hide_empty, array $attributes | `blocks/listing-categories/render.php:31` | - |
| `wb_listora_categories_show_type_label` | filter | bool $show, array $attributes | `blocks/listing-categories/render.php:157` | - |
| `wb_listora_companions` | filter | _(none)_ | `includes/integrations/class-companion-registry.php:40` | - |
| `wb_listora_contact_rate_limit_identity` | filter | string $identity, array $context | `includes/class-template-helpers.php:2465` | - |
| `wb_listora_cron_jobs` | filter | array<string,array> $jobs hook => {schedule, group, delay... | `includes/workflow/class-cron-scheduler.php:134` | `wb-listora-pro` |
| `wb_listora_css_variables` | filter | array $vars | `includes/class-assets.php:439` | - |
| `wb_listora_csv_import_max_rows` | filter | _(none)_ | `includes/import-export/class-csv-importer.php:120` | - |
| `wb_listora_currencies` | filter | array<string,string> $currencies | `includes/class-template-helpers.php:1735` | - |
| `wb_listora_currency_format` | filter | _(none)_ | `includes/class-template-helpers.php:1783` | - |
| `wb_listora_daily_cleanup` | action | _(none)_ | `includes/class-cli-commands.php:292` | - |
| `wb_listora_dashboard_activity` | filter | array $items, int $limit | `includes/admin/class-admin.php:1305` | `wb-listora-pro` |
| `wb_listora_dashboard_attention` | filter | array $queues | `includes/admin/class-admin.php:1215` | `wb-listora-pro` |
| `wb_listora_dashboard_header_actions` | action | mixed $user_id, mixed $user, mixed $default_tab | `blocks/user-dashboard/render.php:632` | `wb-listora-pro` |
| `wb_listora_dashboard_nav_group` | action | string $group, int $user_id | `templates/blocks/user-dashboard/nav.php:86` | `wb-listora-pro` |
| `wb_listora_dashboard_nav_items` | action | int $user_id | `templates/blocks/user-dashboard/nav.php:97` | `wb-listora-pro` |
| `wb_listora_dashboard_sections` | action | int $user_id | `blocks/user-dashboard/render.php:1169` | `wb-listora-pro` |
| `wb_listora_dashboard_tab_labels` | filter | (, a, r, r, a, y, , $, l, a, b, e, l, s, ) | `includes/class-template-helpers.php:446` | `wb-listora-pro` |
| `wb_listora_dashboard_url` | filter | mixed($default) $default | `includes/class-template-helpers.php:275` | - |
| `wb_listora_default_features` | filter | mixed($defaults) $defaults | `includes/class-features.php:183` | - |
| `wb_listora_default_hidden_columns` | filter | string[] $listora_hidden column ids hidden by default, WP_Screen $screen current screen | `includes/admin/class-listing-columns.php:233` | - |
| `wb_listora_demo_import_run` | action | string $run_id, string[] $packs | `includes/admin/class-demo-import-admin.php:165` | - |
| `wb_listora_directory_url` | filter | mixed($default) $default | `includes/class-template-helpers.php:239` | - |
| `wb_listora_display_amount` | filter | _(none)_ | `includes/class-template-helpers.php:1844` | - |
| `wb_listora_docs_url` | filter | string $url, string $tab_id, string $section | `includes/admin/class-settings-page.php:541` | - |
| `wb_listora_draft_reminder` | action | int $post_id | `includes/workflow/class-expiration-cron.php:312` | - |
| `wb_listora_draft_reminder_max_per_run` | filter | int | `includes/workflow/class-expiration-cron.php:114 (name passed at :329)` | - |
| `wb_listora_edit_auto_single_form` | filter | mixed $auto_single, mixed $edit_listing_id, mixed $layout_mode, mixed $visibility_classes, mixed $attributes | `blocks/listing-submission/render.php:456` | - |
| `wb_listora_event_labels` | filter | array<string,string> $labels | `includes/helpers.php:757` | `wb-listora-pro` |
| `wb_listora_expiration_enabled` | filter | bool $enabled | `includes/helpers.php:818` | `wb-listora-pro` |
| `wb_listora_expiration_reason` | filter | string $reason | `includes/admin/class-settings-page.php:961` | `wb-listora-pro` |
| `wb_listora_expire_max_per_run` | filter | int | `includes/workflow/class-expiration-cron.php:114 (name passed at :224)` | - |
| `wb_listora_expiry_warn_max_per_run` | filter | int | `includes/workflow/class-expiration-cron.php:114 (name passed at :89, :161)` | - |
| `wb_listora_favorite_added` | action | int $listing_id, int $user_id | `includes/rest/class-favorites-controller.php:296` | `wb-listora-pro` |
| `wb_listora_favorite_removed` | action | int $listing_id, int $user_id | `includes/rest/class-favorites-controller.php:362` | - |
| `wb_listora_feature_duration_days` | filter | mixed($days) $days, int $post_id | `includes/core/class-featured.php:120` | - |
| `wb_listora_feature_{$key}_enabled` | filter | mixed $key, mixed $enabled, mixed $features | `includes/class-features.php:238` | - |
| `wb_listora_featured_backfill` | filter | bool $backfill, array $attributes | `blocks/listing-featured/render.php:52` | - |
| `wb_listora_features_category_labels` | filter | array $labels | `includes/class-features.php:162` | `wb-listora-pro` |
| `wb_listora_features_registry` | filter | mixed($registry) $registry | `includes/class-features.php:135` | `wb-listora-pro` |
| `wb_listora_field_default_descriptions` | filter | mixed $default_descriptions, mixed $key, mixed $description, mixed $type, mixed $style | `includes/submission-field-renderer.php:76` | - |
| `wb_listora_field_sanitize_callbacks` | filter | mixed($callbacks) $callbacks | `includes/core/class-field.php:310` | - |
| `wb_listora_field_types` | filter | mixed($types) $types | `includes/core/class-field-registry.php:208` | - |
| `wb_listora_fullwidth_blocks` | filter | mixed, WP_Post\|int $post | `includes/core/class-theme-defenses.php:243` | - |
| `wb_listora_import_json_max_bytes` | filter | _(none)_ | `includes/import-export/class-background-import.php:1378` | - |
| `wb_listora_import_location_as_place` | filter | bool $as_place, string $text, int $post_id | `includes/import-export/class-term-helper.php:279` | - |
| `wb_listora_is_account_deactivated` | filter | _(none)_ | `includes/privacy/privacy-helpers.php:312` | - |
| `wb_listora_is_bot_request` | filter | bool $is_bot=false, string $ua | `includes/class-bot-detection.php:91` | - |
| `wb_listora_is_verified` | filter | bool $verified, int $post_id | `includes/class-features.php:283` | `wb-listora-pro` |
| `wb_listora_late_print_script_modules` | filter | _(none)_ | `includes/class-plugin.php:772` | - |
| `wb_listora_layered_style_bases` | filter | string[] $bases | `includes/core/class-theme-defenses.php:206` | `wb-listora-pro` |
| `wb_listora_lead_recorded` | action | int $listing_id | `includes/features/class-analytics-lite.php:132` | - |
| `wb_listora_login_modal_register_url` | filter | string $url, int $listing_id, string $current_permalink | `blocks/listing-detail/render.php:1072` | - |
| `wb_listora_max_hours_slots` | filter | int 3 | `includes/search/class-search-indexer.php:738` | - |
| `wb_listora_migrated_hours_unreadable` | action | int $post_id, mixed $value, string $source_slug | `includes/import-export/class-migration-base.php:496` | - |
| `wb_listora_monetization_status` | filter | (, a, r, r, a, y, , $, s, t, a, t, u, s, ) | `includes/class-template-helpers.php:870` | `wb-listora-pro` |
| `wb_listora_near_me_radius_options` | filter | _(none)_ | `wb-listora.php:689` | - |
| `wb_listora_onboarding_checklist` | filter | (, a, r, r, a, y, [, ], , $, i, t, e, m, s, ) | `includes/admin/class-admin.php:1045` | `wb-listora-pro` |
| `wb_listora_owner_name_use_real_name` | filter | bool $use_real_name, WP_User $user, int $post_id | `includes/helpers.php:448` | - |
| `wb_listora_placeholder_url` | filter | mixed($url) $url | `includes/class-template-helpers.php:155` | - |
| `wb_listora_pro_cta_should_show` | filter | bool, mixed($surface) $surface | `includes/admin/class-pro-promotion.php:146` | - |
| `wb_listora_pro_owns_analytics` | filter | bool $pro_owns=false | `includes/features/class-analytics-lite.php:167` | `wb-listora-pro` |
| `wb_listora_public_meta_keys` | filter | _(none)_ | `includes/core/class-meta-handler.php:374` | - |
| `wb_listora_rate_limit_bypass` | filter | bool, mixed($action) $action, int | `includes/class-rate-limiter.php:239` | - |
| `wb_listora_rate_limit_config` | filter | mixed($config) $config, mixed($action) $action | `includes/class-rate-limiter.php:303` | - |
| `wb_listora_refuse_disallowed_features` | filter | bool $refuse, int[] $disallowed, string $type_slug | `includes/rest/class-submission-controller.php:361` | - |
| `wb_listora_register_field_types` | action | mixed($this) $this | `includes/core/class-field-registry.php:54` | - |
| `wb_listora_register_triggers` | action | \WBListora\Contracts\Trigger_Registry_Interface $registry | `includes/automation/class-trigger-definitions.php:54` | `wb-listora-pro` |
| `wb_listora_renewal_cost` | filter | mixed($cost) $cost, int $post_id, int $plan_id | `includes/rest/class-listings-controller.php:1994` | - |
| `wb_listora_renewal_duration_days` | filter | mixed($duration_days) $duration_days, int $post_id, int $plan_id | `includes/rest/class-listings-controller.php:1997` | - |
| `wb_listora_repair_term_taxonomies` | filter | string[] $taxonomies | `includes/import-export/class-term-helper.php:70` | - |
| `wb_listora_require_rest_nonce` | filter | bool $require, string $action, array $context | `includes/class-template-helpers.php:2387` | - |
| `wb_listora_require_terms_acceptance` | filter | bool $required, WP_REST_Request $request | `includes/rest/class-submission-controller.php:506` | - |
| `wb_listora_required_field_messages` | filter | array<string,string> $messages | `includes/class-assets.php:274` | - |
| `wb_listora_reset_field_aliases` | filter | array<string,string> $aliases | `includes/rest/class-settings-controller.php:781` | `wb-listora-pro` |
| `wb_listora_reset_option_keys` | filter | array $option_keys | `includes/rest/class-settings-controller.php:765` | `wb-listora-pro` |
| `wb_listora_rest_prepare_dashboard_stats` | filter | array\|mixed $data, int $user_id, WP_REST_Request $request | `includes/rest/class-dashboard-controller.php:317` | - |
| `wb_listora_rest_prepare_favorite` | filter | mixed($fav_data) $fav_data, int, WP_REST_Request $request | `includes/rest/class-favorites-controller.php:187` | - |
| `wb_listora_rest_prepare_service` | filter | array\|mixed $data, int, WP_REST_Request $request | `includes/rest/class-services-controller.php:475` | `wb-listora-pro` |
| `wb_listora_save_features_extra` | action | array $input | `includes/admin/class-features-settings-tab.php:288` | `wb-listora-pro` |
| `wb_listora_schema_data` | filter | array\|mixed $data, int $this->post_id | `includes/schema/class-schema-generator.php:205` | - |
| `wb_listora_schema_types` | filter | string[] $types | `includes/admin/class-type-editor.php:141` | - |
| `wb_listora_seo_plugin_active` | filter | bool $active | `includes/class-features.php:321` | - |
| `wb_listora_services_enabled` | filter | bool $enabled, int $listing_id | `includes/core/class-services.php:73` | - |
| `wb_listora_show_basic_csv_import` | filter | bool $show | `includes/admin/class-import-export-tabs.php:126` | `wb-listora-pro` |
| `wb_listora_site_health_checks` | filter | array $checks | `includes/core/class-site-health.php:57` | - |
| `wb_listora_social_link_platforms` | filter | array<string,string> $platforms platform slug => display... | `includes/core/class-field.php:648` | - |
| `wb_listora_space_showcase_enabled` | filter | bool, int | `includes/rest/class-space-listings-controller.php:183` | - |
| `wb_listora_term_archive_filters` | filter | array $filters | `includes/core/class-term-archives.php:80` | - |
| `wb_listora_term_archive_intro` | filter | string $intro, WP_Term $term | `includes/core/class-term-archives.php:183` | - |
| `wb_listora_theme_bridges` | filter | mixed $bridges, mixed $bridge_slug, mixed $child_theme, mixed $active_theme, mixed $bridge_path | `includes/class-assets.php:116` | - |
| `wb_listora_theme_isolation` | filter | bool $enabled | `includes/core/class-theme-defenses.php:90` | - |
| `wb_listora_trust_proxy_headers` | filter | bool | `includes/class-rate-limiter.php:371` | - |
| `wb_listora_trusted_package_hosts` | filter | string[] $hosts, string $package | `includes/integrations/class-companion-installer.php:241` | - |
| `wb_listora_unsubscribable_events` | filter | string[] $events | `includes/rest/class-unsubscribe-controller.php:56` | - |
| `wb_listora_unsubscribe_url` | filter | string $url, int $user_id, string $event | `includes/rest/class-unsubscribe-controller.php:103` | - |
| `wb_listora_upgrade_url` | filter | mixed($default) $default | `includes/class-template-helpers.php:1007` | - |
| `wb_listora_view_recorded` | action | int $listing_id | `includes/features/class-analytics-lite.php:256` | - |
| `wb_listora_visible_meta` | filter | _(none)_ | `includes/core/class-meta-handler.php:387` | - |

## `wb-listora-pro` hooks

**259 hooks** - **99 actions** + **160 filters**, plus 5 deprecated.

### Bootstrap (2)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_pro_features_loaded` | action | _(none)_ | `includes/class-feature-manager.php:126` | - |
| `wb_listora_pro_loaded` | action | _(none)_ | `wb-listora-pro.php:317` | - |

### Listings (17)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_pro_after_bp_listings_loop` | action | $view_data | `templates/bp/listings-loop.php:120` | - |
| `wb_listora_pro_before_bp_listings_loop` | action | $view_data | `templates/bp/listings-loop.php:46` | - |
| `wb_listora_pro_bp_listings_nav_label` | filter | string $label | `includes/features/class-buddy-press-integration.php:1008` | - |
| `wb_listora_pro_bp_listings_per_page` | filter | int $per_page, int $user_id | `includes/features/class-buddy-press-integration.php:700` | - |
| `wb_listora_pro_bp_listings_query_args` | filter | array( 'post_type' => 'listora_listing', 'post_status' =>..., $user_id | `includes/features/class-buddy-press-integration.php:703` | - |
| `wb_listora_pro_bp_listings_view_data` | filter | $view_data, $user_id | `includes/features/class-buddy-press-integration.php:767` | - |
| `wb_listora_pro_email_listing_paused_subject` | filter | mixed $subject, mixed $view_data, mixed $body, mixed $headers | `includes/notifications/class-plan-notifier.php:104` | - |
| `wb_listora_pro_email_listing_paused_view_data` | filter | mixed $view_data, mixed $user, mixed $context, mixed $subject, mixed $body | `includes/notifications/class-plan-notifier.php:89` | - |
| `wb_listora_pro_email_listing_resumed_subject` | filter | mixed $subject, mixed $view_data, mixed $body, mixed $headers | `includes/notifications/class-plan-notifier.php:180` | - |
| `wb_listora_pro_email_listing_resumed_view_data` | filter | mixed $view_data, mixed $user, mixed $context, mixed $subject, mixed $body | `includes/notifications/class-plan-notifier.php:171` | - |
| `wb_listora_pro_listing_auto_renew_paused` | action | int $listing_id, int $plan_id | `includes/features/class-pricing-plans.php:1556` | - |
| `wb_listora_pro_listing_auto_renewed` | action | int $listing_id, int $plan_id | `includes/features/class-pricing-plans.php:1536` | - |
| `wb_listora_pro_listing_paused` | action | mixed $post_id, mixed $plan_id, mixed $user_id, mixed $plan_name, mixed $plan_cost | `includes/features/class-pricing-plans.php:1312` | - |
| `wb_listora_pro_listing_resumed` | action | mixed $listing_id, mixed $plan_id, mixed $user_id, mixed $plan_name, mixed $plan_cost | `includes/features/class-pricing-plans.php:1665` | - |
| `wb_listora_pro_refund_repauses_listing` | filter | bool $repause, int $listing_id, array $context | `includes/features/class-pricing-plans.php:1999` | - |
| `wb_listora_pro_refund_resolved_listings` | filter | array $candidates, int $user_id, array $context | `includes/features/class-pricing-plans.php:1897` | - |
| `wb_listora_rest_prepare_comparison_listing` | filter | $listing, $request, isset( $listing['id'] ) ? (int) $listing['id'] : 0 | `includes/features/class-comparison.php:404` | - |

### Submission (4)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_pro_after_submit_response` | action | $response_id, array( 'need_id' => $need_id, 'listing_id' => $listing_id... | `includes/needs/class-need-response-manager.php:332` | - |
| `wb_listora_pro_before_submit_response` | filter | true, $data | `includes/needs/class-need-response-manager.php:166` | - |
| `wb_listora_pro_need_submitted` | action | $need_id, $request | `includes/rest/class-needs-controller.php:595` | - |
| `wb_listora_review_submitted` | action | _(none)_ | `includes/features/class-multi-criteria-reviews.php:190` | - |

### Reviews (10)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_pro_after_bp_reviews_loop` | action | $view_data | `templates/bp/reviews-loop.php:124` | - |
| `wb_listora_pro_before_bp_reviews_loop` | action | $view_data | `templates/bp/reviews-loop.php:48` | - |
| `wb_listora_pro_bp_reviews_limit` | filter | 20, $user_id | `includes/features/class-buddy-press-integration.php:798` | - |
| `wb_listora_pro_bp_reviews_nav_label` | filter | string $label | `includes/features/class-buddy-press-integration.php:982` | - |
| `wb_listora_pro_bp_reviews_view_data` | filter | $view_data, $user_id | `includes/features/class-buddy-press-integration.php:877` | - |
| `wb_listora_pro_moderation_review_statuses` | filter | array | `includes/features/class-moderator.php:445` | - |
| `wb_listora_pro_review_max_photos` | filter | int $max | `includes/features/class-photo-reviews.php:311` | - |
| `wb_listora_pro_review_photo_max_bytes` | filter | int $bytes | `includes/features/class-photo-reviews.php:191` | - |
| `wb_listora_rest_prepare_multi_criteria_review` | filter | $review, $review_id, $context | `includes/features/class-multi-criteria-reviews.php:83` | - |
| `wb_listora_rest_prepare_photo_review` | filter | $response, $request, (int) $review_id | `includes/features/class-photo-reviews.php:254` | - |

### Search & Filters (15)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_pro_after_email_saved_search` | action | $saved_search_vars | `templates/emails/saved-search-alert.php:74` | - |
| `wb_listora_pro_after_save_search` | action | (int) $id, $item, $request | `includes/features/class-advanced-search.php:321` | - |
| `wb_listora_pro_after_unsave_search` | action | (int) $id, $request | `includes/features/class-advanced-search.php:369` | - |
| `wb_listora_pro_before_email_saved_search` | action | $saved_search_vars | `templates/emails/saved-search-alert.php:28` | - |
| `wb_listora_pro_before_save_search` | filter | true, $new, $request | `includes/features/class-advanced-search.php:300` | - |
| `wb_listora_pro_before_unsave_search` | filter | true, (int) $id, $request | `includes/features/class-advanced-search.php:355` | - |
| `wb_listora_pro_email_saved_search_alert_subject` | filter | $subject, $view_data | `includes/features/class-advanced-search.php:750` | - |
| `wb_listora_pro_email_saved_search_alert_view_data` | filter | $view_data, $user, $search, $new_ids | `includes/features/class-advanced-search.php:741` | - |
| `wb_listora_pro_prepare_service_search_result` | filter | $result, $service_id | `includes/features/class-services-pro.php:591` | - |
| `wb_listora_pro_search_terms_recorded` | action | string $term, int $total, array $context | `includes/features/class-search-analytics.php:152` | - |
| `wb_listora_pro_visibility_hide_from_search` | filter | bool $hide, string $mode | `includes/features/class-coming-soon.php:331` | - |
| `wb_listora_rest_prepare_places_search` | filter | $place, $request, isset( $place['place_id'] ) ? (string) $place['place_id']... | `includes/rest/class-google-import-controller.php:232` | - |
| `wb_listora_rest_prepare_saved_search` | filter | $item, $request, (int) $id, (int) $user_id | `includes/features/class-advanced-search.php:204` | - |
| `wb_listora_rest_prepare_search_alert` | filter | $search, $request, $search_id, $user_id | `includes/features/class-advanced-search.php:244` | - |
| `wb_listora_rest_prepare_search_analytics` | filter | array $report, WP_REST_Request $request | `includes/features/class-search-analytics.php:320` | - |

### Credits & Payments (54)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_pro_after_activate_plan` | action | $listing_id, $context | `includes/features/class-pricing-plans.php:4069` | - |
| `wb_listora_pro_after_create_coupon` | action | $post_id, $meta, $args | `includes/features/class-coupons.php:828` | - |
| `wb_listora_pro_after_credit_purchase` | action | $view_data | `blocks/credit-purchase/render.php:303` | - |
| `wb_listora_pro_after_delete_coupon` | action | $coupon_id | `includes/features/class-coupons.php:983` | - |
| `wb_listora_pro_after_payment_webhook` | action | $user->ID, array( 'credits' => $credits, 'amount' => $amount, 'gatew..., $request | `includes/features/class-webhook-receiver.php:460` | - |
| `wb_listora_pro_after_purchase_plan` | action | $listing_id, array_merge( $context, array( 'discount' => $discount ) ), $request | `includes/rest/class-credits-controller.php:339` | - |
| `wb_listora_pro_after_redeem_coupon` | action | $coupon_id, $context | `includes/features/class-coupons.php:514` | `wb-listora-pro` |
| `wb_listora_pro_after_topup_credits` | action | (int) $user_id, $context | `includes/features/class-credit-system.php:414` | - |
| `wb_listora_pro_after_update_coupon` | action | $coupon_id, $args | `includes/features/class-coupons.php:939` | - |
| `wb_listora_pro_before_activate_plan` | filter | true, $context | `includes/features/class-pricing-plans.php:3781` | - |
| `wb_listora_pro_before_create_coupon` | filter | true, $args | `includes/features/class-coupons.php:774` | - |
| `wb_listora_pro_before_credit_purchase` | action | $view_data | `blocks/credit-purchase/render.php:190` | - |
| `wb_listora_pro_before_delete_coupon` | filter | true, $coupon_id | `includes/features/class-coupons.php:962` | - |
| `wb_listora_pro_before_payment_webhook` | filter | true, $body, $request | `includes/features/class-webhook-receiver.php:214` | - |
| `wb_listora_pro_before_purchase_plan` | filter | true, $context, $request | `includes/rest/class-credits-controller.php:314` | - |
| `wb_listora_pro_before_redeem_coupon` | filter | true, $context | `includes/features/class-coupons.php:366` | - |
| `wb_listora_pro_before_topup_credits` | filter | true, $context | `includes/features/class-credit-system.php:380` | - |
| `wb_listora_pro_before_update_coupon` | filter | true, $coupon_id, $args | `includes/features/class-coupons.php:853` | - |
| `wb_listora_pro_coupon_created` | action | $post_id, $meta | `includes/features/class-coupons.php:819` | - |
| `wb_listora_pro_coupon_deleted` | action | $coupon_id | `includes/features/class-coupons.php:976` | - |
| `wb_listora_pro_coupon_updated` | action | $coupon_id, $args | `includes/features/class-coupons.php:931` | - |
| `wb_listora_pro_coupon_used` | action | $coupon_id, $user_id, $plan_id, $listing_id, $discount | `includes/features/class-coupons.php:506` | - |
| `wb_listora_pro_credit_purchase_view_data` | filter | $view_data, $attributes | `blocks/credit-purchase/render.php:160` | - |
| `wb_listora_pro_credits_added` | action | mixed $user_id, mixed $amount, mixed $balance, mixed $source, mixed $slug | `includes/features/class-credit-system.php:123` | - |
| `wb_listora_pro_credits_deducted` | action | mixed $user_id, mixed $amount, mixed $balance, mixed $ref_type, mixed $item_id | `includes/features/class-credit-system.php:153` | - |
| `wb_listora_pro_credits_refunded` | action | mixed $user_id, mixed $amount, mixed $balance, mixed $ref_type, mixed $item_id | `includes/features/class-credit-system.php:218` | - |
| `wb_listora_pro_credits_refunded_context` | action | int $user_id, float $amount, array $context | `includes/features/class-credit-system.php:234` | - |
| `wb_listora_pro_credits_url` | filter | string $url | `includes/features/class-pricing-plans.php:3028` | - |
| `wb_listora_pro_email_credits_low_subject` | filter | $subject, $view_data | `includes/notifications/class-credit-notifier.php:173` | - |
| `wb_listora_pro_email_credits_low_view_data` | filter | $view_data, $user, $context | `includes/notifications/class-credit-notifier.php:164` | - |
| `wb_listora_pro_email_credits_refunded_subject` | filter | string $subject, array $view_data | `includes/notifications/class-credit-notifier.php:341` | - |
| `wb_listora_pro_email_credits_refunded_view_data` | filter | array $view_data | `includes/notifications/class-credit-notifier.php:314` | - |
| `wb_listora_pro_pack_checkout_url` | filter | _(none)_ | `includes/features/class-pricing-plans.php:2956` | - |
| `wb_listora_pro_payment_received` | action | mixed $user, mixed $credits, mixed $body, mixed $user_id, mixed $context | `includes/features/class-webhook-receiver.php:451` | - |
| `wb_listora_pro_permanent_plan_failure_codes` | filter | array | `includes/features/class-pricing-plans.php:1415` | - |
| `wb_listora_pro_plan_activated` | action | $listing_id, $plan_post->ID, $user_id | `includes/features/class-pricing-plans.php:4061` | - |
| `wb_listora_pro_plan_auto_publish` | filter | mixed $auto_publish, mixed $is_paid, mixed $listing_id, mixed $plan_post, mixed $context | `includes/features/class-pricing-plans.php:3913` | - |
| `wb_listora_pro_plan_capabilities` | filter | _(none)_ | `includes/features/class-pricing-plans.php:2252` | - |
| `wb_listora_pro_plan_currency` | filter | string $currency | `includes/features/class-pricing-plans.php:3513` | - |
| `wb_listora_pro_plan_ineligible` | action | int, int, string | `includes/features/class-pricing-plans.php:1268` | - |
| `wb_listora_pro_plan_trial_consumed` | action | int $user_id, int $plan_id | `includes/features/class-pricing-plans.php:4028` | - |
| `wb_listora_pro_receipt_accept_legacy_tokens` | filter | _(none)_ | `includes/features/class-receipt.php:286` | - |
| `wb_listora_pro_receipt_data` | filter | array $receipt, object $entry | `includes/features/class-receipt.php:524` | - |
| `wb_listora_pro_receipt_invoice_number` | filter | string $invoice_number, object $entry | `includes/features/class-receipt.php:1029` | - |
| `wb_listora_pro_receipt_link_ttl` | filter | _(none)_ | `includes/features/class-receipt.php:200` | - |
| `wb_listora_pro_require_plan_on_admin_save` | filter | bool $required, int $post_id | `includes/features/class-pricing-plans.php:1120` | - |
| `wb_listora_pro_webhook_credit_events` | filter | string[] $events | `includes/features/class-webhook-receiver.php:150` | - |
| `wb_listora_rest_prepare_coupon` | filter | $coupon, $request, isset( $coupon['id'] ) ? (int) $coupon['id'] : 0 | `includes/rest/class-coupons-controller.php:242` | - |
| `wb_listora_rest_prepare_coupon_redemption` | filter | array $prepared, WP_REST_Request $request, object $row | `includes/rest/class-coupons-controller.php:375` | - |
| `wb_listora_rest_prepare_credit_balance` | filter | $response, $request, $user_id | `includes/rest/class-credits-controller.php:277` | - |
| `wb_listora_rest_prepare_credit_ledger_entry` | filter | mixed $entry, mixed $request, mixed $user_id, mixed $history, mixed $response | `includes/rest/class-credits-controller.php:230` | - |
| `wb_listora_rest_prepare_credit_pack` | filter | $pack, $request | `includes/rest/class-credits-controller.php:768` | - |
| `wb_listora_rest_prepare_plan` | filter | $plan, $request, isset( $plan['id'] ) ? (int) $plan['id'] : 0 | `includes/features/class-pricing-plans.php:169` | - |
| `wb_listora_rest_prepare_user_coupon_redemption` | filter | array $prepared, WP_REST_Request $request, int $user_id | `includes/rest/class-credits-controller.php:259` | - |

### Needs (Reverse) (62)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_needs_has_archive` | filter | bool $has_archive | `includes/needs/class-needs-slug.php:202` | - |
| `wb_listora_pro_after_close_need` | action | (int) $need_id, $request | `includes/rest/class-needs-controller.php:940` | - |
| `wb_listora_pro_after_create_need` | action | (int) $need_id, array( 'post_data' => $post_data, 'meta' => $meta_fields, ), $request | `includes/rest/class-needs-controller.php:604` | `wb-listora-pro` |
| `wb_listora_pro_after_delete_need` | action | (int) $need_id, $request | `includes/features/class-reverse-listings.php:220` | - |
| `wb_listora_pro_after_email_need_approved` | action | mixed $approved_vars | `templates/emails/need-approved.php:65` | - |
| `wb_listora_pro_after_email_need_match` | action | $need_match_vars | `templates/emails/need-match.php:129` | - |
| `wb_listora_pro_after_email_need_pending_mod` | action | mixed $pending_vars | `templates/emails/need-pending-mod.php:80` | - |
| `wb_listora_pro_after_email_need_rejected` | action | mixed $rejected_vars | `templates/emails/need-rejected.php:76` | - |
| `wb_listora_pro_after_email_need_response` | action | $response_vars | `templates/emails/need-response.php:97` | - |
| `wb_listora_pro_after_email_response_accepted` | action | mixed $accepted_vars | `templates/emails/response-accepted.php:86` | - |
| `wb_listora_pro_after_email_response_rejected` | action | mixed $rejected_vars | `templates/emails/response-rejected.php:68` | - |
| `wb_listora_pro_after_needs_grid` | action | $view_data | `blocks/needs-grid/render.php:271` | - |
| `wb_listora_pro_after_post_need` | action | $view_data | `blocks/post-need/render.php:219` | - |
| `wb_listora_pro_after_update_need` | action | (int) $need_id, $request | `includes/rest/class-needs-controller.php:800` | - |
| `wb_listora_pro_before_close_need` | filter | true, $need_id, $request | `includes/rest/class-needs-controller.php:927` | - |
| `wb_listora_pro_before_create_need` | filter | true, $post_data, $request | `includes/rest/class-needs-controller.php:541` | - |
| `wb_listora_pro_before_delete_need` | filter | true, $need_id, $request | `includes/features/class-reverse-listings.php:198` | - |
| `wb_listora_pro_before_email_need_approved` | action | mixed $approved_vars, mixed $header_title, mixed $colors, mixed $recipient_name | `templates/emails/need-approved.php:20` | - |
| `wb_listora_pro_before_email_need_match` | action | $need_match_vars | `templates/emails/need-match.php:39` | - |
| `wb_listora_pro_before_email_need_pending_mod` | action | mixed $pending_vars, mixed $header_title, mixed $colors, mixed $recipient_name | `templates/emails/need-pending-mod.php:22` | - |
| `wb_listora_pro_before_email_need_rejected` | action | mixed $rejected_vars, mixed $header_title, mixed $colors, mixed $recipient_name | `templates/emails/need-rejected.php:22` | - |
| `wb_listora_pro_before_email_need_response` | action | $response_vars | `templates/emails/need-response.php:29` | - |
| `wb_listora_pro_before_email_response_accepted` | action | mixed $accepted_vars, mixed $header_title, mixed $colors, mixed $recipient_name | `templates/emails/response-accepted.php:24` | - |
| `wb_listora_pro_before_email_response_rejected` | action | mixed $rejected_vars, mixed $header_title, mixed $colors, mixed $recipient_name | `templates/emails/response-rejected.php:23` | - |
| `wb_listora_pro_before_needs_grid` | action | $view_data | `blocks/needs-grid/render.php:262` | - |
| `wb_listora_pro_before_post_need` | action | $view_data | `blocks/post-need/render.php:205` | - |
| `wb_listora_pro_before_update_need` | filter | true, $need_id, $request | `includes/rest/class-needs-controller.php:719` | - |
| `wb_listora_pro_create_need_capability` | filter | _(none)_ | `includes/rest/class-needs-controller.php:395` | - |
| `wb_listora_pro_dashboard_needs_bundle` | filter | $out, $user_id | `includes/rest/class-need-responses-controller.php:456` | - |
| `wb_listora_pro_email_need_approved_subject` | filter | mixed $subject, mixed $view_data, mixed $body, mixed $headers, mixed $buyer | `includes/needs/class-need-notifier.php:446` | - |
| `wb_listora_pro_email_need_approved_view_data` | filter | mixed $view_data, mixed $buyer, mixed $need, mixed $subject, mixed $body | `includes/needs/class-need-notifier.php:439` | - |
| `wb_listora_pro_email_need_expired_subject` | filter | string $subject, array $view_data | `includes/needs/class-need-notifier.php:175` | - |
| `wb_listora_pro_email_need_match_subject` | filter | $subject, $view_data | `includes/needs/class-need-notifier.php:258` | - |
| `wb_listora_pro_email_need_match_view_data` | filter | $view_data, $owner, $need, $listing | `includes/needs/class-need-notifier.php:246` | - |
| `wb_listora_pro_email_need_pending_mod_subject` | filter | mixed $subject, mixed $view_data, mixed $body, mixed $headers, mixed $mod | `includes/needs/class-need-notifier.php:395` | - |
| `wb_listora_pro_email_need_pending_mod_view_data` | filter | mixed $view_data, mixed $mod, mixed $need, mixed $subject, mixed $body | `includes/needs/class-need-notifier.php:388` | - |
| `wb_listora_pro_email_need_rejected_subject` | filter | mixed $subject, mixed $view_data, mixed $body, mixed $headers, mixed $buyer | `includes/needs/class-need-notifier.php:499` | - |
| `wb_listora_pro_email_need_rejected_view_data` | filter | mixed $view_data, mixed $buyer, mixed $need, mixed $subject, mixed $body | `includes/needs/class-need-notifier.php:492` | - |
| `wb_listora_pro_email_need_response_subject` | filter | $subject, $view_data | `includes/needs/class-need-notifier.php:321` | - |
| `wb_listora_pro_email_need_response_view_data` | filter | $view_data, $need_author, $need, $listing, $response | `includes/needs/class-need-notifier.php:312` | - |
| `wb_listora_pro_email_need_updated_subject` | filter | string $subject, array $view_data | `includes/needs/class-need-notifier.php:94` | - |
| `wb_listora_pro_email_response_closed_subject` | filter | string $subject, array $view_data | `includes/needs/class-need-notifier.php:140` | - |
| `wb_listora_pro_email_response_status_view_data` | filter | mixed $view_data, mixed $vendor, mixed $need, mixed $status, mixed $subject | `includes/needs/class-need-notifier.php:567` | - |
| `wb_listora_pro_need_card_view_data` | filter | $need | `templates/blocks/needs-grid/needs-grid.php:148` | - |
| `wb_listora_pro_need_closed` | action | $need_id | `includes/features/class-reverse-listings.php:1440` | - |
| `wb_listora_pro_need_default_days` | filter | int $days | `includes/needs/class-need-expiration.php:76` | - |
| `wb_listora_pro_need_detail_view_data` | filter | $view_data, $need_id, $need | `includes/frontend/class-need-single-template.php:390` | - |
| `wb_listora_pro_need_expired` | action | $need->ID | `includes/needs/class-need-expiration.php:199` | - |
| `wb_listora_pro_need_fulfilled` | action | mixed $response_id, mixed $response, mixed $context, mixed $action_slug | `includes/needs/class-need-response-manager.php:707` | - |
| `wb_listora_pro_need_match_radius` | filter | int $radius | `includes/needs/class-need-matcher.php:42` | - |
| `wb_listora_pro_need_published` | action | $post->ID | `includes/features/class-reverse-listings.php:1341` | `wb-listora-pro` |
| `wb_listora_pro_need_rejected` | action | int $post_id | `includes/features/class-reverse-listings.php:1174` | - |
| `wb_listora_pro_need_response_closed` | action | int $response_id, array $context | `includes/needs/class-need-response-manager.php:783` | - |
| `wb_listora_pro_need_response_cost` | filter | mixed $cost, mixed $raw, mixed $data, mixed $need_id, mixed $listing_id | `includes/needs/class-need-response-manager.php:56` | - |
| `wb_listora_pro_need_response_created` | action | mixed $response_id, mixed $need_id, mixed $listing_id, mixed $user_id, mixed $context | `includes/needs/class-need-response-manager.php:324` | - |
| `wb_listora_pro_need_updated_after_quotes` | action | int $need_id | `includes/rest/class-needs-controller.php:789` | - |
| `wb_listora_pro_needs_grid_view_data` | filter | $view_data, $attributes | `blocks/needs-grid/render.php:248` | - |
| `wb_listora_pro_post_need_view_data` | filter | $view_data, $attributes | `blocks/post-need/render.php:198` | - |
| `wb_listora_pro_refund_needs_admin_attention` | action | int $user_id, float $amount, array $context | `includes/features/class-pricing-plans.php:1812` | - |
| `wb_listora_pro_user_need_quota` | filter | mixed $raw, mixed $user_id | `includes/features/class-reverse-listings.php:260` | - |
| `wb_listora_rest_prepare_need` | filter | $data, $post | `includes/rest/class-needs-controller.php:1099` | - |
| `wb_listora_rest_prepare_need_response` | filter | $data, $response | `includes/rest/class-need-responses-controller.php:627` | - |

### Members & Roles (7)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_pro_after_email_moderator_reassigned` | action | $reassign_vars | `templates/emails/moderator-reassigned.php:81` | - |
| `wb_listora_pro_before_email_moderator_reassigned` | action | $reassign_vars | `templates/emails/moderator-reassigned.php:29` | - |
| `wb_listora_pro_email_moderator_reassigned_subject` | filter | $subject, $view_data | `includes/features/class-moderator.php:1728` | - |
| `wb_listora_pro_email_moderator_reassigned_view_data` | filter | $view_data, $to_user, $from_user, $reassigned | `includes/features/class-moderator.php:1725` | - |
| `wb_listora_pro_moderator_assigned` | action | $moderator_id, $object_id, $object_type | `includes/features/class-moderator.php:293` | - |
| `wb_listora_pro_moderator_reassigned` | action | $from_id, $to_id, (int) $updated | `includes/features/class-moderator.php:1678` | - |
| `wb_listora_pro_moderators_per_page` | filter | int $per_page | `includes/features/class-moderator.php:1032` | - |

### Notifications (24)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_email_content_{$event}` | filter | string $value, array $vars | `includes/class-email-helpers.php:109` | - |
| `wb_listora_email_footer_text` | filter | '', $event, $view_data | `includes/class-email-helpers.php:142` | - |
| `wb_listora_email_logo_url` | filter | '', $event, $view_data | `includes/class-email-helpers.php:141` | - |
| `wb_listora_email_subject_{$event}` | filter | string $value, array $vars | `includes/class-email-helpers.php:109` | - |
| `wb_listora_pro_after_email_digest` | action | $digest_vars | `templates/emails/digest.php:88` | - |
| `wb_listora_pro_after_email_lead` | action | $lead_vars | `templates/emails/lead-notification.php:78` | - |
| `wb_listora_pro_after_outgoing_webhook` | action | (int) $log_id, array( 'webhook_id' => $webhook_id, 'event' => (string) $lo | `includes/features/class-outgoing-webhooks.php:1089` | - |
| `wb_listora_pro_after_outgoing_webhook_failed` | action | (int) $log_id, array( 'webhook_id' => $webhook_id, 'event' => (string) $lo | `includes/features/class-outgoing-webhooks.php:1111` | - |
| `wb_listora_pro_before_email_digest` | action | $digest_vars | `templates/emails/digest.php:33` | - |
| `wb_listora_pro_before_email_lead` | action | $lead_vars | `templates/emails/lead-notification.php:32` | - |
| `wb_listora_pro_before_outgoing_webhook` | filter | true, $event, $data | `includes/features/class-outgoing-webhooks.php:773` | - |
| `wb_listora_pro_email_digest_subject` | filter | $subject, $view_data | `includes/features/class-notification-digest.php:473` | - |
| `wb_listora_pro_email_digest_view_data` | filter | $view_data, $to | `includes/features/class-notification-digest.php:463` | - |
| `wb_listora_pro_email_headers` | filter | array( 'Content-Type: text/html; charset=UTF-8' ), 'need_match', $view_data | `includes/features/class-advanced-search.php:759` | - |
| `wb_listora_pro_email_{$event}_subject` | filter | mixed $subject, mixed $event, mixed $view_data, mixed $body, mixed $template | `includes/needs/class-need-notifier.php:580` | - |
| `wb_listora_pro_urgent_notification_events` | filter | $urgent_events, $event, $vars | `includes/features/class-notification-digest.php:352` | - |
| `wb_listora_pro_webhook_delivery_retried` | action | int $log_id | `includes/features/class-outgoing-webhooks.php:2150` | - |
| `wb_listora_pro_webhook_max_age` | filter | 300 | `includes/features/class-webhook-receiver.php:177` | - |
| `wb_listora_pro_webhook_strict_hmac` | filter | (bool) get_option( self::STRICT_HMAC_OPTION, true ) | `includes/features/class-webhook-receiver.php:537` | - |
| `wb_listora_rest_prepare_outgoing_webhook` | filter | $item, $request, $webhook | `includes/features/class-outgoing-webhooks.php:1635` | - |
| `wb_listora_rest_prepare_webhook_delivery` | filter | array $prepared, object $row | `includes/features/class-outgoing-webhooks.php:2065` | - |
| `wb_listora_rest_prepare_webhook_delivery_log` | filter | $row, $request, (int) $webhook_id | `includes/features/class-outgoing-webhooks.php:1967` | - |
| `wb_listora_rest_prepare_webhook_payload` | filter | $data, $request | `includes/rest/class-webhook-controller.php:56` | - |
| `wb_listora_send_notification` | filter | mixed $event, mixed $context, mixed $user_id | `includes/class-email-helpers.php:77` | - |

### Admin & Settings (6)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_dashboard_per_page` | filter | _(none)_ | `includes/features/class-needs-dashboard-tab.php:142` | - |
| `wb_listora_pro_badges_per_page` | filter | int $per_page | `includes/features/class-badges.php:1757` | - |
| `wb_listora_pro_feature_page_map` | filter | array $map | `includes/functions.php:581` | - |
| `wb_listora_pro_free_setting` | filter | $value, $key, $default | `includes/class-settings-helper.php:54` | - |
| `wb_listora_pro_seo_page_use_theme_template` | filter | bool $use_theme, array $context | `includes/features/class-seo-pages.php:505` | - |
| `wb_listora_pro_show_gateway_settings` | filter | mixed $views, mixed $descriptions | `includes/admin/class-credit-mappings.php:705` | - |

### Templates & Display (17)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_pro_after_assign_badge` | action | $post_id, $context | `includes/features/class-badges.php:1281` | - |
| `wb_listora_pro_after_create_badge` | action | $post_id, $data | `includes/features/class-badges.php:324` | - |
| `wb_listora_pro_after_delete_badge` | action | $badge_id | `includes/features/class-badges.php:1444` | - |
| `wb_listora_pro_after_unassign_badge` | action | $post_id, $context | `includes/features/class-badges.php:1290` | - |
| `wb_listora_pro_after_update_badge` | action | $badge_id, $data | `includes/features/class-badges.php:394` | - |
| `wb_listora_pro_before_assign_badge` | filter | true, $context | `includes/features/class-badges.php:1265` | - |
| `wb_listora_pro_before_create_badge` | filter | true, $data | `includes/features/class-badges.php:286` | - |
| `wb_listora_pro_before_delete_badge` | filter | true, $badge_id | `includes/features/class-badges.php:1429` | - |
| `wb_listora_pro_before_unassign_badge` | filter | true, $context, $request | `includes/rest/class-badges-controller.php:436` | - |
| `wb_listora_pro_before_update_badge` | filter | true, $badge_id, $data | `includes/features/class-badges.php:349` | - |
| `wb_listora_pro_card_native_badge_slugs` | filter | _(none)_ | `includes/features/class-badges.php:980` | - |
| `wb_listora_pro_detail_native_badge_slugs` | filter | string[] $slugs, int $listing_id, string $type_slug | `includes/features/class-badges.php:907` | - |
| `wb_listora_pro_feature_as_hook_map` | filter | mixed $map | `includes/functions.php:472` | - |
| `wb_listora_pro_quick_view_badges` | filter | _(none)_ | `includes/features/class-quick-view.php:336` | - |
| `wb_listora_rest_prepare_badge` | filter | $item, $request, $badge_post | `includes/rest/class-badges-controller.php:199` | - |
| `wb_listora_rest_prepare_field_map` | filter | $fields, $request, $type_slug | `includes/rest/class-visual-import-controller.php:611` | - |
| `wb_listora_rest_prepare_import_field_map` | filter | $fields, $request, $type_slug | `includes/rest/class-visual-import-controller.php:598` | - |

### Other (41)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_pro_after_comparison_block` | action | $attributes, $listings | `blocks/comparison/render.php:493` | - |
| `wb_listora_pro_after_google_import` | action | $results, $context, $request | `includes/rest/class-google-import-controller.php:337` | - |
| `wb_listora_pro_after_send_alert` | action | (int) $user->ID, $context | `includes/features/class-advanced-search.php:774` | - |
| `wb_listora_pro_after_visual_import` | action | $result, $context, $request | `includes/rest/class-visual-import-controller.php:449` | - |
| `wb_listora_pro_after_{$action_slug}` | action | mixed $action_slug, mixed $response_id, mixed $response, mixed $status | `includes/needs/class-need-response-manager.php:720` | - |
| `wb_listora_pro_after_{$action}` | action | mixed $action, mixed $post_id, mixed $is_create, mixed $columns, mixed $new | `includes/features/class-pricing-plans.php:2709` | - |
| `wb_listora_pro_analytics_click_window` | filter | int $seconds, int $listing_id, string $event_type | `includes/features/class-analytics.php:521` | - |
| `wb_listora_pro_analytics_view_window` | filter | _(none)_ | `includes/features/class-analytics.php:253` | - |
| `wb_listora_pro_audit_log` | action | // phpcs:ignore WordPress.NamingConventions.PrefixAllGlob..., $object_type, $object_id, array( 'moderator_id' => $moderator_id, 'moderator_name'... | `includes/features/class-moderator.php:310` | - |
| `wb_listora_pro_before_google_import` | filter | true, $context, $request | `includes/rest/class-google-import-controller.php:319` | - |
| `wb_listora_pro_before_send_alert` | filter | true, $context | `includes/features/class-advanced-search.php:725` | - |
| `wb_listora_pro_before_visual_import` | filter | true, $context, $request | `includes/rest/class-visual-import-controller.php:425` | - |
| `wb_listora_pro_before_{$action_slug}` | filter | mixed $check, mixed $action_slug, mixed $response_id, mixed $response, mixed $status | `includes/needs/class-need-response-manager.php:664` | - |
| `wb_listora_pro_before_{$action}` | filter | mixed $check, mixed $action, mixed $post_id, mixed $is_create, mixed $fields | `includes/features/class-pricing-plans.php:2593` | - |
| `wb_listora_pro_compare_group_by_type` | filter | false, $listings | `blocks/comparison/render.php:295` | - |
| `wb_listora_pro_default_features` | filter | $defaults | `includes/functions.php:258` | - |
| `wb_listora_pro_feature_{$key}_enabled` | filter | mixed $key, mixed $enabled, mixed $features | `includes/functions.php:374` | - |
| `wb_listora_pro_features_registry` | filter | $registry | `includes/functions.php:202` | - |
| `wb_listora_pro_has_purchase_path` | filter | _(none)_ | `includes/class-monetization.php:330` | - |
| `wb_listora_pro_migration_dispatched` | action | string $run_id, array $payload | `includes/features/class-competitor-migration.php:283` | - |
| `wb_listora_pro_migration_run_id` | filter | string $run_id | `includes/features/class-competitor-migration.php:295` | - |
| `wb_listora_pro_pack_consumed` | action | mixed $user_id, mixed $plan_post, mixed $remaining_after, mixed $listing_id, mixed $context | `includes/features/class-pricing-plans.php:4002` | - |
| `wb_listora_pro_pack_purchased` | action | mixed $user_id, mixed $plan_post, mixed $plan_quota, mixed $listing_id, mixed $context | `includes/features/class-pricing-plans.php:3991` | - |
| `wb_listora_pro_public_rest_rate_limit` | filter | int $limit, string $bucket | `includes/class-public-rate-limiter.php:111` | - |
| `wb_listora_pro_public_rest_rate_limit_bypass` | filter | bool $bypass, string $bucket | `includes/class-public-rate-limiter.php:90` | - |
| `wb_listora_pro_purchase_refund_settled` | action | int $user_id, float $amount, array $summary, array $context | `includes/features/class-pricing-plans.php:1779` | - |
| `wb_listora_pro_seo_theme_provides_title` | filter | $decided, $seo_title, $header_html | `includes/features/class-seo-pages.php:687` | - |
| `wb_listora_pro_service_book` | action | (int) $service_id, (int) $listing_id | `includes/features/class-services-pro.php:250` | - |
| `wb_listora_pro_service_booking_url` | filter | '', $service_id, $listing_id | `includes/features/class-services-pro.php:275` | - |
| `wb_listora_pro_trust_proxy_headers` | filter | bool $trust | `includes/class-public-rate-limiter.php:283` | - |
| `wb_listora_pro_visibility_rest_allowed_routes` | filter | array, string | `includes/features/class-coming-soon.php:154` | - |
| `wb_listora_pro_visibility_should_gate` | filter | true, $mode | `includes/features/class-coming-soon.php:390` | - |
| `wb_listora_pro_white_label_pro_suffix` | filter | string, string | `includes/features/class-white-label.php:344` | - |
| `wb_listora_rest_prepare_analytics_event` | filter | $response, $request, (int) $listing_id, (string) $event_type | `includes/features/class-analytics.php:505` | - |
| `wb_listora_rest_prepare_analytics_summary` | filter | $response, $request, (int) $listing_id | `includes/features/class-analytics.php:599` | - |
| `wb_listora_rest_prepare_audit_log_entry` | filter | $item, $request, isset( $item['id'] ) ? (int) $item['id'] : 0 | `includes/features/class-audit-log.php:1003` | - |
| `wb_listora_rest_prepare_import_preview` | filter | $response, $request | `includes/rest/class-visual-import-controller.php:304` | - |
| `wb_listora_rest_prepare_import_status` | filter | $status, $request, (string) $batch_id | `includes/rest/class-visual-import-controller.php:475` | - |
| `wb_listora_rest_prepare_places_preview` | filter | $details, $request, (string) $place_id | `includes/rest/class-google-import-controller.php:273` | - |
| `wb_listora_rest_prepare_service_pro` | filter | $result, $service_id | `includes/features/class-services-pro.php:602` | - |
| `wb_listora_trust_proxy_headers` | filter | false | `includes/class-public-rate-limiter.php:269` | - |

### Deprecated (5)

Still fired, with a deprecation notice. Move to the replacement before the version that removes them.

| Hook | Type | Deprecated in | Removed in | Use instead | Fired at |
|---|---|---|---|---|---|
| `wb_listora_pro_after_submit_lead` | action | 2.0.0 | 2.2.0 | `wb_listora_after_contact_form_submit` | `includes/features/class-lead-form.php:131` |
| `wb_listora_pro_before_submit_lead` | filter | 2.0.0 | 2.2.0 | `wb_listora_before_contact_form_submit` | `includes/features/class-lead-form.php:119` |
| `wb_listora_pro_email_lead_notification_subject` | filter | 2.0.0 | 2.2.0 | `wb_listora_email_subject_lead_received` | `includes/features/class-lead-form.php:153` |
| `wb_listora_pro_lead_per_listing_daily_cap` | filter | 2.0.0 | 2.2.0 | `wb_listora_contact_form_per_listing_daily_cap` | `includes/features/class-lead-form.php:142` |
| `wb_listora_rest_prepare_lead` | filter | 2.0.0 | 2.2.0 | _(no replacement)_ | `includes/features/class-lead-form.php:102` |
