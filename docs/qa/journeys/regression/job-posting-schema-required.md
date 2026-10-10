---
journey: job-posting-schema-required
plugin: wb-listora
priority: normal
roles: [anon]
covers: [10368644969]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
---

# JobPosting schema carries title and datePosted

Job listings output JobPosting JSON-LD without `datePosted` or `title`, both required by Google for job results.

## Steps

### 1. Job listing JSON-LD
- **Action**: `curl -s $SITE_URL/listing/<a job listing>/`, parse every `application/ld+json` block.
- **Expect**: the JobPosting object has `title` = the listing title and `datePosted` = its publish time in ISO 8601 UTC.
- **On fail**: `includes/schema/class-schema-generator.php` `get_data()`.

## Pass criteria

ALL of the following hold:
1. `title` and `datePosted` present on every JobPosting.
2. `JobPostingSchemaTest` passes.
