# Automation Triggers

> **Availability:** Free + Pro. The trigger registry and all 25 core triggers are **Free**. Delivering them to an external URL needs [Outgoing Webhooks](outgoing-webhooks.md), which is **Pro** and adds 9 triggers of its own.

Every meaningful thing that happens in your directory - a listing approved, a claim rejected, a member suspended, a coupon redeemed - is published as a named **trigger** with a documented payload shape. Automation platforms and your own code can discover what exists instead of guessing, and the shape of what they receive will not change underneath them.

## What it is

Before this, the list of events you could subscribe to lived inside the webhook feature as a hand-maintained list. Two problems followed from that: events were being dispatched that nobody could subscribe to, and the payload each one sent was assembled separately from the payload the REST API returned for the same thing, so a listing arrived in two different shapes depending on how you asked for it.

The registry fixes both. It is one catalogue, built when the plugin loads, that answers three questions:

- **What can this directory announce?** 25 triggers in Free, 34 with Pro active.
- **What exactly will I receive?** Every trigger points at a versioned JSON Schema file shipped inside the plugin - `listing_approved.v1.json`, `claim_rejected.v1.json`, and so on.
- **Will it change?** No. A published schema is immutable. If a payload ever needs a different shape it ships as `.v2` alongside the original, and existing subscribers keep receiving `.v1`.

Three automated checks enforce this on every build: every event dispatched must be a registered trigger, every registered trigger must have a schema file, and no published schema file may be modified in place.

## The catalogue

**Free - 25 triggers**

| Trigger | Fires when |
|---|---|
| `listing_submitted` | A listing is submitted |
| `listing_approved` | A listing moves into published from pending or rejected |
| `listing_rejected` | A listing is rejected |
| `listing_deactivated` | A listing is deactivated |
| `listing_reactivated` | A deactivated listing is published again |
| `listing_pending_review` | A listing moves into pending |
| `listing_expired` | A listing passes its expiry date |
| `listing_renewed` | A listing's expiry is extended |
| `listing_claimed` | A listing is claimed |
| `listing_reported` | A listing is reported |
| `review_submitted` | A review is submitted |
| `review_reply_posted` | An owner replies to a review |
| `claim_submitted` | A claim is submitted |
| `claim_approved` | A claim is approved |
| `claim_rejected` | A claim is rejected |
| `member_suspended` | A member is suspended |
| `member_unsuspended` | A suspension is lifted |
| `account_deactivated` | A member deactivates their account |
| `account_reactivated` | A member reactivates their account |
| `account_deleted` | A member account is deleted |
| `favorite_added` | A listing is favourited |
| `favorite_removed` | A favourite is removed |
| `service_created` | A service is added to a listing |
| `service_updated` | A service is edited |
| `service_deleted` | A service is removed |

**Pro - 9 more**

| Trigger | Fires when |
|---|---|
| `payment_received` | A payment completes |
| `credits_added` | Credits are added to a member's balance |
| `coupon_redeemed` | A coupon is redeemed |
| `plan_resumed` | A paused listing resumes under its plan |
| `need_posted` | A buyer posts a need ([Needs Marketplace](needs-marketplace.md)) |
| `listing_created` | A listing is created through the API |
| `listing_updated` | A listing is updated through the API |
| `review_posted` | A review is posted |
| `review_approved` | A review is approved |

## How you use it

### As a site owner - subscribe to an event

Automation triggers are the catalogue; [Outgoing Webhooks](outgoing-webhooks.md) is the delivery mechanism, and it reads this catalogue directly.

1. Go to **Listora > Webhooks** and add an endpoint.
2. Tick the events you want. **The checkbox list is built from the registry**, so every event offered is one that can actually be delivered - the list can no longer drift from what the plugin really fires.
3. Save. Deliveries are signed, queued and retried as described on the [Outgoing Webhooks](outgoing-webhooks.md) page.

If an event you expect is missing from the list, it is because nothing dispatches it on your configuration - a Pro trigger with Pro inactive, for instance. That is the list telling you the truth rather than offering a dead option.

### As a developer - what a delivery looks like

Every delivery carries the same envelope:

```json
{
  "event": "listing_approved",
  "timestamp": "2026-08-19T11:40:00+00:00",
  "site_url": "https://example.com",
  "version": "1",
  "id": "9f8c1c2e-4a3b-4d5e-9f10-2b3c4d5e6f70",
  "data": { }
}
```

- `event` - the trigger name from the catalogue above.
- `version` - the schema version of `data`. Pin your parser to this.
- `id` - a unique id for this delivery to this subscriber. Use it to make your receiver idempotent; a retry reuses the same `id`.
- `data` - the payload, matching the schema file for this trigger and version.

`data` is built from the **same serializers the REST API uses**, so a listing inside a webhook is the shape `GET /listora/v1/listings/{id}` returns. One shape to learn, not two.

### As a developer - register your own trigger

Add-ons declare triggers on the `wb_listora_register_triggers` action, which fires once at load:

```php
add_action( 'wb_listora_register_triggers', function ( $registry ) {
	$registry->register( array(
		'name'        => 'my_plugin_thing_happened',
		'hook'        => 'my_plugin_thing_happened',
		'label'       => __( 'Thing Happened', 'my-plugin' ),
		'description' => __( 'A thing happened.', 'my-plugin' ),
		'schema'      => 'my_plugin_thing_happened.v1.json',
	) );
} );
```

Read the catalogue with `wb_listora_service( 'triggers' )`, which exposes `all()`, `get( $name )`, `has( $name )` and `names()`.

## Settings & options

There is nothing to configure. The registry is always on and costs nothing when no webhook subscribes to anything - triggers are declarations, and a trigger with no subscriber does no work.

## Good to know

- **There is no discovery endpoint yet.** The catalogue is readable in PHP and the schema files ship in the plugin at `includes/automation/schemas/`, but 1.6.0 does not publish a REST route that lists triggers. That is planned, not shipped.
- **Some triggers share a WordPress hook.** The five `listing_*` state changes all hang off one status-change hook, and the two claim outcomes share another. Each declares the condition that distinguishes it. As of 1.6.0 that condition is documentation - Pro's delivery handlers check the status themselves - so treat it as describing intent rather than as a filter you can rely on.
- **`coupon_redeemed` has been dispatched since it shipped**, but before 1.6.0 it was not in the subscribable list, so every dispatch was discarded. It is subscribable now.

## Related

- [Outgoing Webhooks](outgoing-webhooks.md) - delivering these triggers to an external URL
- [Payment Webhooks](payment-webhooks.md) - the inbound direction, for gateways
- [REST API](../developer-guide/rest-api.md) - the entity shapes `data` uses
- [Hooks Reference](../developer-guide/hooks-reference.md) - the WordPress hooks behind each trigger
