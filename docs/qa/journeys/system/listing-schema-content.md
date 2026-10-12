---
journey: listing-schema-content
plugin: wb-listora
priority: high
roles: [system, guest, admin]
covers: [schema-generator-defense, social-links-json-recovery, address-malformed-skip, business-hours-malformed-skip, job-posting, structured-data, card-9905075024, card-10368644969, bc-10393531313]
prerequisites:
  - "Site reachable at $SITE_URL; WP_DEBUG and WP_DEBUG_LOG on"
  - "A published {listing} whose type shows address, social_links and business_hours on the listing page (fields switched off via 'Show on listing page' are absent from JSON-LD by design: inject only into fields the type shows)"
  - "A PUBLISHED Job listing {listing-job} (create QA-J1 job if every demo job is expired) and a Restaurant {listing-other-type} for the control"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light, unbridged]
site_profile: required
supersedes: [ora:regression/schema-generator-malformed-meta.md, ora:regression/job-posting-schema-required.md]
fails_on: "[] operator not supported for strings (card 9905075024); 3bd222ac (remote country, 11204e3c^)"
---

# The schema generator emits complete, Google-shaped JSON-LD per listing type and never fatals on malformed meta

## Why
A single malformed meta value (`[] operator not supported for strings`) took the listing page down for every visitor after a save; a JobPosting missing `title`/`datePosted` is rejected by Google Rich Results and loses the job from search.

`Schema_Generator::for_listing()` runs `normalize_meta_for_schema()` once, coercing `address`, `social_links`, `business_hours`, `gallery`, `features`, `price`, `map_location` to arrays (JSON strings decoded, garbage becomes `[]`). It reads `Meta_Handler::get_visible_values()`. JobPosting adds `title`, `datePosted` (ISO 8601 UTC), `jobLocation`, salary currency and unit, Google employment-type and remote values.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Read and save the originals of `_listora_social_links`, `_listora_address`, `_listora_business_hours` on {listing} first; restore them in Cleanup.

## Steps

### 1. Baseline (positive control)
- **Action**: `wp eval '$g=\WBListora\Schema\Schema_Generator::for_listing(ID); echo count($g->get_data());'`
- **Expect**: an integer >= 5 with the type's `@type`; nothing new in `wp-content/debug.log`.

### 2. String-typed social_links: no fatal
- **Action**: `wp post meta update ID _listora_social_links 'https://facebook.com/example'`; build the generator and call `get_data()` in try/catch.
- **Expect**: no Throwable.

### 3. String-typed address and business_hours together
- **Action**: set `_listora_address` and `_listora_business_hours` to `bad-string-not-array`; read `get_data()`.
- **Expect**: no fatal; `openingHoursSpecification` absent; JSON-LD is valid JSON.
- **expected-fail: empty PostalAddress for garbage address**: the correct behaviour is that `address` is absent; today it emits `{"@type":"Place","address":{"@type":"PostalAddress","streetAddress":"",...}}` from the type-property mapper (reported product bug).

### 4. JSON-string-of-array recovers
- **Action**: set `_listora_social_links` to `{"facebook":"https://facebook.com/x","twitter":"https://twitter.com/x"}`.
- **Expect**: `sameAs` contains both URLs (the permalink first when the listing has a website).

### 5. Bulk sweep over published listings
- **Action**: loop all published `listora_listing` ids through `for_listing()->get_data()` in try/catch; get the count with `wp post list --post_type=listora_listing --post_status=publish --format=count`.
- **Expect**: `fatals: 0`. For the scale pass include `post_status=any` (drafts are never rendered).

### 6. Browser path with the malformed meta in place
- **Action**: `curl -s -o /dev/null -w "%{http_code}" $SITE_URL/listing/<{listing} slug>/`.
- **Expect**: 200, no "critical error", two `application/ld+json` blocks (listing graph and `BreadcrumbList`). Restore the meta.

### 7. JobPosting fields
- **Action**: `curl -s <{listing-job} permalink>` and parse every `application/ld+json` block (or read `get_data()` if no published job exists).
- **Expect**: `title` equals the listing title; `datePosted` ISO 8601 UTC; `jobLocation` (not a top-level address) with a PostalAddress; `baseSalary` MonetaryAmount with 3-letter `currency` and `unitText`; `employmentType` in Google's enum (FULL_TIME, PART_TIME...); `hiringOrganization.name` non-empty; `jobLocationType: "TELECOMMUTE"` and `applicantLocationRequirements.name` with the country only for remote jobs.
- **Read-back**: `curl -s <permalink> | grep -o '"@type":"JobPosting".*' | jq '.hiringOrganization.name, .jobLocation["@type"], .baseSalary.currency, .employmentType'`. fails_on: 3bd222ac. Not verified live.

### 8. Positive control: a non-job type
- **Action**: same on {listing-other-type}.
- **Expect**: type `Restaurant`/LocalBusiness, no `datePosted`, none of the JobPosting keys.

### 9. SEO plugin owns it
- With an SEO plugin signal true the Listora block is absent (see `system/listing-seo-head-output.md`); this journey does not repeat that matrix.

### 10. Type saved keeps its Schema.org type
- **Action**: save the Job/Classified/Education/Healthcare/Real Estate listing type in the type editor and reload a listing of it.
- **Expect**: still `JobPosting` etc., never LocalBusiness (2.0.0 fix). Not verified live.

## Role ladder
Output is public and identical for {guest}, {owner}, {other}, {editor}, {admin}; the generator has no per-user branch. Verify once as {guest} (step 6, 7) and once as {admin} (control, step 1). A draft or expired job has no public page (404 for {guest}); {owner}/{admin} preview only.

## Pass criteria
1. No Throwable for string-typed, JSON-string or garbage meta; the page returns 200.
2. Every JobPosting carries `title`, `datePosted`, `jobLocation`, `baseSalary.currency`/`unitText` and a valid employment type.
3. A non-job type carries none of the JobPosting keys.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Fatal `[] operator` | appender on a non-array; `$array_keys` list missing a key | `includes/schema/class-schema-generator.php` `normalize_meta_for_schema()` (~:58-77) |
| JSON string not recovered | decode branch | same (~:66-76) |
| Empty PostalAddress for garbage address | type-property mapper | `class-schema-generator.php` (~:245) |
| Missing title/datePosted | `get_data()` | `class-schema-generator.php` (~:108-112) |
| Job type saved as LocalBusiness | listing-type save path | `includes/core/class-listing-type-registry.php` (~:299) |

## Not verified
Steps 7, 10 on a published job (all demo jobs expired on the reference site); step 3 address is expected-fail. Scale pass over thousands of drafts not run.

## Cleanup
`wp post meta update` the three meta keys back to the saved originals (or delete the ones that did not exist); delete the QA-J1 job fixture.
