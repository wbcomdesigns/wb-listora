# Rate Limiting & Abuse Controls

> **Availability:** Free + Pro.

Every public write in Listora has a limit on how often one member or one network address can repeat it. The limits stop one bad actor from flooding your directory with listings, reviews, claims or contact messages. The defaults work without any setup.

## What it is

Spam checks look at what is sent; rate limits look at how often. They back each other up: even if a message passes every spam check, one address cannot send it hundreds of times.

How the limits work:

- **Two counters.** A signed-in member is counted by account. A visitor who is not signed in is counted by network address. A signed-in member is not also counted against the address, so one shared office or school network does not lock out every member on it.
- **Fixed windows.** The window starts at the first request. Once the limit is reached, further requests are refused until the window ends, then the count starts again.
- **Stored in the database.** Counts are kept in WordPress transients and expire on their own. They use no extra tables.
- **Refusal.** A limited request gets HTTP status `429` and the error `listora_rate_limit`. The response carries a `Retry-After` header with the seconds to wait. Members see "Too many requests. Please wait a moment before trying again."; visitors see "Too many requests from this network. Please try again later."

### Default limits

Per hour unless stated.

| Action | Per member | Per network address (visitors) |
|---|---|---|
| Add a listing | 30 | 10 |
| Write a review | 30 | 15 |
| Reply to a review | 200 | 50 |
| Vote on a review | 300 | 150 |
| Add a review photo | 30 | 30 |
| Report a review | 50 | 20 |
| Report a listing | 50 | 20 |
| Submit a claim (per day) | 20 | 10 |
| Save a favourite | 1,000 | 500 |
| Bulk listing actions (per minute) | 120 | 30 |
| Search (per minute) | 300 | 60 |
| Search suggestions (per minute) | 150 | 30 |
| Browse listings and search without signing in (per minute) | - | 120 |
| Sign in with an application password | - | 20 |
| Resend or confirm an email verification, unsubscribe | 100 | 30 |
| Deactivate your account | 10 | 20 |
| Delete your account | 5 | 10 |

The listing, review, claim, favourite and search limits apply to the REST API, so they also cover the mobile app and any integration that calls it.

### The contact form

The contact-the-owner form has its own limits:

- **Per sender, per listing:** 3 messages per hour. A signed-in member is counted by account, a visitor by network address.
- **Per listing:** 20 messages per day in total, so one popular listing cannot be buried in messages.

Past a limit the sender sees "Too many messages. Try again in an hour." or "This listing has received too many messages today. Try again tomorrow." Developers can change the daily cap with the `wb_listora_contact_form_per_listing_daily_cap` filter.

## Public read endpoints (Pro)

WB Listora Pro also limits its public read-only REST endpoints, so a crawler looping over them cannot drive heavy database load. These cover credit packs, pricing plans, the needs feed, comparisons, service search and badges.

- **Default:** 60 requests per network address per minute, counted separately for each endpoint group.
- **Fail-open:** if no address can be found or the counter is unavailable, the request is allowed.
- **Signed-in members are limited too.** Pro does not exempt them.
- **Refusal:** status `429`, error `listora_pro_rate_limit`, with a `Retry-After` header.

Developer filters:

- `wb_listora_pro_public_rest_rate_limit` - change the per-minute limit for an endpoint group. Return `0` or a negative number to switch the limit off for that group.
- `wb_listora_pro_public_rest_rate_limit_bypass` - return `true` to skip the check, for trusted internal calls.

## How you use it

### As a site owner

The defaults need no action. There is no settings screen for rate limits; changes are made by a developer with the filters below.

If your site is behind Cloudflare, Fastly or another CDN or load balancer, ask your developer to tell Listora to trust the forwarded address, as shown in the developer section. Without it, every visitor appears to come from the CDN's address and visitors share one counter.

### As a developer

- `wb_listora_rate_limit_config` (filter) - change the limits for an action. It receives the limit array (`user_max`, `user_window`, `ip_max`, `ip_window`) and the action name. Return a changed array. A value of `0` for a maximum switches that counter off.
- `wb_listora_rate_limit_bypass` (filter) - return `true` to skip the limit for an action and member.
- `wb_listora_rate_limit_ip_counts_members` (filter) - return `true` to also count signed-in members against their network address.
- `wb_listora_trust_proxy_headers` (filter) - return `true` when the site sits behind a CDN or load balancer whose `X-Forwarded-For` header can be trusted. Otherwise Listora reads the connecting address only, because a plain host would let a visitor send any header.
- `wb_listora_trusted_proxy_hops` (filter) - how many trusted proxies sit in front of WordPress. Default 1.

```php
// Behind a CDN: trust X-Forwarded-For.
add_filter( 'wb_listora_trust_proxy_headers', '__return_true' );

// Allow guests 3 listing submissions an hour.
add_filter( 'wb_listora_rate_limit_config', function ( $config, $action ) {
	if ( 'submission' === $action ) {
		$config['ip_max'] = 3;
	}
	return $config;
}, 10, 2 );
```

The action names are the ones in the table's order: `submission`, `review_create`, `review_reply`, `review_vote`, `review_photo`, `review_report`, `listing_report`, `claim_submit`, `favorite`, `bulk_listings`, `search`, `search_suggest`, `public_read`, `app_password`, `resend_verification`, `verify_email`, `unsubscribe`, `account_deactivate` and `account_delete`.

## Settings and options

| Setting | Where | Default |
|---|---|---|
| Limits per action | Filter `wb_listora_rate_limit_config` | See the table above |
| Trust a CDN's forwarded address | Filter `wb_listora_trust_proxy_headers` | Off |
| Contact form daily cap per listing | Filter `wb_listora_contact_form_per_listing_daily_cap` | 20 |

## Related

- [Spam Protection](spam-protection.md) - the content checks that pair with rate limits.
- [Contact Form](contact-form.md) - the form with its own limits.
- [Developer Reference: Hooks](../developer-guide/hooks-reference.md) - every filter and action.
