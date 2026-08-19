---
journey: webhook-trigger-registry
plugin: wb-listora
priority: high
roles: [administrator]
covers: [trigger-registry, outgoing-webhooks-subscriber-ui, coupon_redeemed, need_posted, wb_listora_register_triggers, automation-payload-listing]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free + Pro both active (the subscriber UI and delivery are Pro; the registry and payload builders being tested are Free)"
  - "wp-admin access as an administrator (autologin=1)"
  - "WP-CLI (`wp eval`) available against the same install"
  - "The receiver fixture: docs/qa/fixtures/webhook-receiver.php, started with `php -S 127.0.0.1:8955 docs/qa/fixtures/webhook-receiver.php` from this plugin's root"
estimated_runtime_minutes: 12
---

# The subscriber UI offers exactly what the registry can deliver, and a listing payload is the API's own shape

Before this wave, Pro's Outgoing_Webhooks admin page built its checkbox list from a hand-maintained
`EVENTS` class constant that had drifted from `dispatch_event()`'s real call sites. `coupon_redeemed`
and `need_posted` were dispatched by real handlers that built a payload and threw it away, because
neither name was in `EVENTS` — the checkbox to subscribe to them never existed. This journey guards
the two properties that made that bug possible: the UI's offered list must be *derived from* the
trigger registry (never a second hand-maintained list), and it must never offer a name the delivery
layer cannot actually service.

It also checks the promise `Trigger_Definitions`/`Payload` were built on — a listing in a webhook is
the same object a REST client already gets, not a third shape only automations see.

## Setup

```bash
SITE=http://wb-listora.local
cd "wp-content/plugins/wb-listora"   # Free's root — the receiver fixture lives here

# Start the receiver fixture (kill it in "State restored" below).
php -S 127.0.0.1:8955 docs/qa/fixtures/webhook-receiver.php &
RECEIVER_PID=$!
sleep 1
curl -s "http://127.0.0.1:8955/?_control=reset&fail_first=0"
```

**Trap — pre-seeded demo webhooks.** `demo/pro-pack.php` ships 3 sample webhook subscribers
(Zapier / webhook.site / Slack catch-all URLs) already `publish`-status on any site that ran the
Pro setup wizard demo import. `get_active_webhooks_for_event()` matches on **every** published
subscriber, so firing a shared event (`listing_approved`, `payment_received`, `credits_added`) while
they're live sends real outbound HTTP requests to those third-party URLs on top of the one this
journey means to test, and pads the delivery count with unrelated 404/timeout rows. Pause them for
the duration:

```bash
wp eval '
foreach ( get_posts( array( "post_type" => "listora_webhook", "post_status" => "publish", "posts_per_page" => -1 ) ) as $p ) {
  echo $p->ID . "\n";
  wp_update_post( array( "ID" => $p->ID, "post_status" => "draft" ) );
}' > /tmp/wbl-paused-webhooks.txt
cat /tmp/wbl-paused-webhooks.txt
```

## Steps

### 1. Every event offered in the subscriber UI comes from the registry

- **Action**: `$SITE/wp-admin/admin.php?page=listora-webhooks&view=add&autologin=1`, read every
  `input[name^="wh_events"]` checkbox's `value`.
- **Expect**: the rendered `value` set is **identical** to the keys of
  `WBListoraPro\Features\Outgoing_Webhooks::get_subscribable_triggers()` (private — reach it via
  `wp eval` + Reflection, shown in Step 3). There is no second, hand-authored list anywhere in the
  render path — `render_add_edit_page()` loops `get_subscribable_triggers()` directly (verified at
  `class-outgoing-webhooks.php:2581`).
- **On fail**: a checkbox whose `value` is not a registry name means someone reintroduced a literal
  list in the admin template. Suspect the `render_add_edit_page()` events fieldset.

### 2. `coupon_redeemed` and `need_posted` are offered

- **Action**: on the same page, find checkboxes labelled **Coupon Redeemed** and **Need Posted**.
- **Expect**: both present. Browser-verified 2026-08-16: 13 checkboxes render, including
  `value="coupon_redeemed"` (label "Coupon Redeemed") and `value="need_posted"` (label "Need Posted").
- **On fail**: `Pro_Trigger_Definitions::register_all()` no longer declares one of the two orphans, or
  `Outgoing_Webhooks::get_event_source_map()` dropped its dispatch entry (see Step 3 — offered without
  a source map entry is worse than not offered, it is the exact bug this task closes).

### 3. Both orphans deliver end-to-end — proven by an independent receiver, not Pro's own log

A journey that only reads Pro's `webhook_log` table to confirm "it fired" is trusting the thing under
test to grade itself. This step drives a real HTTP delivery to `docs/qa/fixtures/webhook-receiver.php`
and reads the delivery back from the RECEIVING side.

- **Action**:
  ```bash
  wp eval '
  global $wpdb;
  $table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . "webhook_log";
  $wpdb->query( "TRUNCATE TABLE $table" );

  $secret = "wbl_qa_secret_" . wp_generate_password( 8, false );
  $webhook_id = wp_insert_post( array(
    "post_type"   => "listora_webhook",
    "post_title"  => "QA receiver (temporary)",
    "post_status" => "publish",
  ) );
  update_post_meta( $webhook_id, "_listora_wh_url", "http://127.0.0.1:8955/deliver" );
  update_post_meta( $webhook_id, "_listora_wh_secret", $secret );
  update_post_meta( $webhook_id, "_listora_wh_events", wp_json_encode( array( "coupon_redeemed", "need_posted" ) ) );
  file_put_contents( "/tmp/wbl-test-webhook-id.txt", $webhook_id );
  echo "webhook_id=$webhook_id secret=$secret\n";

  do_action( "wb_listora_pro_after_redeem_coupon", 555001, array( "user_id" => 1, "plan_id" => 1, "listing_id" => 0, "discount" => 10.0 ) );
  do_action( "wb_listora_pro_after_create_need", 555002, array( "post_data" => array(), "meta" => array( "category" => "qa-journey" ) ), null );

  $ow = \WBListoraPro\Pro_Plugin::instance()->features()->get( "outgoing_webhooks" );
  foreach ( $wpdb->get_col( "SELECT id FROM $table ORDER BY id" ) as $log_id ) {
    $ow->deliver( (int) $log_id ); // deliver() is public — no reflection needed.
  }
  echo $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = \"delivered\"" ) . " delivered rows\n";
  '
  curl -s "http://127.0.0.1:8955/?_control=log" | python3 -m json.tool
  ```
- **Expect**:
  - `webhook_log` shows exactly 2 rows, both `status = delivered`, `response_code = 200`.
  - The receiver's log (independent of Pro's own table) shows **2 requests received**, one with
    `X-Listora-Event: coupon_redeemed` and one with `X-Listora-Event: need_posted`, each body
    decoding to `{ event, timestamp, site_url, version, id, data }` with `data.coupon_id == 555001` /
    `data.id == 555002` respectively.
  - `hash_hmac( 'sha256', $body, $secret )` (compute independently, e.g. in Python/PHP) equals the
    `sha256=` value in each `X-Listora-Signature` header — proves the receiver, not Pro's own
    reporting, is the one confirming the signature is real and verifiable.
- **On fail**: 0 rows in the receiver log with 2 rows `delivered` in `webhook_log` means the HTTP
  request left Pro but never reached the fixture — check `WBL_RECEIVER_LOG`/`WBL_RECEIVER_STATE` env
  vars and that nothing else is bound to port 8955. A row stuck `pending`/`retrying` past this step
  means `deliver()` was never invoked for it — check the `foreach` over `get_col()` above ran before
  reading the receiver log.
- **Independent registry cross-check**:
  ```bash
  wp eval '
  $triggers = wb_listora_service( "triggers" );
  echo "registered total: " . count( $triggers->all() ) . "\n";  // expect 34 (25 Free + 9 Pro)
  $ow = \WBListoraPro\Pro_Plugin::instance()->features()->get( "outgoing_webhooks" );
  $m = new ReflectionMethod( $ow, "get_subscribable_triggers" );
  $m->setAccessible( true );
  wp_set_current_user( 1 );
  $offered = array_keys( $m->invoke( $ow ) );
  echo "offered: " . count( $offered ) . "\n";                    // expect 13
  echo "coupon_redeemed offered: " . ( in_array( "coupon_redeemed", $offered, true ) ? "yes" : "no" ) . "\n";
  echo "need_posted offered: " . ( in_array( "need_posted", $offered, true ) ? "yes" : "no" ) . "\n";
  '
  ```
  **Expect**: `registered total: 34`, `offered: 13`, both orphans `yes`.

### 4. The UI does not offer any event Pro cannot deliver

- **Action**: `wp eval '$t = wb_listora_service("triggers"); echo in_array("plan_resumed", $t->names(), true) ? "registered\n" : "missing\n";'` then re-check the checkbox list from Step 1 for a "Plan Resumed" entry.
- **Expect**: `plan_resumed` **is registered** (Ruling 1 of this plan declared it into the catalogue —
  it is a real, subscribable-in-principle fact) but **is not offered** as a checkbox, because
  `Outgoing_Webhooks::get_event_source_map()` has no dispatch entry for it yet. Confirmed live
  2026-08-16: `plan_resumed` present in `$triggers->names()`, absent from
  `get_subscribable_triggers()` and absent from the rendered checkbox list.
- **Why this matters**: this is the *opposite* failure from Step 2 (an event offered that cannot
  deliver) — a registry entry existing does not, by itself, put a checkbox on the page.
  `get_subscribable_triggers()`'s intersection with `get_deliverable_event_names()` is what prevents
  it, and this step is the regression guard for that intersection specifically.
- **On fail**: someone added `plan_resumed` (or any other registry-only trigger) to
  `get_deliverable_event_names()`/`get_event_source_map()` without also wiring a real
  `add_action()`/`dispatch_event()` call — the exact "looks like it works" failure the whole trigger
  registry exists to prevent.

### 5. A payload's `listing` matches the REST API's own shape — verified value-for-value, not assumed

`Payload::listing()` (`includes/automation/class-payload.php`) deliberately delegates to
`wb_listora_get_listing_cards()` — **the same function** `GET /favorites`, `GET /listings/{id}/related`
and `/search` use for their card entries — not to `GET /listings/{id}`'s own detail builder, which is
a materially richer, differently-shaped response (verified live below). "Field-for-field" is therefore
checked two ways: an exact-shape check against a genuine card endpoint, and a named-field overlap
check against the detail endpoint for the fields Pro's delivered payload explicitly reconciles with it.

- **Action (card-shape identity — the real architectural guarantee)**:
  ```bash
  wp eval '
  $id = (int) get_posts( array( "post_type" => "listora_listing", "posts_per_page" => 1, "fields" => "ids", "post_status" => "publish" ) )[0];
  $payload = \WBListora\Automation\Payload::listing( $id );
  $card    = wb_listora_get_listing_cards( array( $id ) )[ $id ];
  echo ( $payload === $card ) ? "IDENTICAL\n" : "DIVERGED:\n" . json_encode( $payload ) . "\nvs\n" . json_encode( $card ) . "\n";
  '
  ```
- **Expect**: `IDENTICAL`. This is true by construction (`Payload::listing()` is a direct passthrough —
  see its docblock) but is asserted here as a regression guard: if a future change makes `Payload::listing()`
  post-process the card (e.g. re-adding `excerpt`, per Task 5's I1 finding), this step is what catches it.
- **Action (overlap with `GET /listings/{id}`, the endpoint named in this task's brief)**:
  ```bash
  curl -s "$SITE/?rest_route=/listora/v1/listings/$id" | python3 -m json.tool > /tmp/wbl-detail.json
  wp eval '
  $id = (int) get_posts( array( "post_type" => "listora_listing", "posts_per_page" => 1, "fields" => "ids", "post_status" => "publish" ) )[0];
  echo json_encode( \WBListora\Automation\Payload::listing( $id ) );
  ' > /tmp/wbl-payload.json
  python3 -c "
  import json
  detail = json.load(open('/tmp/wbl-detail.json'))
  payload = json.load(open('/tmp/wbl-payload.json'))
  print('id match:', payload['id'] == detail['id'])
  print('link match:', payload['link'] == detail['link'])
  print('rating match:', payload['rating'] == detail['rating'])
  print('listing_type match:', payload['listing_type'] == detail['listing_type'])
  print('listing_type_name match:', payload['listing_type_name'] == detail['listing_type_name'])
  print('is_featured match:', payload['is_featured'] == detail['is_featured'])
  print('title value match (unwrap detail rendered):', payload['title'] == detail['title']['rendered'])
  "
  ```
- **Expect**: every printed line `True`. Verified live 2026-08-16 on a seeded listing (id 774,
  "Union Square Greenmarket"): `id`, `link`, `rating` ({average, count}), `listing_type`,
  `listing_type_name`, `is_featured` all matched key **and** value; `title`'s underlying string
  matched once `detail.title.rendered` is unwrapped from the detail endpoint's WP-REST-standard
  `{ rendered }` envelope (`Payload::listing()` returns a bare string).
- **Documented, NOT a failure**: `Payload::listing()` additionally carries `featured_image` (full/
  medium/medium_large/thumbnail/alt) and `location` — `GET /listings/{id}` has **no equivalent
  top-level fields at all** for either (its own featured-image representation is `featured_media`, an
  attachment ID, controlled by the `image_sizes` request param; location lives in `listora_meta.address`
  and the `listing_locations` taxonomy array instead of a flat string). This is not something to force
  into agreement — `Payload::listing()` is intentionally the compact CARD shape shared with `/search`,
  `/favorites` and `/listings/{id}/related`, not a repackaging of the detail endpoint's own field set.
  A future change that tries to make these fields identical would be solving a problem that does not
  exist and coupling two endpoints that serve different consumers by design.
- **On fail (any `match: False` line, or the identity check diverging)**: the shared card function
  changed shape, or `Payload::listing()` stopped delegating straight to it. Either breaks the "one
  shape, one function" guarantee `class-payload.php`'s own docblock states as its reason for existing.

## Pass criteria

ALL must hold:
- Every `wh_events` checkbox value on the Add Webhook page is a name returned by
  `Outgoing_Webhooks::get_subscribable_triggers()` — no second list.
- `coupon_redeemed` and `need_posted` both render as checkboxes AND both deliver a real HTTP request
  (independently observed by `docs/qa/fixtures/webhook-receiver.php`) with a valid HMAC signature.
- `plan_resumed` is registered but NOT offered (registry membership alone does not put a checkbox on
  the page).
- Registry totals: 34 registered, 13 offered/deliverable.
- `Payload::listing()` is byte-identical to `wb_listora_get_listing_cards()`'s own entry for the same
  listing (the real architectural guarantee), and its `id`/`link`/`rating`/`listing_type`/
  `listing_type_name`/`is_featured`/`title` fields agree value-for-value with `GET /listings/{id}`
  wherever both endpoints represent the same concept.
- No new lines in `wp-content/debug.log` from any step.

## Fail diagnostics

| Symptom | Suspect |
|---|---|
| Checkbox `value` not in the registry | A literal list reintroduced in `render_add_edit_page()` |
| `coupon_redeemed`/`need_posted` offered but receiver log stays empty | `get_event_source_map()` entry missing or its hook never fires — check `wb_listora_pro_after_redeem_coupon`/`wb_listora_pro_after_create_need` fire sites |
| `plan_resumed` appears as a checkbox | A dispatch entry was added to `get_event_source_map()` without also wiring a real firer — the opposite-direction regression this journey guards |
| `Payload::listing()` diverges from `wb_listora_get_listing_cards()` | `class-payload.php`'s `listing()` stopped being a pure passthrough |
| `id`/`link`/`rating`/`listing_type`/`title` mismatch against `GET /listings/{id}` | The shared card function or the detail endpoint changed a field name/shape independently |

## State restored

```bash
# Delete ONLY the exact webhook post created in Step 3, by the ID captured
# there — never by post_title (it is not a recognized WP_Query arg; passing
# it silently matches EVERY listora_webhook post regardless of title, which
# would delete the demo-seeded subscribers this Setup paused, not just the
# temporary one this journey created).
wp eval '
global $wpdb;
$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . "webhook_log";
$wpdb->query( "TRUNCATE TABLE $table" );
$id = (int) trim( file_get_contents( "/tmp/wbl-test-webhook-id.txt" ) );
if ( $id > 0 && "listora_webhook" === get_post_type( $id ) ) {
  wp_delete_post( $id, true );
  echo "deleted webhook #$id\n";
}
'
# Un-pause EXACTLY the demo webhooks captured in Setup (by ID, one per line).
while read -r id; do
  [ -n "$id" ] && wp eval "wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );"
done < /tmp/wbl-paused-webhooks.txt
rm -f /tmp/wbl-paused-webhooks.txt /tmp/wbl-test-webhook-id.txt /tmp/wbl-detail.json /tmp/wbl-payload.json
kill $RECEIVER_PID 2>/dev/null
```

**Verify before moving on**: `wp eval 'foreach (get_posts(array("post_type"=>"listora_webhook","post_status"=>"any","posts_per_page"=>-1)) as $p) echo "#{$p->ID} {$p->post_status} {$p->post_title}\n";'` must list exactly the webhooks that existed before Setup ran (no fewer, no extra) — confirm this BEFORE trusting the journey is done, since a partial cleanup failure here silently leaves either a stray test subscriber or a paused real one.
