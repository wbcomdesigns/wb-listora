---
journey: overview-company-logo-id
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [listing-detail-tabs, file-field-skip-on-overview]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 1 published Job listing with Company Logo set"
estimated_runtime_minutes: 2
---

# Overview tab no raw attachment ID regression sentinel

The Overview tab on a Job (or any) listing must NOT print `Company Logo: 818` (raw attachment ID). Pre-fix #9867775853: `tabs.php` Overview loop iterated all fields including `file` type, which has no resolved value renderer — so it printed the raw stored value (an attachment ID). Sentinel: skip `file` type fields on the Overview loop; render them only on their own field-group tab as image/link.

## Setup

- Site: `$SITE_URL`
- Fixture: 1 published Job listing with Company Logo (file-type field) set. Capture slug as `LISTING_SLUG`.

## Steps

### 1. Visit the Job listing detail page
- **Action**: `playwright_navigate $SITE_URL/listing/$LISTING_SLUG`
- **Expect**: detail page renders, Overview tab is the active default

### 2. Critical assertion — no raw attachment ID
- **Action**: `browser_evaluate "document.querySelector('.listora-detail__tabs').textContent"`
- **Expect**: text does NOT match the regex `/Company Logo:\s*\d+\s*$/m`
- **On fail**: regression of #9867775853. See `templates/blocks/listing-detail/tabs.php` Overview loop — must skip `file` type fields.

### 3. Verify Company tab renders the logo as image (not as raw)
- **Action**: click "Company" tab (or whichever tab owns file fields for this type)
- **Expect**: `<img>` element with the logo, alt text, working src

### 4. Verify other field types still render on Overview
- **Action**: check Overview tab for text/select/url/email type fields
- **Expect**: they render with proper labels + values (not skipped)

## Pass criteria

1. Overview tab does NOT contain a `Company Logo: <integer>` block
2. Logo renders as image on the Company tab
3. Non-file-type fields still appear on Overview

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Raw attachment ID visible | regression of #9867775853 | `templates/blocks/listing-detail/tabs.php` Overview loop — must add `if ($field->type === 'file') continue;` |
| Logo missing from Company tab too | over-eager skip across all tabs | tabs.php — skip should apply to Overview ONLY |
