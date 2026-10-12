---
journey: job-posting-schema-required
plugin: wb-listora
priority: normal
roles: [anonymous, admin]
covers: [10368644969, structured-data, job-posting]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "A Job listing that is PUBLISHED. On this site all 14 demo jobs are `listora_expired` (validThrough 2026-06-21..07-08 precedes their datePosted), so no public job page exists: create QA-J1 job, publish it, or read `Schema_Generator::for_listing( id )->get_data()` directly"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# JobPosting schema carries title, datePosted and Google-shaped fields

Source: `includes/schema/class-schema-generator.php:108-112` adds `title` and `datePosted` (publish time, ISO 8601 UTC) beside `name`. 2.0.0 also puts the location in `jobLocation`, gives the salary currency and unit, and uses Google's employment type and remote values. The schema is printed in a `<script type="application/ld+json">` on the single listing page unless an SEO plugin owns it (`wb_listora_seo_plugin_active()`, `includes/class-plugin.php:1083`).

Verified via: `Schema_Generator::for_listing()->get_data()` for listings 456, 466, 471 (expired jobs), 2026-10-12. Result: `title` = listing title, `datePosted` "2026-09-13T15:21:50+00:00", `jobLocation.address` PostalAddress (street, locality NY, postal, country US), `employmentType` FULL_TIME, `baseSalary` MonetaryAmount `currency` USD, `value.minValue/maxValue/unitText` YEAR, `jobLocationType` TELECOMMUTE on remote ones. The rendered page JSON-LD was not fetched (no published job).

## Steps

### 1. Job listing JSON-LD
- **Action**: publish a Job fixture, `curl -s <permalink>`, parse every `application/ld+json` block.
- **Expect**: the `JobPosting` has `title` = listing title, `datePosted` ISO 8601 UTC, `jobLocation` (not a top-level address), `baseSalary.currency` + `unitText`, `employmentType` in Google's enum (FULL_TIME, PART_TIME...), `jobLocationType: "TELECOMMUTE"` only for remote.

### 2. POSITIVE CONTROL: a non-job type
- **Action**: same on a Restaurant listing.
- **Expect**: type `Restaurant`/LocalBusiness, no `datePosted`.

### 3. SEO plugin owns it
- **Action**: with Yoast/Rank Math active the Listora block is absent (by design); with none it is present.

### 4. Role ladder
- Public output; anonymous, member, owner, editor, admin see the same JSON-LD (the draft/expired preview for owner/admin aside). Admin is only the control.

## Pass criteria

1. `title` and `datePosted` present on every JobPosting.
2. `JobPostingSchemaTest` passes.

## Fail diagnostics

| Symptom | File |
|---|---|
| Missing title/datePosted | `includes/schema/class-schema-generator.php:108-112` `get_data()` |
| Job type saved as LocalBusiness | listing-type save path (2.0.0 readme Fix: saving Job/Classified/Education/Healthcare/Real Estate keeps its Schema.org type): `includes/core/class-listing-type-registry.php:299` area |

## Not verified

The printed `<script>` on a published job page.
