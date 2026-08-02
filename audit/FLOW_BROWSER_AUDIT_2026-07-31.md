# Listora Functionality Flow Audit

**Site:** http://directory.local  
**When:** 2026-07-31T13:08:25.563Z  
**Build:** Free+Pro 1.3.0 (1.3.1 cycle start)

## Summary

| PASS | FAIL | WARN | SKIP |
|---:|---:|---:|---:|
| 42 | 0 | 1 | 3 |

## Results

| Group | Flow | Status | Detail |
|---|---|---|---|
| G1 | Directory page: search + map + grid | **PASS** | search=true map=true cards=27 results="Showing 1–20 of 86 listings" |
| G1 | Type filter (Place) | **PASS** | clicked=true cards=15 "Showing 1–8 of 8 listings" url=http://directory.local/listings/?type=place |
| G1 | Keyword search "Central Park" | **PASS** | cards=11 hasCentral=true "Showing 1–4 of 4 listings" |
| G1 | Map + Near Me control | **PASS** | map=true nearMe=true |
| G1 | Quick View from card | **PASS** | clicked={"ok":true} open={"found":true,"visible":true,"text":"1 / 5\nPlace\nCentral Park — The Mall & Bethesda Terrace\n5.0 (1 review)\nFeatured\n\nThe grand pedestrian promenade a |
| G1 | Add to Compare from card | **PASS** | clicked=true bar={"bar":true,"visible":false,"text":"\n\t\t\t\n\t\t\t\t0 of 4 selected\n\t\t\t\t\n\t\t\t\t\n\t\t\t\t\tClear\n\t\t\t\t\tCompare Now\n\t\t\t\t\n\t\t\t\n\t\t"} |
| G1 | Grid/List view toggle | **PASS** | toggleFound=true |
| G1 | Categories / Featured / Calendar blocks | **SKIP** | No dedicated pages with those blocks on this site (only /listings, /add-listing, /my-listings, /compare-listings) |
| G1 | Directory mobile 390px | **PASS** | search=true map=true cards=27 hScroll=false |
| G2 | Detail page loads (title, gallery, open-now, rating) | **PASS** | title="Central Park — The Mall & Bethesda Terrace" openNow=true rating=true gallery=true thumbs=7 featured=true |
| G2 | Detail actions (Save / Share / Directions / Compare) | **PASS** | save=true share=true directions=true compare=true claim=false |
| G2 | Lead form (Pro) or Free contact form | **PASS** | lead=true contact=false |
| G2 | Detail tabs present | **PASS** | tabs=["Services","Services","Overview","Location","Place Details"] |
| G2 | Share modal opens | **PASS** | clicked=true {"openCount":13,"text":["","\n\t\t\t\n\t\t\t\t\n\t\t\t\t\t\n\t\t\t\t\t\n\t\t\t\t\n\t\t\t\n\t\t\tReport This Listing\n\t\t\tTell us what is wrong s","\n\t\t\t\t\n\t\t\t |
| G2 | Guest Save → login prompt | **PASS** | clicked=true {"openCount":17,"asksLogin":true} |
| G2 | Reviews tab content | **PASS** | tabClicked=true {"hasReviews":true,"criteria":true,"writeForm":false} |
| G2 | Services tab | **PASS** | tabClicked=true |
| G3 | Guest submission gated (login required) | **PASS** | {"asksLogin":true,"wizard":false,"loginBtn":true} |
| G3 | Logged-in wizard Step 1 (Type) | **PASS** | types=10 steps=["1\n\t\t\t\t\n\t\t\t\n\t\t\tType","Type","2\n\t\t\t\t\n\t\t\t\n\t\t\tBasic Info","Basic Info","3\n\t\t\t\t\n\t\t\t\n\t\t\tDetails","Details","4\n\t\t\t\t\n\t\t\t\n\ |
| G3 | Wizard advances to Basic Info | **PASS** | inputs=313 hasTitle=true |
| G3 | Wizard multi-step navigation (no final submit) | **PASS** | progress={"bodyHint":["Type","Basic Info","Details","Media","Preview","Basic Info"],"active":["2\n\t\t\t\t\n\t\t\t\n\t\t\tBasic Info"],"validation":false} (stopped before publish t |
| G4 | Dashboard loads with nav tabs | **PASS** | nav=["Add Listing","Active","Pending","Reviews","Saved","Union Square Greenmarket","High Line — Elevated Linear Park","Statue of Liberty & Ellis Island","Brooklyn Botanic Garden"," |
| G4 | Credits tab gated when no purchase path | **WARN** | creditsTab=true (expected hidden — packs/plans empty) |
| G4 | Analytics tab (Pro) | **PASS** | analyticsTab=true |
| G4 | Dashboard tab: Overview | **PASS** | clicked=true fatal=false snippet="Skip to content directory Home About Services Pricing Journal Directory FAQ Contact System mode (click to switch to light) varundubey My Listings  |
| G4 | Dashboard tab: Reviews | **PASS** | clicked=true fatal=false snippet="Skip to content directory Home About Services Pricing Journal Directory FAQ Contact System mode (click to switch to light) varundubey My Listings  |
| G4 | Dashboard tab: Favorites | **PASS** | clicked=true fatal=false snippet="Skip to content directory Home About Services Pricing Journal Directory FAQ Contact System mode (click to switch to light) varundubey My Listings  |
| G4 | Dashboard tab: My Claims | **PASS** | clicked=true fatal=false snippet="Skip to content directory Home About Services Pricing Journal Directory FAQ Contact System mode (click to switch to light) varundubey My Listings  |
| G4 | Dashboard tab: Profile | **PASS** | clicked=true fatal=false snippet="Skip to content directory Home About Services Pricing Journal Directory FAQ Contact System mode (click to switch to light) varundubey My Listings  |
| G4 | Dashboard tab: Analytics | **PASS** | clicked=true fatal=false snippet="Skip to content directory Home About Services Pricing Journal Directory FAQ Contact System mode (click to switch to light) varundubey My Listings  |
| G4 | Manage Services modal | **PASS** | gear=true modal={"open":true,"text":"Services for \"Union Square Greenmarket\"\nAdd Service\nGuided Walking Tour\n$35.00\n1h 30m\nSunset Photo Experience\n$89.00\n1h "} |
| G5 | Reviews list on detail | **PASS** | {"list":true,"form":false,"ownerBanner":true,"helpful":true,"criteria":true,"reply":false} |
| G5 | Review form / owner restriction | **PASS** | form=false ownerBanner=true |
| G5 | Helpful vote control | **PASS** | helpful=true |
| G5 | Multi-criteria UI (Pro) | **PASS** | criteria=true |
| G6 | Compare page empty state | **PASS** | {"empty":true,"block":true,"fatal":false} |
| G6 | Compare with selections | **PASS** | {"stillEmpty":false,"hasTable":false,"listingNames":["Central Park","Brooklyn","Brooklyn","Brooklyn","Central Park"]} |
| G6 | Credit purchase page | **SKIP** | No credits page / block on site (wb_listora_pro_credits_page_id empty; packs empty) |
| G6 | Needs marketplace (post-need / needs-grid) | **SKIP** | reverse_listings toggle OFF on this site — blocks not expected |
| G6 | Lead form UI on detail | **PASS** | {"present":true,"inputs":6,"hasSubmit":true,"text":"Contact Owner\nYour Name * \nYour Email * \nPhone (optional) \nMessage * \nSend Message"} |
| G6 | Badges on detail | **PASS** | {"count":12,"featured":true,"verified":false} |
| API | Search REST | **PASS** | HTTP 200 |
| API | Listing types REST | **PASS** | HTTP 200 |
| API | Maps config REST | **PASS** | HTTP 200 |
| API | App config REST | **PASS** | HTTP 200 |
| API | Badges REST | **PASS** | HTTP 200 |
