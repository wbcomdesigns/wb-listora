---
journey: empty-media-fieldset
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [listing-submission-step-details, fieldset-suppression]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "tester user exists with submission cap"
  - "Listing types: Business + Restaurant + Hotel + Place + Marketplace + Real Estate + Event + Medical + Course + Job Board all registered"
estimated_runtime_minutes: 5
---

# Empty Media fieldset regression sentinel

The submission wizard's Details step must NOT render an empty `<fieldset><legend>Media</legend></fieldset>` when the Media field-group has only renderer-skipped types. Pre-fix #9867347053 surfaced an empty Media legend with no input children. Sentinel for commit class involving `step-details.php` fieldset suppression.

## Setup

- Site: `$SITE_URL`
- User: `tester` (subscriber/contributor)

## Steps

### 1. Open Add Listing as tester
- **Action**: `playwright_navigate $SITE_URL/add-listing/?autologin=tester`
- **Expect**: wizard step 1 renders

### 2. Pick "Business" listing type → Continue
- **Action**: select Business → click Next
- **Expect**: wizard advances to Basic Info step

### 3. Fill Basic Info → Continue to Details step
- **Action**: fill required basic fields → Next
- **Expect**: Details step renders

### 4. Critical assertion — no empty Media fieldset
- **Action**: `browser_evaluate "Array.from(document.querySelectorAll('fieldset')).filter(fs => { const legend = fs.querySelector('legend'); return legend && legend.textContent.trim() === 'Media' && fs.children.length === 1; }).length"`
- **Expect**: `0`
- **On fail**: regression of #9867347053. See `templates/blocks/listing-submission/step-details.php` — must suppress the entire `<fieldset>` when every field in the group is renderer-skipped (e.g. types where Media field-group is empty).

### 5. Verify Media fieldset DOES render when group has fields
- **Action**: navigate back to Type step → pick "Restaurant" (which uses gallery field) → continue to Details
- **Expect**: Media fieldset visible with at least 1 input child (gallery / featured image picker)

### 6. Repeat for all 10 listing types
- **Action**: for each of Business, Restaurant, Hotel, Place, Marketplace, Real Estate, Event, Medical, Course, Job Board:
  - go to Type step → pick type → advance to Details
  - run the empty-fieldset assertion from step 4
- **Expect**: zero empty Media fieldsets across all 10 types

### 7. Verify wizard can proceed past Details
- **Action**: fill required fields → Next
- **Expect**: advances to Media step (or Plan, depending on wizard order). No console errors.

## Pass criteria

1. No `<fieldset>` whose only child is `<legend>Media</legend>` exists in DOM at any step
2. Media fieldset DOES render when there are visible input fields
3. All 10 listing types pass step 4
4. Wizard advances normally after Details step

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Empty Media fieldset visible for some type | regression of #9867347053 | `templates/blocks/listing-submission/step-details.php` — fieldset wrap should suppress when 0 visible fields |
| Wizard stuck on Type step | type registration broken | `Listing_Type_Registry` |
| Media fieldset never renders even for Restaurant | over-eager suppression | step-details.php — visible-field count |
