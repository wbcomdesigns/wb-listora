---
slug: needs-archive-is-the-marketplace
priority: normal
covers:
  - BC 10208510069
likely_files:
  - ../wb-listora-pro/templates/archive-listora_need.php
  - ../wb-listora-pro/includes/frontend/class-need-single-template.php
---

# /needs/ IS the marketplace, not a second product

The post-type archive fell through to whatever the theme does with an unknown
CPT — a blog-style list with "Written by … on …", no search, no type or urgency
filters, no cards — while the marketplace page rendered the needs-grid block
with all of it. Whichever URL a visitor hit or shared decided whether Needs
looked finished or unbuilt.

The archive now renders the needs-grid BLOCK through a plugin template. Not a
redirect, and deliberately not a hand-written archive loop: a second renderer
for the same data is what let the compare table drift until the shortcode broke.
One renderer, two routes.

## Steps

1. Visit `/needs/` as an anonymous visitor.
   - **Expect:** search box, type and urgency filters, need cards.
   - **Fail if:** you see "Written by …" or a bare post list. That is the theme
     archive, meaning the template did not take.
2. Visit the marketplace page.
   - **Expect:** structurally identical output. Assert equal counts of the
     grid-block marker, `<select>`, and search inputs on both URLs.
3. `/needs/` must NOT redirect.
   - **Expect:** HTTP 200. Existing links, shares and search results keep
     working at the URL people already have.
4. Check `<link rel="canonical">` on `/needs/`.
   - **Expect:** points at the marketplace page — two URLs serving identical
     content otherwise reads as duplicate content.
   - **Expect:** ABSENT when an SEO plugin (Yoast / Rank Math) is active. Two
     competing canonical tags are worse than none.
   - **Expect:** absent when the marketplace page is a DRAFT. The page registry
     still returns `?page_id=N` for a draft, so a naive check would point
     canonical at something no visitor can open.
5. Theme override: copy `archive-listora_need.php` into
   `{theme}/wb-listora/`.
   - **Expect:** the theme copy wins.
6. `/needs/feed/`
   - **Expect:** still a feed, HTTP 200, not HTML.
