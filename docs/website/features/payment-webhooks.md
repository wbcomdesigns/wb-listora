# Payment Webhook Receiver

> **Pro feature** — requires [WB Listora Pro](../getting-started/activating-pro.md).
Accept payment-completed webhooks from Stripe, PayPal, Paddle, or any custom payment processor and convert them into credits on the user's balance — payment-gateway-agnostic by design. Strict HMAC verification (with timestamp + nonce replay protection) is on by default; the receiver follows ADR-002 (payload must be HMAC-verified AND replay-protected before crediting).

![Payment Webhooks — settings tab showing endpoint URL, secret, and last-received log](../images/payment-webhooks-settings.png)

## What it is

WB Listora doesn't ship a payment gateway — that's a deliberate architectural choice (see [Credits & Pricing Plans (Pro)](credits-and-plans.md)). Instead, the plugin exposes a single hardened webhook endpoint and lets your payment processor of choice POST to it whenever a payment completes. The receiver converts the payment into credits on the user's balance.

Why payment-gateway-agnostic:
- Sites that use Stripe Checkout point Stripe at the webhook URL.
- Sites that use WooCommerce checkout flow use the Pro's bundled SDK adapters.
- Sites that use Paddle / Gumroad / Lemon Squeezy / a custom gateway just need to POST a tiny JSON payload + an HMAC signature.

Security model (the non-negotiable part):

- **Strict HMAC mode is the default** (`wb_listora_pro_webhook_strict_hmac` option, on by default since 1.0.5). The receiver requires:
  - `X-Listora-Signature` HMAC-SHA256 of the raw body, computed with the shared secret.
  - `X-Listora-Timestamp` within 5 minutes of server time (rejects replays past freshness window).
  - `X-Listora-Nonce` — a random per-request nonce stored in a short-TTL transient; reject if seen before (replay defence even within the freshness window).
- **Legacy fallback** (legacy sites) — admin can disable strict mode in Settings → Webhooks → **Strict HMAC** to allow the old shared-secret header path. Disabling is admin-only, audited, and discouraged.
- **All accepted webhooks** land in the **Audit Log** (Pro) so disputes are reconstructable.

What the endpoint does on a verified payment:
1. Calls the Credits SDK `Credits::topup($user_id, $amount, $context)` — idempotent on `gateway_payment_id` so retries don't double-credit.
2. Fires `wb_listora_pro_payment_received` action + the canonical `wb_listora_pro_credits_added` event (via the SDK bridge).
3. Auto-resumes any of the user's listings currently in `listora_payment` ("Awaiting Credits") status whose plan cost is now covered.

## How you use it

### As a site owner — set up an integration

1. **Enable the feature:** Listora → Settings → Features → **Credit System / Webhook Receiver** (always-on infrastructure; on by default).
2. **Visit Settings → Webhooks** — copy your **Webhook URL** (`https://yoursite.com/wp-json/listora/v1/webhooks/payment`) + your **Webhook Secret** (regenerate if needed).
3. **Configure your payment processor:**
   - **Stripe** — Stripe Dashboard → Developers → Webhooks → Add endpoint → URL = your webhook URL → events = `checkout.session.completed` + `payment_intent.succeeded`. Stripe's signing secret is separate; the bridge between Stripe's signature and Listora's signature is built into the receiver.
   - **Paddle** — Paddle Dashboard → Developer Tools → Notifications → Add → URL = your webhook URL → events = `transaction.completed`.
   - **Custom processor** — POST JSON to the URL with `X-Listora-Signature: <HMAC-SHA256(body, secret)>`, `X-Listora-Timestamp: <unix>`, `X-Listora-Nonce: <random>`.
4. **Test:** trigger a small test payment → check Listora → Settings → Webhooks → **Last received** for a 2xx + the credit balance for that user updated.

### Payload shape (custom processor)

```json
POST /wp-json/listora/v1/webhooks/payment
Content-Type: application/json
X-Listora-Signature: 5e3a7c…
X-Listora-Timestamp: 1716232847
X-Listora-Nonce: a8f3-…

{
  "event": "payment.completed",
  "gateway": "your-gateway-id",
  "gateway_payment_id": "ch_3OZ…",   // idempotency key
  "user_id": 42,
  "user_email": "owner@example.com",
  "amount_credits": 100,
  "amount_currency": 19.99,
  "currency": "USD",
  "meta": { ... }
}
```

## Settings & options

| Setting | Location | Default | Notes |
|---|---|---|---|
| Endpoint | `POST /wp-json/listora/v1/webhooks/payment` | Always registered | Public-write endpoint, HMAC-gated |
| Strict HMAC | Settings → Webhooks → Strict HMAC | **On** | Disable only to support legacy integrations |
| Webhook secret | Settings → Webhooks → Secret | Auto-generated on activation | Regenerable; invalidates existing integrations |
| Idempotency window | (system) | Per-`gateway_payment_id` | Same payment ID never credited twice |
| Replay protection | Timestamp (5min) + Nonce (10min) | (system) | Both must pass |
| Log | Audit Log (Pro) | — | Every accepted/rejected webhook recorded |

Developer hooks:

- `wb_listora_pro_payment_received` (action, 4 args) — fires after credits land. Listeners email, push to CRM, notify Slack.
- `wb_listora_pro_webhook_strict_hmac` (option / filter) — programmatic override of strict-mode (advisable: never disable in production).
- `wb_listora_pro_webhook_payload_normalized` (filter) — modify the parsed payload before crediting (e.g. apply gateway-specific currency conversion).
- `wb_listora_pro_webhook_verification_failed` (action) — listen for rejected webhooks for monitoring.

## Related

- [Credits & Pricing Plans (Pro)](credits-and-plans.md) — the credit system this webhook receiver feeds into.
- [Outgoing Webhooks (Pro)](outgoing-webhooks.md) — the *outbound* counterpart; same HMAC contract, opposite direction.
- [Audit Log (Pro)](audit-log.md) — every payment webhook (accepted + rejected) recorded.
- [Pricing Plans (Pro)](pricing-plans.md) — paused listings auto-resume on top-up.
- [Developer Reference: REST API](../developer-guide/rest-api.md) — webhook endpoint shape.
