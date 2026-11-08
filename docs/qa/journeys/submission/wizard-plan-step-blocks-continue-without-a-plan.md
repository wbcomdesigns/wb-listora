---
journey: wizard-plan-step-blocks-continue-without-a-plan
group: submission
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [owner]
covers: [submission-wizard, plan-step, credits, "10487339174"]
prerequisites:
  - "Pro active with two plans priced above {owner}'s balance (0 credits); a type that accepts submissions"
estimated_runtime_minutes: 6
last_verified: 2026-11-08
verified_against: free 2.1.1 + pro 2.1.1
viewports: [1440, 390]
themes: [reign]
site_profile: required
---

# The Plan step cannot be passed with no plan, and says what to do

## Why
With a 0-credit balance every plan radio is disabled, so none carries `required` and `validateStep()` found nothing to check: Continue went to Preview and the refusal came only as a 400 on Submit, with a message that matched nothing on screen. The plan group now opts in with `data-listora-required-group`.

## Steps
### 1. Blocked with nothing selectable
- **Action**: as {owner} (0 credits) open `{add_listing}`, pick a type, fill Basic info and Details, add a featured photo, reach step 5 "Plan", press Continue.
- **Expect**: the wizard stays on Plan; the plan list is outlined and a red line under it reads "You need more credits for any of these plans. Use Buy Credits above - your draft is saved and waiting here."; no request to `/listora/v1/submit`.

### 2. A plan picked still goes through
- **Action**: give {owner} enough credits (or, in the console, enable and tick one plan radio), press Continue.
- **Expect**: Preview opens and the error line is gone.

### 3. Phone width
- **Action**: repeat step 1 at 390.
- **Expect**: same message, no horizontal scroll (`document.documentElement.scrollWidth` is 390).

### 4. One heading per meaning
- **Action**: on step 3 "Details" read the fieldset legends.
- **Expect**: the group that holds only the social link fields is headed "Social links", not "Media"; step 4 is the only "Media".

## Cleanup
Delete the draft and uploaded image the walk created.
