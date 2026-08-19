---
journey: blocking-enforced-on-live-contact-route
plugin: wb-listora
roles: [member, anonymous]
priority: critical
covers: [member-blocking, lead-form, contact-form, wb_listora_can_members_contact, app-config-contact-route, BC-10184284933, BC-10183618407]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora AND wb-listora-pro both active"
  - "Pro's `lead_form` feature ON (the paid default)"
  - "Two member accounts, and a published listing owned by one of them"
estimated_runtime_minutes: 6
---

# Blocking stops contact on the route members actually reach

There are two contact endpoints and **only one of them renders on any given site**:

| Route | Owner | Live when |
|---|---|---|
| `POST /listings/{id}/contact-form` | Free | `lead_form` is OFF |
| `POST /listings/{id}/contact` | Pro | `lead_form` is ON — the paid default |

Free's `Contact_Form::should_render()` suppresses itself when Pro's lead form is active. Free's
route had carried the block check since blocking shipped; Pro's had none. So on the paid
configuration **the enforced route was the dead one and the live route was unenforced** — a blocked
member could still message the person who blocked them, while the plugin's own summary of blocking
led with "stops the two members contacting each other".

This is App Store Guideline 1.2 severity, not cosmetic: the app posts to Pro's route, and Apple's
reviewer tests blocking from the app.

> The tell was that `Member_Blocks::can_contact()` had exactly three call sites, all in Free, and
> **zero** in Pro's `class-lead-form.php`. Pro's Needs already did it correctly — the lead form
> simply never got the same guard.

## Steps

### 1 — Establish a blocked pair

As member A, block member B (the listing owner) — from the review card, the dashboard, or
`POST /wp-json/listora/v1/me/blocks` with `{"user_id": B}`.

```bash
wp eval 'echo var_export( wb_listora_can_members_contact( A, B ), true );'   # false
wp eval 'echo var_export( wb_listora_can_members_contact( B, A ), true );'   # false — symmetric
```

Both directions must be false. Blocking is symmetric, so it holds whichever party did the blocking.

### 2 — The live route refuses

As A, POST to **Pro's** `/wp-json/listora/v1/listings/{B's listing}/contact` with a valid REST nonce
and the per-listing `_listora_lead_nonce` from the rendered form.

- **Expect** `403` with code `listora_contact_blocked`.
- **`200 {"sent": true}` is the original regression** and must fail the run.

### 3 — The other route refuses too

POST to Free's `/listings/{id}/contact-form`.

- **Expect** `403` `listora_contact_blocked`.

Both routes are asserted regardless of which one is live, because the decision on
`#10183618407` was "blocking works everywhere" — a guard on only the currently-live route
re-creates the original divergence the moment the toggle flips.

### 4 — Unblocking restores contact

Unblock, then repeat step 2.

- **Expect** `200` and `sent: true`. A guard that blocks everyone is the opposite failure and just
  as bad.

### 5 — Guests are unaffected

Log out and post to the live contact route.

- **Expect** it behaves exactly as before (no `listora_contact_blocked`). An anonymous sender has no
  member identity, and `is_blocked_pair()` returns false for a viewer ID of 0 by design.

### 6 — The app is told which route to use

```bash
curl -s $SITE_URL/wp-json/listora/v1/settings/app-config | jq '.contact_route'
```

- **Expect** `"contact"` when `lead_form` is ON, `"contact-form"` when it is OFF.
- The app previously had to guess which endpoint existed. A wrong guess is a 404 or a silently dead
  button.

## Cleanup

Remove the block, and delete any application passwords created for the probe. The lead form emails
rather than storing, so no rows persist.
