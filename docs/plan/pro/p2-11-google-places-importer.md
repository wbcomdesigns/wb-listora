# P2-11 — Google Places Importer (Pro)

## Overview

Import business data from Google Maps/Places API to bootstrap a directory with real listings. Admin enters a location + radius + category, fetches businesses from Google, previews results, and imports selected ones.

## User Stories

- **Directory admin:** "I want to pre-populate my restaurant directory with 500 real restaurants in NYC before launch."
- **Agency:** "Client wants a dental directory. I need to import all dentists in their city as a starting point."

## Field Mapping

| Google Places Field | Listora Field |
|---|---|
| name | post_title |
| editorial_summary | post_content |
| formatted_address | address (geo table) |
| geometry.location.lat/lng | lat/lng (geo table) |
| address_components | city, state, country, postal_code (geo table) |
| formatted_phone_number | phone |
| website | website |
| opening_hours.periods | business_hours |
| photos[0] | featured image |
| photos[1-5] | gallery |
| rating | avg_rating (search_index) |
| user_ratings_total | review_count (search_index) |
| price_level | price_range |
| types | listing categories |

## Competitive Context

- GeoDirectory: paid addon ($49/year)
- Directorist: does not have this
- Listora Pro: included in bundle

## Effort: ~4hr
