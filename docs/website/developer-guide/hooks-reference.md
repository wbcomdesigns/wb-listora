# Hooks Reference (Actions & Filters)

Every WB Listora action and filter, grounded in the current manifest at `audit/manifest.json`. The plugin fires **197 hooks** across the categories below — **108 actions** + **89 filters**. Pro consumes a subset of these; the **Consumed by** column lists every Pro listener so you can see at a glance which hooks already have wiring.

*Generated from `audit/manifest.json`. Re-run `/wp-plugin-onboard --refresh` after non-trivial commits to regenerate.*

## Naming convention

- `wb_listora_before_{action}_{resource}` — write-lifecycle **filter**; return `WP_Error` to abort the write.
- `wb_listora_after_{action}_{resource}` — write-lifecycle **action**; canonical extension point.
- `wb_listora_rest_prepare_{resource}` — modify REST response shape; receives `($data, $context, $request)`.
- `wb_listora_{event}` — domain event (e.g. `wb_listora_listing_submitted`).

## Bootstrap (2)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_loaded` | action | _(none)_ | `class-plugin.php:49` | `wb-listora-pro` |
| `wb_listora_rest_api_init` | action | _(none)_ | `class-plugin.php:251` | `wb-listora-pro` |

## Listings — Write-Lifecycle (25)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_approve_listing` | action | `WP_Post|int $post->ID` | `wb-listora.php:406` | — |
| `wb_listora_after_bulk_moderate` | action | `string $action, array $ok, array $failed` | `rest/class-listings-controller.php:531` | — |
| `wb_listora_after_contact_form_submit` | action | `int $listing_id, array $context, WP_REST_Request $request` | `class-contact-form.php:253` | — |
| `wb_listora_after_create_listing` | action | `int $post_id, WP_REST_Request $request` | `rest/class-submission-controller.php:512` | `wb-listora-pro` |
| `wb_listora_after_dashboard_listings` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/tab-listings.php:404` | — |
| `wb_listora_after_deactivate_listing` | action | `int $post_id, WP_REST_Request $request` | `rest/class-listings-controller.php:970` | — |
| `wb_listora_after_delete_listing` | action | `int $post_id, WP_REST_Request $request` | `rest/class-listings-controller.php:876` | `wb-listora-pro` |
| `wb_listora_after_feature_listing` | action | `int $post_id, mixed($context) $context` | `core/class-featured.php:179` | — |
| `wb_listora_after_featured_listings` | action | `mixed($featured_block_attributes) $featured_block_attributes` | `blocks/listing-featured/render.php:96` | — |
| `wb_listora_after_listing_fields` | action | `int $post_id, mixed($detail_type_slug) $detail_type_slug` | `templates/blocks/listing-detail/sidebar.php:70` | `wb-listora-pro` |
| `wb_listora_after_listing_grid` | action | `mixed($grid_block_attributes) $grid_block_attributes` | `blocks/listing-grid/render.php:216` | — |
| `wb_listora_after_reactivate_listing` | action | `int $post_id, WP_REST_Request $request` | `rest/class-listings-controller.php:1106` | — |
| `wb_listora_after_reject_listing` | action | `WP_Post|int $post->ID` | `wb-listora.php:410` | — |
| `wb_listora_after_renew_listing` | action | `int $post_id, mixed` | `rest/class-listings-controller.php:1562` | `wb-listora-pro` |
| `wb_listora_after_unfeature_listing` | action | `int $post_id, mixed($reason) $reason` | `core/class-featured.php:228` | — |
| `wb_listora_after_update_listing` | action | `int $post_id, WP_REST_Request $request` | `rest/class-submission-controller.php:676` | `wb-listora-pro` |
| `wb_listora_before_create_listing` | filter | `bool, mixed($title) $title, WP_REST_Request $request` | `rest/class-submission-controller.php:405` | — |
| `wb_listora_before_dashboard_listings` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/tab-listings.php:24` | — |
| `wb_listora_before_delete_listing` | filter | `bool, int $post_id, WP_REST_Request $request` | `rest/class-listings-controller.php:843` | — |
| `wb_listora_before_feature_listing` | filter | `bool, int $post_id, mixed($context) $context` | `core/class-featured.php:156` | — |
| `wb_listora_before_featured_listings` | action | `mixed($featured_block_attributes) $featured_block_attributes` | `blocks/listing-featured/render.php:75` | — |
| `wb_listora_before_listing_grid` | action | `mixed($grid_block_attributes) $grid_block_attributes` | `blocks/listing-grid/render.php:209` | — |
| `wb_listora_before_renew_listing` | filter | `bool, int $post_id, mixed($context) $context` | `rest/class-listings-controller.php:1469` | `wb-listora-pro` |
| `wb_listora_before_unfeature_listing` | filter | `bool, int $post_id, mixed($context) $context` | `core/class-featured.php:214` | — |
| `wb_listora_before_update_listing` | filter | `bool, int $post_id, WP_REST_Request $request` | `rest/class-submission-controller.php:584` | — |

## Reviews — Write-Lifecycle (10)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_create_review` | action | `int $review_id, int $listing_id, WP_REST_Request $request` | `rest/class-reviews-controller.php:533` | `wb-listora-pro` |
| `wb_listora_after_dashboard_reviews` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/tab-reviews.php:80` | — |
| `wb_listora_after_delete_review` | action | `int $review_id, int $review ? (int) $review->listing_id : null, WP…` | `rest/class-reviews-controller.php:682` | `wb-listora-pro` |
| `wb_listora_after_reviews` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-reviews/reviews.php:128` | — |
| `wb_listora_after_update_review` | action | `int $review_id, WP_REST_Request $request` | `rest/class-reviews-controller.php:615` | `wb-listora-pro` |
| `wb_listora_before_create_review` | filter | `bool, int $listing_id, WP_REST_Request $request` | `rest/class-reviews-controller.php:455` | — |
| `wb_listora_before_dashboard_reviews` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/tab-reviews.php:20` | — |
| `wb_listora_before_delete_review` | filter | `bool, int $review_id, WP_REST_Request $request` | `rest/class-reviews-controller.php:649` | — |
| `wb_listora_before_reviews` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-reviews/reviews.php:31` | — |
| `wb_listora_before_update_review` | filter | `bool, int $review_id, WP_REST_Request $request` | `rest/class-reviews-controller.php:573` | — |

## Favorites — Write-Lifecycle (4)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_add_favorite` | action | `int $listing_id, int $user_id, WP_REST_Request $request` | `rest/class-favorites-controller.php:258` | `wb-listora-pro` |
| `wb_listora_after_remove_favorite` | action | `int $listing_id, int $user_id, WP_REST_Request $request` | `rest/class-favorites-controller.php:316` | `wb-listora-pro` |
| `wb_listora_before_add_favorite` | filter | `bool, int $listing_id, WP_REST_Request $request` | `rest/class-favorites-controller.php:205` | — |
| `wb_listora_before_remove_favorite` | filter | `bool, int $listing_id, WP_REST_Request $request` | `rest/class-favorites-controller.php:290` | — |

## Claims — Write-Lifecycle (6)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_dashboard_claims` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/tab-claims.php:123` | — |
| `wb_listora_after_submit_claim` | action | `int $claim_id, int $listing_id, WP_REST_Request $request` | `rest/class-claims-controller.php:219` | `wb-listora-pro` |
| `wb_listora_after_update_claim` | action | `int $claim_id, mixed($new_status) $new_status, WP_REST_Request $re…` | `rest/class-claims-controller.php:549` | `wb-listora-pro` |
| `wb_listora_before_dashboard_claims` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/tab-claims.php:36` | — |
| `wb_listora_before_submit_claim` | filter | `bool, int $listing_id, WP_REST_Request $request` | `rest/class-claims-controller.php:169` | — |
| `wb_listora_before_update_claim` | filter | `bool, int $claim_id, WP_REST_Request $request` | `rest/class-claims-controller.php:481` | — |

## Services — Write-Lifecycle (6)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_create_service` | action | `int $service_id, array|mixed $data` | `core/class-services.php:196` | `wb-listora-pro` |
| `wb_listora_after_delete_service` | action | `int $service_id, mixed($existing) $existing` | `core/class-services.php:342` | `wb-listora-pro` |
| `wb_listora_after_update_service` | action | `int $service_id, array|mixed $data` | `core/class-services.php:289` | `wb-listora-pro` |
| `wb_listora_before_create_service` | filter | `array|mixed $data` | `core/class-services.php:117` | `wb-listora-pro` |
| `wb_listora_before_delete_service` | filter | `int $service_id` | `core/class-services.php:316` | — |
| `wb_listora_before_update_service` | filter | `array|mixed $data, int $service_id` | `core/class-services.php:227` | `wb-listora-pro` |

## Listing Lifecycle Events (11)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_claim_submitted` | action | `int $claim_id, int $listing_id, int $user_id` | `rest/class-claims-controller.php:210` | — |
| `wb_listora_expired_listing_notice` | filter | `mixed($message) $message, mixed` | `class-plugin.php:432` | — |
| `wb_listora_is_verified` | filter | `bool $verified, int $post_id` | `class-features.php:232` | `wb-listora-pro` |
| `wb_listora_listing_claimed` | action | `int $listing_id, array $context` | `rest/class-claims-controller.php:512` | `wb-listora-pro` |
| `wb_listora_listing_expiration_date` | filter | `string $expiry_iso, int $post_id, array $context` | `workflow/class-status-manager.php:99,118; rest/class-listings-controller.php:1654` | `wb-listora-pro` |
| `wb_listora_listing_expired` | action | `int $post_id` | `workflow/class-expiration-cron.php:169` | `wb-listora-pro` |
| `wb_listora_listing_renewed` | action | `int $post_id` | `rest/class-listings-controller.php:1572` | — |
| `wb_listora_listing_status_changed` | action | `WP_Post|int $post->ID, mixed($new) $new, mixed($old) $old` | `search/class-search-indexer.php:553` | `wb-listora` · `wb-listora-pro` |
| `wb_listora_listing_submitted` | action | `int $post_id, mixed($new_status) $new_status, mixed($synthetic_req…` | `admin/class-listing-columns.php:470` | `wb-listora-pro` |
| `wb_listora_review_status_changed` | action | `int $review_id, string $status, int $listing_id` | `rest/class-reviews-controller.php:650` | — |
| `wb_listora_review_submitted` | action | `int $review_id, int $listing_id, int $user_id, mixed($criteria_rat…` | `rest/class-reviews-controller.php:524` | `wb-listora-pro` |

## Review Events (5)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_review_after_content` | action | `mixed($review) $review` | `templates/blocks/listing-reviews/review-card.php:54` | `wb-listora-pro` |
| `wb_listora_review_criteria` | filter | `array, mixed($listing_type_slug) $listing_type_slug` | `blocks/listing-reviews/render.php:78` | `wb-listora-pro` |
| `wb_listora_review_form_after_content` | action | `int $post_id` | `templates/blocks/listing-detail/tabs.php:399` | `wb-listora-pro` |
| `wb_listora_review_helpful_milestone` | action | `int $review_id, mixed($new_count) $new_count` | `rest/class-reviews-controller.php:772` | — |
| `wb_listora_review_reply` | action | `int $review_id` | `rest/class-reviews-controller.php:807` | — |

## REST Response Filters (8)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_rest_prepare_claim` | filter | `mixed($response_data) $response_data, int $claim_id, WP_REST_Reque…` | `rest/class-claims-controller.php:238` | — |
| `wb_listora_rest_prepare_dashboard_stats` | filter | `array|mixed $data, int $user_id, WP_REST_Request $request` | `rest/class-dashboard-controller.php:284` | — |
| `wb_listora_rest_prepare_favorite` | filter | `mixed($fav_data) $fav_data, int, WP_REST_Request $request` | `rest/class-favorites-controller.php:155` | — |
| `wb_listora_rest_prepare_listing` | filter | `array|mixed $data, WP_Post|int $post, WP_REST_Request $request` | `rest/class-listings-controller.php:777` | `wb-listora-pro` |
| `wb_listora_rest_prepare_listing_type` | filter | `mixed($type_data) $type_data, mixed($type) $type, WP_REST_Request …` | `rest/class-listing-types-controller.php:152` | — |
| `wb_listora_rest_prepare_review` | filter | `mixed($review_data) $review_data, int, WP_REST_Request $request` | `rest/class-reviews-controller.php:342` | `wb-listora-pro` |
| `wb_listora_rest_prepare_search_result` | filter | `mixed($response_data) $response_data, WP_REST_Request $request` | `rest/class-search-controller.php:290` | `wb-listora-pro` |
| `wb_listora_rest_prepare_service` | filter | `array|mixed $data, int, WP_REST_Request $request` | `rest/class-services-controller.php:421` | `wb-listora-pro` |

## Block Render (8)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_calendar_events` | filter | `mixed($events) $events, mixed($attributes) $attributes` | `blocks/listing-calendar/render.php:125` | — |
| `wb_listora_card_actions` | action | `int|string $id` | `templates/blocks/listing-card/card-actions.php:27` | `wb-listora-pro` |
| `wb_listora_card_view_data` | filter | `mixed($card_data) $card_data, int $post_id, WP_Post|int $post` | `class-template-helpers.php:517` | — |
| `wb_listora_category_card_data` | filter | `array, mixed($cat) $cat` | `templates/blocks/listing-categories/categories.php:49` | — |
| `wb_listora_grid_after_card` | action | `mixed($listing['id']) $listing['id'], mixed($grid_block_attributes…` | `templates/blocks/listing-grid/grid.php:71` | — |
| `wb_listora_grid_query_args` | filter | `mixed($search_args) $search_args, mixed($attributes) $attributes` | `blocks/listing-grid/render.php:95` | — |
| `wb_listora_map_config` | filter | `mixed($map_config) $map_config` | `blocks/listing-map/render.php:113` | `wb-listora-pro` |
| `wb_listora_map_provider` | filter | `string $value` | `wb-listora.php:288` | `wb-listora-pro` |

## Search & Submission (2)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_search_args` | filter | `array $args, WP_REST_Request $request` | `rest/class-search-controller.php:252` | `wb-listora-pro` |
| `wb_listora_search_results` | filter | `mixed($response_data) $response_data, array $args, WP_REST_Request…` | `rest/class-search-controller.php:282` | — |

## Settings & Admin (9)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_reset_settings` | action | `array $option_keys` | `rest/class-settings-controller.php:371` | `wb-listora-pro` |
| `wb_listora_default_features` | filter | `mixed($defaults) $defaults` | `class-features.php:120` | — |
| `wb_listora_feature_duration_days` | filter | `mixed($days) $days, int $post_id` | `core/class-featured.php:140` | — |
| `wb_listora_featured_query_args` | filter | `mixed($featured_q_args) $featured_q_args, mixed($attributes) $attr…` | `blocks/listing-featured/render.php:30` | — |
| `wb_listora_features_registry` | filter | `mixed($registry) $registry` | `class-features.php:99` | — |
| `wb_listora_settings_nav_groups` | filter | `mixed($groups) $groups` | `admin/class-settings-page.php:288` | `wb-listora-pro` |
| `wb_listora_settings_skip_form_tabs` | filter | `array` | `admin/class-settings-page.php:377` | `wb-listora-pro` |
| `wb_listora_settings_tab_content` | action | `int $tab_id` | `admin/class-settings-page.php:469` | `wb-listora-pro` |
| `wb_listora_settings_tabs` | filter | `mixed($tabs) $tabs` | `admin/class-settings-page.php:311` | `wb-listora-pro` |

## Notifications & Email (14)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_contact_form_email_headers` | filter | `array $headers, WP_Post $post` | `class-contact-form.php:239` | — |
| `wb_listora_email_content` | filter | `mixed($body) $body, mixed($event) $event, mixed($vars) $vars` | `workflow/class-notifications.php:907` | — |
| `wb_listora_email_footer_text` | filter | `string, mixed($event) $event, mixed($vars) $vars` | `workflow/class-notifications.php:881` | — |
| `wb_listora_email_from_address` | filter | `mixed, mixed($event) $event, mixed($vars) $vars` | `workflow/class-notifications.php:941` | — |
| `wb_listora_email_from_name` | filter | `mixed($site_name) $site_name, mixed($event) $event, mixed($vars) $…` | `workflow/class-notifications.php:933` | — |
| `wb_listora_email_headers` | filter | `mixed($headers) $headers, mixed($event) $event, mixed($vars) $vars` | `workflow/class-notifications.php:949` | — |
| `wb_listora_email_logo_url` | filter | `string, mixed($event) $event, mixed($vars) $vars` | `workflow/class-notifications.php:870` | — |
| `wb_listora_email_palette` | filter | `array` | `workflow/class-notifications.php:1064` | — |
| `wb_listora_email_subject` | filter | `mixed($subject) $subject, mixed($event) $event, mixed($vars) $vars` | `workflow/class-notifications.php:891` | — |
| `wb_listora_listing_verify_email` | action | `int $post_id, mixed($token) $token` | `workflow/class-email-verification.php:223` | — |
| `wb_listora_notification_log_enabled` | filter | `bool` | `workflow/class-notifications.php:1010` | — |
| `wb_listora_notification_recipients` | filter | `mixed($to) $to, mixed($event) $event, mixed($vars) $vars` | `workflow/class-notifications.php:922` | — |
| `wb_listora_notification_skipped` | action | `mixed($event_key) $event_key, string, mixed($context) $context` | `workflow/class-notifications.php:668` | — |
| `wb_listora_send_notification` | filter | `bool, mixed($event) $event, mixed($vars) $vars` | `workflow/class-notifications.php:840` | `wb-listora-pro` |

## Page Registry (1)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_register_pages` | action | _(none)_ | `page-registry-helpers.php:269` | `wb-listora-pro` |

## Spam & Rate Limit (4)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_captcha_bypass` | filter | `mixed($bypass) $bypass, mixed($provider) $provider` | `class-captcha.php:144` | — |
| `wb_listora_rate_limit_bypass` | filter | `bool, mixed($action) $action, int` | `class-rate-limiter.php:137` | — |
| `wb_listora_rate_limit_config` | filter | `mixed($config) $config, mixed($action) $action` | `class-rate-limiter.php:188` | — |
| `wb_listora_submission_captcha` | action | `int $form_id` | `class-captcha.php:106` | — |

## Templates (2)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_locate_template` | filter | `mixed($template) $template, mixed($template_name) $template_name, …` | `class-template-helpers.php:51` | — |
| `wb_listora_template_args` | filter | `array $args, mixed($template_name) $template_name` | `class-template-helpers.php:81` | — |

## Lifecycle (30)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_after_calendar` | action | `mixed($attributes) $attributes` | `blocks/listing-calendar/render.php:215` | — |
| `wb_listora_after_card` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-card/card.php:66` | — |
| `wb_listora_after_card_actions` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-card/card-actions.php:29` | — |
| `wb_listora_after_card_content` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-card/card-content.php:130` | — |
| `wb_listora_after_card_image` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-card/card-image.php:75` | — |
| `wb_listora_after_categories_grid` | action | `mixed($attributes) $attributes` | `blocks/listing-categories/render.php:89` | — |
| `wb_listora_after_dashboard_credits` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/tab-credits.php:218` | — |
| `wb_listora_after_dashboard_nav` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/nav.php:113` | — |
| `wb_listora_after_dashboard_profile` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/tab-profile.php:86` | — |
| `wb_listora_after_detail_gallery` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-detail/gallery.php:67` | — |
| `wb_listora_after_detail_sidebar` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-detail/sidebar.php:83` | — |
| `wb_listora_after_detail_tabs` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-detail/tabs.php:464` | — |
| `wb_listora_after_email_verified` | action | `int $post_id, mixed($new_status) $new_status` | `admin/class-listing-columns.php:468` | — |
| `wb_listora_after_map` | action | `mixed($attributes) $attributes` | `blocks/listing-map/render.php:155` | — |
| `wb_listora_after_search_results` | action | _(none)_ | `templates/blocks/listing-search/search.php:85` | `wb-listora-pro` |
| `wb_listora_after_template` | action | `mixed($template_name) $template_name, mixed($template_path) $templ…` | `class-template-helpers.php:89` | — |
| `wb_listora_before_calendar` | action | `mixed($attributes) $attributes` | `blocks/listing-calendar/render.php:190` | — |
| `wb_listora_before_card` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-card/card.php:43` | — |
| `wb_listora_before_card_actions` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-card/card-actions.php:18` | — |
| `wb_listora_before_card_content` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-card/card-content.php:31` | — |
| `wb_listora_before_card_image` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-card/card-image.php:27` | — |
| `wb_listora_before_categories_grid` | action | `mixed($attributes) $attributes` | `blocks/listing-categories/render.php:82` | — |
| `wb_listora_before_dashboard_credits` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/tab-credits.php:24` | — |
| `wb_listora_before_dashboard_nav` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/nav.php:29` | — |
| `wb_listora_before_dashboard_profile` | action | `mixed($view_data) $view_data` | `templates/blocks/user-dashboard/tab-profile.php:19` | — |
| `wb_listora_before_detail_gallery` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-detail/gallery.php:26` | — |
| `wb_listora_before_detail_sidebar` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-detail/sidebar.php:24` | — |
| `wb_listora_before_detail_tabs` | action | `mixed($view_data) $view_data` | `templates/blocks/listing-detail/tabs.php:39` | — |
| `wb_listora_before_map` | action | `mixed($attributes) $attributes` | `blocks/listing-map/render.php:135` | — |
| `wb_listora_before_template` | action | `mixed($template_name) $template_name, mixed($template_path) $templ…` | `class-template-helpers.php:87` | — |

## Miscellaneous (50)

| Hook | Type | Args | Fired at | Consumed by |
|---|---|---|---|---|
| `wb_listora_analytics_retention_days` | filter | `int` | `workflow/class-expiration-cron.php:232` | — |
| `wb_listora_app_config` | filter | `array|mixed $data` | `rest/class-settings-controller.php:327` | — |
| `wb_listora_appointment_button` | action | `int $post_id, mixed($detail_type_slug) $detail_type_slug` | `templates/blocks/listing-detail/sidebar.php:79` | — |
| `wb_listora_claim_approved` | action | `int $claim_id, int $listing_id, mixed($claimant) $claimant` | `rest/class-claims-controller.php:531` | `wb-listora-pro` |
| `wb_listora_claim_rejected` | action | `int $claim_id, int` | `rest/class-claims-controller.php:539` | — |
| `wb_listora_contact_form_per_listing_daily_cap` | filter | `int $cap, int $listing_id` | `class-contact-form.php:198` | — |
| `wb_listora_credits_purchase_url` | filter | `mixed($override) $override` | `wb-listora.php:198` | — |
| `wb_listora_dashboard_nav_items` | action | `int $user_id` | `templates/blocks/user-dashboard/nav.php:109` | `wb-listora-pro` |
| `wb_listora_dashboard_sections` | action | `int $user_id` | `blocks/user-dashboard/render.php:660` | `wb-listora-pro` |
| `wb_listora_dashboard_url` | filter | `mixed($default) $default` | `class-template-helpers.php:236` | — |
| `wb_listora_detail_actions` | action | `int $post_id` | `blocks/listing-detail/render.php:340` | `wb-listora-pro` |
| `wb_listora_detail_reviews_limit` | filter | `int, int $post_id` | `blocks/listing-detail/render.php:424` | — |
| `wb_listora_detail_tabs_view_data` | filter | `mixed($tabs_view_data) $tabs_view_data, int $post_id` | `blocks/listing-detail/render.php:479` | — |
| `wb_listora_directory_url` | filter | `mixed($default) $default` | `class-template-helpers.php:188` | — |
| `wb_listora_draft_reminder` | action | `int $post_id` | `workflow/class-expiration-cron.php:210` | — |
| `wb_listora_favorite_added` | action | `int $listing_id, int $user_id` | `rest/class-favorites-controller.php:249` | `wb-listora-pro` |
| `wb_listora_favorite_removed` | action | `int $listing_id, int $user_id` | `rest/class-favorites-controller.php:307` | — |
| `wb_listora_field_sanitize_callbacks` | filter | `mixed($callbacks) $callbacks` | `core/class-field.php:247` | — |
| `wb_listora_field_types` | filter | `mixed($types) $types` | `core/class-field-registry.php:208` | — |
| `wb_listora_fullwidth_blocks` | filter | `mixed, WP_Post|int $post` | `core/class-theme-defenses.php:77` | — |
| `wb_listora_listing_expiring` | action | `int, mixed($days) $days` | `admin/class-listing-columns.php:524` | — |
| `wb_listora_listing_indexed` | action | `int $post_id` | `search/class-search-indexer.php:224` | — |
| `wb_listora_listing_limit_counted_statuses` | filter | `array` | `core/class-listing-limits.php:430` | — |
| `wb_listora_listing_limit_overflow` | action | `int $user_id, mixed($overflow_cost) $overflow_cost` | `core/class-listing-limits.php:255` | — |
| `wb_listora_listing_pending_admin` | action | `int $post_id` | `admin/class-listing-columns.php:472` | — |
| `wb_listora_listing_title_badges` | action | `int $post_id, mixed($type ? $type->get_slug() : '') $type ? $type-…` | `blocks/listing-detail/render.php:275` | `wb-listora-pro` |
| `wb_listora_listing_trashed` | action | `int $post_id, mixed` | `rest/class-listings-controller.php:868` | — |
| `wb_listora_listing_updated` | action | `int $post_id, mixed, WP_REST_Request $request` | `rest/class-submission-controller.php:668` | — |
| `wb_listora_login_modal_register_url` | filter | `string $url, int $listing_id, string $current_permalink` | `blocks/listing-detail/render.php:801` | — |
| `wb_listora_placeholder_url` | filter | `mixed($url) $url` | `class-template-helpers.php:129` | — |
| `wb_listora_pro_cta_should_show` | filter | `bool, mixed($surface) $surface` | `admin/class-pro-promotion.php:139` | — |
| `wb_listora_register_field_types` | action | `mixed($this) $this` | `core/class-field-registry.php:54` | — |
| `wb_listora_register_listing_types` | action | `mixed($this) $this` | `core/class-listing-type-registry.php:78` | — |
| `wb_listora_render_contact_form` | filter | `bool $should_render` | `class-contact-form.php:56` | — |
| `wb_listora_renewal_cost` | filter | `mixed($cost) $cost, int $post_id, int $plan_id` | `rest/class-listings-controller.php:1335` | — |
| `wb_listora_renewal_duration_days` | filter | `mixed($duration_days) $duration_days, int $post_id, int $plan_id` | `rest/class-listings-controller.php:1338` | — |
| `wb_listora_reset_option_keys` | filter | `array $option_keys` | `rest/class-settings-controller.php:360` | `wb-listora-pro` |
| `wb_listora_rest_listing_response` | filter | `mixed($listing) $listing, WP_Post|int $post` | `rest/class-search-controller.php:461` | `wb-listora-pro` |
| `wb_listora_schema_data` | filter | `array|mixed $data, int $this->post_id` | `schema/class-schema-generator.php:166` | — |
| `wb_listora_show_dashboard_pro_cta` | filter | `bool, int $user_id` | `blocks/user-dashboard/render.php:666` | — |
| `wb_listora_submission_login_buttons` | action | _(none)_ | `templates/blocks/listing-submission/submission.php:67` | — |
| `wb_listora_submission_plan_step` | action | `mixed($listing_type) $listing_type` | `templates/blocks/listing-submission/submission.php:111` | `wb-listora-pro` |
| `wb_listora_submission_steps` | filter | `mixed($steps) $steps, mixed($listing_type) $listing_type, mixed($i…` | `blocks/listing-submission/render.php:165` | `wb-listora-pro` |
| `wb_listora_submit_url` | filter | `mixed($default) $default` | `class-template-helpers.php:211` | — |
| `wb_listora_trust_proxy_headers` | filter | `bool` | `class-rate-limiter.php:227` | — |
| `wb_listora_unverified_listing_cleaned` | action | `int $post_id, mixed($action) $action` | `workflow/class-email-verification.php:544` | — |
| `wb_listora_upgrade_url` | filter | `mixed($default) $default` | `class-template-helpers.php:258` | — |
| `wb_listora_user_credit_balance` | filter | `mixed($balance) $balance, int $user_id` | `core/class-listing-limits.php:554` | `wb-listora.php:418` |
| `wb_listora_user_listing_limit` | filter | `mixed($best) $best, int $user_id` | `core/class-listing-limits.php:382` | — |
| `wb_listora_webhook_secret` | filter | `string $default, array $context` | `admin/class-settings-page.php:998` | `wb-listora-pro` |

---

## How to subscribe

```php
// Action: fires after a listing is created via REST submission.
add_action( 'wb_listora_after_create_listing', function ( $post_id, $request ) {
    // Sync to your CRM, fire a webhook, etc.
    error_log( "Listing $post_id created" );
}, 10, 2 );

// Filter: modify the response shape (Pro uses this to inject feature fields).
add_filter( 'wb_listora_rest_prepare_listing', function ( $data, $post, $request ) {
    $data['custom_field'] = get_post_meta( $post->ID, '_custom_field', true );
    return $data;
}, 10, 3 );

// Filter that aborts a write: return WP_Error to block.
add_filter( 'wb_listora_before_create_review', function ( $value, $listing_id, $args ) {
    if ( /* your gate */ ) return new WP_Error( 'gate_blocked', 'Reviews paused.' );
    return $value;
}, 10, 3 );
```

## Reading the table

- **Hook** — the action/filter name to `add_action` / `add_filter` against.
- **Type** — `action` (no return) or `filter` (must return a value, possibly `WP_Error` for before-filters).
- **Args** — the parameter signature passed to your callback.
- **Fired at** — the file:line where `do_action()` / `apply_filters()` calls — useful for reading the surrounding code for context.
- **Consumed by** — every plugin/site that currently listens. `—` means the hook is documented but no internal consumer exists yet — first-extender territory.

## Related

- [REST API](rest-api.md) — every endpoint that fires these hooks.
- [Extending with WB Listora Pro](extending-with-pro.md) — how Pro consumes these surfaces.
- [Template Overrides](template-overrides.md) — extend without forking templates.
- [Custom Fields & Field Types](custom-fields.md) — define your own field types using the registry.