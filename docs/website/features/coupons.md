# Coupons

> **Pro feature** — requires [WB Listora Pro](../getting-started/activating-pro.md). Free sites do not have a coupon system.

## What it does

Coupons let you offer discount codes that reduce the credit cost of a listing plan. Users enter a coupon code during the plan selection step of the submission form. The discount applies immediately — before the user confirms and credits are deducted.

## Why you'd use it

- Run limited-time promotions to drive listing submissions.
- Offer referral discounts or partner codes to specific business groups.
- Restrict coupons to specific plans so a 50% discount only applies to your premium tier.
- Set per-user usage limits to prevent a single person from using a code multiple times.

## How to use it

### For site owners (admin steps)

**Creating a coupon:**

1. Go to **Listora → Coupons → Add New Coupon**.
2. Set the coupon title (this is your internal reference name, not the code).
3. Fill in the coupon fields:
   - **Coupon Code** — the code users will enter (e.g., `LAUNCH50`). Codes are case-insensitive.
   - **Type** — choose **Fixed** (deduct a set number of credits) or **Percent** (deduct a percentage of the plan cost).
   - **Value** — the discount amount (e.g., `10` for 10 credits off, or `20` for 20% off).
   - **Usage Limit** — total number of times the code can be used across all users. Set to `0` for unlimited.
   - **Per User Limit** — maximum times a single user can use the code. Set to `0` for unlimited.
   - **Expiry Date** — date after which the code is no longer valid. Leave blank for no expiry.
   - **Minimum Credits** — minimum plan credit cost required for the coupon to apply. Set to `0` for no minimum.
   - **Restrict to Plans** — optionally limit the coupon to one or more specific plans by selecting their names.
4. Publish the coupon.
5. Share the code with users.

**Checking usage:**

The coupon list at **Listora → Coupons** shows the usage count next to each coupon. Open a coupon to see the current count against the usage limit.

**Deactivating a coupon:**

Set the coupon's status to **Draft** to immediately disable it. Users who enter the code will see an "inactive" error.

### For end users (visitor/user-facing)

1. During listing submission, the **Choose a Plan** step includes a coupon field.
2. Enter the code and click **Apply**.
3. If the code is valid for the selected plan, the discounted credit cost appears immediately on the plan card.
4. Select a plan and continue — the discounted amount is deducted from your balance at submission.

## Tips

- Use uppercase codes — they're easier to communicate and the system normalizes to uppercase automatically.
- For launch promotions, set a short expiry date and a reasonable usage limit rather than a permanent unlimited coupon.
- Restrict high-value coupons to your most expensive plan using **Restrict to Plans** — this prevents users from applying large discounts to free or low-cost plans.
- If you want a coupon for a specific group (e.g., a partner organization), set **Per User Limit** to `1` and distribute unique codes via email — but this requires creating one coupon per recipient. For bulk codes, consider a single shared code with a tight **Usage Limit**.
- A coupon with **Minimum Credits** set prevents applying it to free plans. Set `min_credits` to at least 1 to exclude free-plan submissions.

## Common issues

| Symptom | Fix |
|---------|-----|
| "Coupon code not found" error | Confirm the coupon is published (not Draft) in **Listora → Coupons** |
| "This coupon is not valid for the selected plan" | The coupon has **Restrict to Plans** set — the user must select one of the restricted plans |
| Code applied but discount not showing | The plan card updates the displayed cost; if it doesn't change, try clicking Apply again |
| "Coupon has reached its usage limit" | Increase the **Usage Limit** or create a new coupon |

## Related features

- [Credits and Plans](credits-and-plans.md)
- [Analytics](analytics.md)
