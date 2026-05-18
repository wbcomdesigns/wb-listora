# 20 — Claim Listing

## Scope

| | Free | Pro |
|---|---|---|
| Basic claim flow | Yes | Yes |
| Proof submission (text + files) | Yes | Yes |
| Admin review & approve/reject | Yes | Yes |
| Email notifications | Yes | Yes |
| Paid claim (fee to claim) | — | Yes |
| Auto-verification methods | — | Yes |

---

## Purpose

Site owners seed directories with business data. Real business owners then "claim" their listing to take ownership, update info, and respond to reviews. This is the Yelp/Google model and a proven revenue path.

---

## Claim Flow

### Step 1: Visitor Sees Unclaimed Listing
```
┌─────────────────────────────────────────────────────┐
│ Pizza Palace                                        │
│ ★★★★½ 4.5 (23 reviews)                            │
│                                                     │
│ ┌──────────────────────────────────────────┐        │
│ │ 🏷 Is this your business?               │        │
│ │ Claim this listing to update info,       │        │
│ │ respond to reviews, and more.            │        │
│ │                          [Claim Now →]   │        │
│ └──────────────────────────────────────────┘        │
└─────────────────────────────────────────────────────┘
```

### Step 2: Claim Form (Modal or Page)
```
┌─────────────────────────────────────────────────────┐
│ Claim: Pizza Palace                                 │
│                                                     │
│ Please verify that you are the owner or             │
│ authorized representative of this business.         │
│                                                     │
│ Your Name *                                         │
│ [ John Smith                                    ]   │
│                                                     │
│ Your Role *                                         │
│ [ Owner ▾ ]  (Owner / Manager / Representative)     │
│                                                     │
│ How can we verify? *                                │
│ ┌───────────────────────────────────────────────┐   │
│ │ I am the owner of Pizza Palace at 123 Main   │   │
│ │ Street. I can provide my business license     │   │
│ │ number: BL-12345-NYC                          │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ Supporting Documents (optional)                     │
│ [📎 Upload] Business license, utility bill, etc.   │
│                                                     │
│ ☑ I confirm I am authorized to manage this listing │
│                                                     │
│                              [Submit Claim]         │
└─────────────────────────────────────────────────────┘
```

### Step 3: Admin Reviews Claim
Admin page: `Listora → Claims`

```
┌─────────────────────────────────────────────────────┐
│ Claim #42 — Pizza Palace                            │
│                                                     │
│ Status: 🟡 Pending                                  │
│ Submitted: March 15, 2026                           │
│                                                     │
│ Claimant: John Smith (john@example.com)             │
│ Role: Owner                                         │
│                                                     │
│ Verification text:                                  │
│ "I am the owner of Pizza Palace at 123 Main Street. │
│  I can provide my business license: BL-12345-NYC"   │
│                                                     │
│ Documents: [business-license.pdf] [View]            │
│                                                     │
│ Admin Notes:                                        │
│ [                                               ]   │
│                                                     │
│ [✓ Approve Claim]  [✗ Reject Claim]                │
└─────────────────────────────────────────────────────┘
```

### Step 4: Claim Approved
- Listing ownership transferred to claimant
- Claimant becomes post author
- "Claimed" badge appears on listing
- Claimant can now edit listing and reply to reviews
- Email notification sent to claimant

### Step 4b: Claim Rejected
- Rejection reason sent to claimant via email
- Claimant can re-submit with additional proof

---

## Post-Claim: Listing Shows "Claimed" Badge

```
✓ Claimed · Owner verified
```

This badge:
- Shows on listing card (small checkmark)
- Shows on listing detail page
- Builds trust with visitors
- Stored as `is_claimed = 1` in search_index

---

## REST API

```
POST /listora/v1/claims                → submit claim
GET  /listora/v1/claims                → list claims (admin)
GET  /listora/v1/claims/{id}           → single claim (admin)
PUT  /listora/v1/claims/{id}           → approve/reject (admin)
```

---

## Database

See `06-database.md` for `listora_claims` table schema.

---

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Listing already claimed | Show "This listing is already claimed" message |
| Multiple claim requests | Only process first pending claim; queue others |
| User claims their own listing | Auto-approve (they're already the author) |
| Claim on a listing the user submitted | Show "You already own this listing" |
| Admin revokes claim later | Reset author to original, remove claimed badge |
