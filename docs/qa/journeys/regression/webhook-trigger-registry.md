---
journey: webhook-trigger-registry
plugin: wb-listora
priority: high
roles: [administrator, anonymous, member]
covers: [trigger-registry, outgoing-webhooks-subscriber-ui, coupon_redeemed, need_posted, wb_listora_register_triggers, automation-payload-listing]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free + Pro both active (subscriber UI and delivery are Pro; the registry and payload builders under test are Free)"
  - "wp-admin as administrator (?autologin=1), WP-CLI against the same install"
  - "The receiver fixture `docs/qa/fixtures/webhook-receiver.php`, started from the Free plugin root with `php -S 127.0.0.1:8955 docs/qa/fixtures/webhook-receiver.php`"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
changed: true
---

# The subscriber UI offers exactly what the registry can deliver, and a listing payload is the API's own card shape

Pro's Outgoing_Webhooks add/edit page builds its checkbox list from the trigger registry, never a second hand-kept list: `get_subscribable_triggers()` (`wb-listora-pro/includes/features/class-outgoing-webhooks.php:898`) takes `wb_listora_service( 'triggers' )->all()`, keeps names that are also in `get_deliverable_event_names()` (`:346`, backed by `get_event_source_map()` `:194`) and that the current user's capability allows; the page loops it at `:2618` and renders `input[name="wh_events[]"]` (`:2682`). Admin URL: `wp-admin/admin.php?page=listora-webhooks&view=add`.

It also guards the promise `Payload::listing()` (`includes/automation/class-payload.php:31`) was built on: a listing in a webhook is the same card object `wb_listora_get_listing_cards()` returns for `/search`, `/favorites` and `/listings/{id}/related`, not a third shape.

## Setup
```bash
SITE=http://wb-listora.local
cd "wp-content/plugins/wb-listora"            # Free root: the receiver fixture lives here
php -S 127.0.0.1:8955 docs/qa/fixtures/webhook-receiver.php & RECEIVER_PID=$!
sleep 1; curl -s "http://127.0.0.1:8955/?_control=reset&fail_first=0"
```
**Trap: pre-seeded demo webhooks.** `get_active_webhooks_for_event()` matches every `publish` subscriber, so firing a shared event sends real outbound HTTP to third-party URLs and pads the delivery count. On 2026-10-12 this site held three `listora_webhook` posts: Zapier (6395, draft), Webhook.site (6396, draft) and **Slack - Payment Channel (6397, publish, https://hooks.slack.com/...)**. Pause every published one for the run and restore it:
```bash
wp eval 'foreach ( get_posts( array( "post_type" => "listora_webhook", "post_status" => "publish", "numberposts" => -1 ) ) as $p ) { echo $p->ID . "\n"; wp_update_post( array( "ID" => $p->ID, "post_status" => "draft" ) ); }' > "$SCRATCH/paused-webhooks.txt"
```

## Steps

### 1. Every event offered in the UI comes from the registry
- **Action**: open `$SITE/wp-admin/admin.php?page=listora-webhooks&view=add&autologin=1`, read every `input[name="wh_events[]"]` `value`.
- **Expect**: the value set equals `array_keys( get_subscribable_triggers() )` (private: reach it by Reflection as in step 3). 2026-10-12: 13 names: `listing_approved, listing_rejected, listing_expired, claim_submitted, claim_approved, payment_received, credits_added, coupon_redeemed, need_posted, listing_created, listing_updated, review_posted, review_approved`.
- **On fail**: a checkbox whose value is not a registry name means a literal list crept back into the events fieldset (`:2618-2690`).

### 2. `coupon_redeemed` and `need_posted` are offered
- **Expect**: checkboxes with `value="coupon_redeemed"` (label "Coupon Redeemed") and `value="need_posted"` (label "Need Posted"). Registry check verified 2026-10-12 (both offered); the rendered page was not opened.
- **On fail**: `Pro_Trigger_Definitions::register_all()` no longer declares one, or `get_event_source_map()` dropped its entry (offered without a source map entry is the bug this guards).

### 3. Both orphans deliver end to end, proven by the independent receiver
A journey that only reads Pro's `webhook_log` trusts the thing under test to grade itself. Drive a real HTTP delivery to the fixture and read it back from the receiving side.
- **Action** (`wp eval-file`): truncate `{prefix}listora_webhook_log`; create a `listora_webhook` post (`_listora_wh_url = http://127.0.0.1:8955/deliver`, `_listora_wh_secret`, `_listora_wh_events` = JSON `["coupon_redeemed","need_posted"]`); `do_action( 'wb_listora_pro_after_redeem_coupon', 555001, array( 'user_id' => 1, 'plan_id' => 1, 'listing_id' => 0, 'discount' => 10.0 ) )` and `do_action( 'wb_listora_pro_after_create_need', 555002, array( 'post_data' => array(), 'meta' => array( 'category' => 'qa-journey' ) ), null )`; then `Pro_Plugin::instance()->features()->get( 'outgoing_webhooks' )->deliver( $log_id )` for each log row (`deliver()` is public, `:1005`).
- **Expect**: `webhook_log` has exactly 2 rows, both `status = delivered`, `response_code = 200`; the receiver (`curl "http://127.0.0.1:8955/?_control=log"`) shows 2 requests, one with `X-Listora-Event: coupon_redeemed` and one `need_posted`, each body `{event, timestamp, site_url, version, id, data}` with `data.coupon_id == 555001` / `data.id == 555002`; `hash_hmac( 'sha256', $body, $secret )` computed independently equals the `sha256=` value of `X-Listora-Signature` (`:1043`).
- **Registry cross-check** (verified 2026-10-12): `count( $triggers->all() )` = 34 (25 Free + 9 Pro); offered = 13; both orphans `yes`.
- **Positive control**: step 1 for a name that IS fully wired (`listing_approved`) also delivers.

### 4. The UI does not offer an event Pro cannot deliver
- **Action**: `wp eval 'echo in_array( "plan_resumed", wb_listora_service( "triggers" )->names(), true ) ? "registered" : "missing";'` then look for a "Plan Resumed" checkbox.
- **Expect**: `registered`, and NOT offered: absent from `get_subscribable_triggers()` (verified 2026-10-12) and from the rendered list, because `get_event_source_map()` has no dispatch entry for it. This is the opposite failure to step 2 (the intersection with `get_deliverable_event_names()` is what prevents it).

### 5. A payload's `listing` is the card shape, verified value for value
- **Action (identity)**: `\WBListora\Automation\Payload::listing( $id ) === wb_listora_get_listing_cards( array( $id ) )[ $id ]`.
- **Expect**: `IDENTICAL` (verified 2026-10-12). Keys today: `id, title, link, featured_image, rating, listing_type, listing_type_name, is_featured, location`; a change that post-processes the card (re-adds `excerpt`, etc.) breaks it.
- **Action (overlap)**: `GET /listora/v1/listings/{id}` vs the payload.
- **Expect** equal: `id`, `link`, `rating` (`{average,count}`), `listing_type`, `listing_type_name`, `is_featured`, and `title` equal to `detail.title.rendered` (verified, all `true`). `featured_image` and `location` exist only in the card shape (the detail route uses `featured_media` and `listora_meta.address`): documented, not a failure.

### 6. Role ladder
| Actor | Expect |
|---|---|
| Administrator | steps 1-5 (the subscriber UI needs `manage_options`-level capability per trigger `capability`) |
| Member / editor without the capability | the page is not reachable; REST webhook routes 403; a trigger whose `capability` the user lacks is not offered |
| Anonymous | `wp-admin/admin.php?page=listora-webhooks` redirects to login; the receiver never gets a request for a draft webhook (control for the pause step) |

### 7. Layout
At 390 the events fieldsets stack and wrap; checkboxes' labels are >= 40px tap targets. Not a matter of this journey's contract; check once.

## State restored
```bash
wp eval 'global $wpdb; $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}listora_webhook_log" ); $id = (int) trim( file_get_contents( "$SCRATCH/test-webhook-id.txt" ) ); if ( $id > 0 && "listora_webhook" === get_post_type( $id ) ) { wp_delete_post( $id, true ); }'
while read -r id; do [ -n "$id" ] && wp eval "wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );"; done < "$SCRATCH/paused-webhooks.txt"
kill $RECEIVER_PID
```
Delete ONLY the webhook created in step 3 by its captured ID (never by title: `post_title` is not a `get_posts` arg and would match every webhook). Verify that `get_posts( listora_webhook, any )` lists exactly the webhooks that existed before Setup. Do not truncate `webhook_log` on a site whose log matters.

## Fail diagnostics
| Symptom | Suspect |
|---|---|
| Checkbox value not in the registry | literal list in `class-outgoing-webhooks.php:2618-2690` |
| Offered but the receiver log stays empty | `get_event_source_map()` entry or its hook never fires: `wb_listora_pro_after_redeem_coupon` / `wb_listora_pro_after_create_need` (`:315`, `:323`) |
| `plan_resumed` appears as a checkbox | a dispatch entry added without a real firer |
| `Payload::listing()` diverges from the card | `includes/automation/class-payload.php:31` stopped being a passthrough |
| Detail fields disagree | the shared card function or detail endpoint changed a field name independently |

## Not verified
Registry totals (34 / 13), `plan_resumed` not offered, payload identity and field overlap were run on 2026-10-12 via wp-cli; the Slack webhook (6397) was left published and no events were fired. Steps 2 (rendered checkboxes), 3 (end-to-end delivery and HMAC, which would POST to the published Slack subscriber unless paused), 6 and 7 were not run.

Verified via: wp-cli.
