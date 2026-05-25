# WB Listora - Testimonial Placeholders

Eight testimonial slots for the sales team to fill with real customer quotes. Each entry includes: the customer persona, use case context, pain solved, an outcome metric slot, and an attribution slot. Mix of formats included: short pull-quote, extended narrative, video script prompt, and case study summary.

Sales team: replace every `{{PLACEHOLDER}}` with verified customer data before publishing. Do not publish placeholder text on any public page.

---

## Testimonial 1 - Short Pull-Quote (Featured on sales page hero)

**Format:** Pull-quote, 2-3 sentences, designed to sit beside a product screenshot.

**Customer persona:** Independent restaurant directory operator, single city, 80-150 vendors.

**Use case:** Built a paid city food guide on WB Listora Pro after trying a different directory plugin. Needed a way to charge restaurants without a separate WooCommerce build.

**Pain solved:** Previous tool required a separate payment add-on that didn't connect to the listing expiration system - vendors paid but listings didn't renew automatically.

**Outcome metric:** {{NUMBER_OF_PAYING_VENDORS}} restaurants paying {{ANNUAL_FEE}} per year within {{MONTHS_TO_REACH_MILESTONE}} months of launch.

```
"{{CUSTOMER_QUOTE_2_TO_3_SENTENCES}}"

- {{CUSTOMER_NAME}}, {{BUSINESS_OR_SITE_NAME}}
{{CITY_AND_COUNTRY}} - {{DATE_OF_QUOTE}}
```

---

## Testimonial 2 - Extended Narrative (Used in email sequences and case study page)

**Format:** 4-6 sentences, conversational, tells the before/after story.

**Customer persona:** Niche directory operator, yoga and wellness studios, national scope.

**Use case:** Runs a directory of independent yoga studios. Needed multi-criteria reviews (class quality, instructor, cleanliness separately) and a lead form that delivered inquiries to studio owners with Reply-To set correctly.

**Pain solved:** Previous setup used a contact form plugin that delivered leads to the admin email, not the studio owner - every lead required manual forwarding.

**Outcome metric:** {{LEAD_FORM_FILLS_PER_MONTH}} leads delivered directly to studio owners per month. {{STUDIO_UPGRADE_RATE}}% of free listings upgraded to Pro within {{MONTHS}} months.

```
"{{CUSTOMER_NARRATIVE_4_TO_6_SENTENCES_BEFORE_AFTER_STORY}}"

- {{CUSTOMER_NAME}}, {{ROLE_OR_TITLE}}
{{SITE_NAME}} - {{DATE_OF_QUOTE}}
```

---

## Testimonial 3 - Agency / Developer Voice (Used in developer-facing copy and agency sales)

**Format:** 3-4 sentences focusing on the technical experience - hooks, REST API, migration tools.

**Customer persona:** WordPress agency developer, builds directory sites for clients.

**Use case:** Agency switched from building directories on Directorist to WB Listora after a client needed a competitor migration (GeoDirectory) and the existing tool had no migrator.

**Pain solved:** Manual GeoDirectory-to-Directorist migration took 3 days. The WB Listora built-in migrator reduced the same type of job to under 4 hours with field mapping preserved.

**Outcome metric:** Agency now builds {{NUMBER_OF_DIRECTORIES}} directory projects per year on WB Listora. Average setup time dropped from {{OLD_SETUP_HOURS}} hours to {{NEW_SETUP_HOURS}} hours per project.

```
"{{DEVELOPER_QUOTE_3_TO_4_SENTENCES_TECHNICAL_FOCUS}}"

- {{DEVELOPER_NAME}}, {{ROLE}}
{{AGENCY_NAME}} - {{DATE_OF_QUOTE}}
```

---

## Testimonial 4 - Video Script Prompt (Used on product page video)

**Format:** 90-second video script outline. Sales team: use this as a prompt when scheduling a customer interview. Record, edit to 90 seconds, add captions.

**Customer persona:** B2B services marketplace operator (IT consultants, HR agencies).

**Use case:** Built a reverse marketplace where buyers post service Needs and vendors pay credits to respond. Runs on WB Listora Pro's Needs feature.

**Pain solved:** Tried to build the same concept as a custom WooCommerce build - took 4 months, broke on plugin updates, had no moderation layer.

**Interview questions (pull 90 seconds of usable quotes from these):**
1. "What were you trying to build before you found WB Listora?"
2. "What made you choose it over building custom?"
3. "Walk me through what the Needs marketplace looks like from a vendor's side."
4. "How long did it take to get the first vendor responding to a Need?"
5. "What's one thing that surprised you about how it works?"
6. "What does the revenue model look like now vs. before?"

**Outcome metric:** {{NUMBER_OF_ACTIVE_VENDORS}} active vendors. {{NEEDS_PER_MONTH}} buyer Needs posted per month. {{RESPONSE_FEE_REVENUE_MONTHLY}} in monthly response fee revenue.

```
VIDEO ATTRIBUTION:
Name: {{CUSTOMER_NAME}}
Title / Role: {{ROLE}}
Site: {{SITE_URL}}
Recorded: {{DATE}}
Release signed: {{YES_NO}}
```

---

## Testimonial 5 - Moderator / Team Operations Voice (Used in Pro feature pages)

**Format:** 2-3 sentences focused on the moderation team and audit log features.

**Customer persona:** Directory operator with a paid moderation team - non-admin users who approve listings and review claims.

**Use case:** Needed to delegate listing approval to 3 part-time moderators without giving them full WordPress admin access. Used WB Listora Pro's Moderator Team feature.

**Pain solved:** Previous approach gave moderators Editor role - they could accidentally delete pages, edit settings, or see revenue data. WB Listora's custom caps (`moderate_listings`, `moderate_claims`) give exactly the right access and nothing more.

**Outcome metric:** Moderation team approves {{LISTINGS_PER_WEEK}} listings per week. Time from submission to approval dropped from {{OLD_APPROVAL_TIME}} to {{NEW_APPROVAL_TIME}} with the moderation queue.

```
"{{CUSTOMER_QUOTE_2_TO_3_SENTENCES_MODERATION_FOCUS}}"

- {{CUSTOMER_NAME}}, {{ROLE}}
{{SITE_NAME}} - {{DATE_OF_QUOTE}}
```

---

## Testimonial 6 - Migrator / Switching Story (Used in migration landing pages)

**Format:** 4 sentences: what they were on, why they switched, how the migration went, where they are now.

**Customer persona:** Established directory operator migrating from Directorist with 500+ existing listings.

**Use case:** Ran a regional business directory on Directorist for 2 years. Needed to switch to WB Listora for the unified payment/credit system. Had 500 listings with custom fields (business hours, social links, services).

**Pain solved:** Feared losing custom field data in the migration. Used WB Listora's built-in Directorist migrator with dry-run first - confirmed field mapping before touching live data.

**Outcome metric:** {{NUMBER_OF_LISTINGS}} listings migrated in {{MIGRATION_DURATION}}. {{CUSTOM_FIELDS_PRESERVED}} custom fields preserved without manual re-entry.

```
"{{CUSTOMER_QUOTE_4_SENTENCES_MIGRATION_STORY}}"

- {{CUSTOMER_NAME}}, {{ROLE}}
{{SITE_NAME}} - {{DATE_OF_QUOTE}}
```

---

## Testimonial 7 - Membership Site Integration Voice (Used in membership + directory use case pages)

**Format:** 3-4 sentences describing the MemberPress or PMPro + WB Listora combo.

**Customer persona:** Membership site owner who grants listing credits as a membership benefit - members in a paid tier automatically get credits to list their business.

**Use case:** Runs a membership community for freelance designers. Pro members get 10 listing credits per month included in their membership. When a member upgrades or renews, credits appear automatically via the MemberPress adapter.

**Pain solved:** Previously required members to go through a separate checkout to buy listing credits - 40% dropped off between membership purchase and listing creation. With the adapter, credits arrive with the membership.

**Outcome metric:** Listing submission rate among Pro members rose from {{OLD_SUBMISSION_RATE}}% to {{NEW_SUBMISSION_RATE}}% after the adapter integration.

```
"{{CUSTOMER_QUOTE_3_TO_4_SENTENCES_MEMBERSHIP_INTEGRATION}}"

- {{CUSTOMER_NAME}}, {{ROLE}}
{{SITE_NAME}} - {{DATE_OF_QUOTE}}
```

---

## Testimonial 8 - Case Study Summary (Used as a downloadable PDF and long-form case study page)

**Format:** Structured case study summary. 200-250 words. Fill in all placeholders before publishing.

**Customer persona:** Regional real estate directory operator, 3 agents contributing listings, advertising revenue from featured placements.

**Use case:** Built a regional real estate directory to capture search traffic for a specific metro area. Uses WB Listora's Real Estate listing type (bedrooms, price range, square footage custom fields, Schema.org markup). Pro plan enables featured placement rotation for agencies that pay for premium visibility.

**Pain solved:** Previous directory plugin (WPBDP) had no geo radius search and no schema markup - listings didn't rank in Google's local results. Required a separate plugin for maps.

**Full case study outline for sales team to populate:**

```
## {{SITE_NAME}} - WB Listora Case Study

**Industry:** Real Estate
**Region:** {{LOCATION}}
**Launch date:** {{DATE}}
**Stack:** WordPress {{WP_VERSION}}, WB Listora {{LISTORA_VERSION}}, {{THEME_NAME}}, {{HOST_NAME}}

### The Situation
{{2_SENTENCES_DESCRIBING_THE_OPERATOR_AND_THEIR_GOAL}}

### The Problem with Their Previous Setup
{{2_TO_3_SENTENCES_DESCRIBING_THE_PAIN}}

### What Changed with WB Listora
{{3_TO_4_SENTENCES_DESCRIBING_WHAT_THEY_CONFIGURED_AND_HOW_LONG_IT_TOOK}}

### Results
- Listings indexed: {{NUMBER}}
- Organic traffic (month 6 vs month 1): {{PERCENTAGE_CHANGE}}
- Featured placements sold: {{NUMBER}} at {{PRICE}} each
- Time to first paying advertiser: {{DAYS_FROM_LAUNCH}}

### In Their Words
"{{CUSTOMER_QUOTE}}"
- {{CUSTOMER_NAME}}, {{ROLE}}, {{SITE_NAME}}

### What's Next
{{1_TO_2_SENTENCES_ABOUT_PLANNED_EXPANSION_OR_NEXT_FEATURES}}
```

---

*Instructions for the sales team: Collect testimonials via a post-onboarding email survey at 60 days (long enough for the customer to have real results). For video testimonials, offer a 15-minute Zoom call and record with permission. Always get written approval before publishing name, site URL, or revenue figures. Store signed releases in {{RELEASE_STORAGE_LOCATION_PLACEHOLDER}}.*
