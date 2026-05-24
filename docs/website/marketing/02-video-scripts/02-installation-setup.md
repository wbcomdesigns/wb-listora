# Video Script — Installation & Setup Tutorial (5-Minute)

A step-by-step tutorial for new buyers. Goes from downloading the zip at wblistora.com to having a live directory with a first published listing. Hand to an editor alongside this script - every scene calls out the exact screenshot to show.

**Total length: 4:45 - 5:05**
**Audience: WordPress site owners who just purchased or downloaded WB Listora**
**Voiceover pace: 145-155 words/min. Tutorial pace - slower than the overview tour. Viewers need time to follow along.**
**Hook: "By the end of this video you will have a working directory with live listings."**

---

## SCENE 1 — Intro Promise (0:00 - 0:12)

**Duration:** 12 seconds
**Words:** ~35
**B-roll:** Split screen - left side shows a blank WordPress site (default theme, no content), right side shows the completed Listora directory with search bar, category grid, and listing cards. A 3-second animated "before / after" reveal wipe connects them.
**Transition:** Cut to screen-record at 0:12

**On screen:** The before/after split with "WB Listora - Installation & Setup" title card in ocean blue.

**Voiceover:**
> In the next five minutes you will install WB Listora, run through the 6-step setup wizard, and submit your first listing through the frontend form. By the end of this video your directory is live and ready for vendors. Let's go.

**Pacing note:** Short and fast. No warm-up. The viewer is here to follow instructions, not watch an introduction.

---

## SCENE 2 — Download from wblistora.com (0:12 - 0:45)

**Duration:** 33 seconds
**Words:** ~80
**B-roll:** Browser navigating to wblistora.com - the pricing page, then clicking Download (Free) or the purchase flow for Pro. The download completes and `wb-listora.zip` appears in the Finder downloads folder.
**Transition:** Cut to WordPress admin at 0:45

**On screen:** wblistora.com pricing/download page. When the zip lands in Finder, zoom in briefly on the filename to confirm it.

**Reference screenshot:** `installation-admin-page.png` (use at 0:40 as a preview of where we are going)

**Voiceover:**
> WB Listora is a private plugin distributed at wblistora.com - you will not find it on the WordPress plugin directory. Go to wblistora.com, sign into your account, and download the zip file from your purchases page. If you are starting with Free, download is immediate. Keep the zip file as-is - do not unzip it. WordPress uploads the zip directly. While that downloads, log into your WordPress site's wp-admin.

**Pacing note:** Emphasize "do not unzip it" - new users make this mistake. Brief pause before that sentence.

---

## SCENE 3 — Plugin Installation and Activation (0:45 - 1:20)

**Duration:** 35 seconds
**Words:** ~90
**B-roll:** WordPress admin - Plugins > Add New > Upload Plugin. Click "Choose File", select `wb-listora.zip`. Click Install Now. Progress bar runs. "Plugin installed successfully" screen appears. Click Activate Plugin. The Listora admin menu appears in the sidebar. A "Welcome to WB Listora" onboarding notice appears at the top of the screen.
**Transition:** Cut to the Setup Wizard at 1:20

**On screen (text overlays):**
- At 0:47: "Plugins > Add New > Upload Plugin"
- At 1:05: "Install Now - no unzipping needed"
- At 1:14: "Activate Plugin"

**Reference screenshots:** `installation-admin-page.png`, `setup-wizard-step1.png` (preview at 1:18)

**Voiceover:**
> In wp-admin, go to Plugins, then Add New, then Upload Plugin at the top of the page. Click Choose File and select the zip you just downloaded. Click Install Now - WordPress handles everything from here. When it finishes, click Activate Plugin. You will see the Listora menu appear in your sidebar and a notice inviting you to run the setup wizard. Click that notice or navigate to Listora > Setup Wizard.

---

## SCENE 4 — Setup Wizard Step 1: Listing Type (1:20 - 1:55)

**Duration:** 35 seconds
**Words:** ~85
**B-roll:** Setup wizard loads at Step 1. The wizard header shows "Step 1 of 6 - Listing Type". The screen displays a grid of listing type icons: Restaurant, Hotel, Real Estate, Job Board, Place, Classified, Education, Healthcare, General. The presenter clicks "Restaurant". The type selection card highlights. Click Next Step button.
**Transition:** Cut to Step 2 at 1:55

**On screen:**
- Wizard step indicator at top: steps 1-6 visible, step 1 highlighted
- At 1:22: overlay "Step 1 - Choose Your Listing Type"

**Reference screenshot:** `setup-wizard-step1.png`, `listing-types.png`

**Voiceover:**
> Step 1 of the 6-step wizard asks what kind of directory you are building. This determines which custom fields, demo content, and schema types WB Listora configures for you. The 9 demo packs cover restaurant, hotel, real estate, job board, place, classified, education, healthcare, and general directories. You can add more listing types later from the admin. For this tutorial, select Restaurant and click Next Step.

---

## SCENE 5 — Setup Wizard Step 2: Location Settings (1:55 - 2:20)

**Duration:** 25 seconds
**Words:** ~65
**B-roll:** Step 2 screen. Fields for default country, default city, distance unit (miles / km). The presenter selects United States, types "New York", selects miles, clicks Next Step.
**Transition:** Cut to Step 3 at 2:20

**On screen:**
- Wizard step indicator: step 2 highlighted
- At 1:57: overlay "Step 2 - Location Settings"

**Reference screenshot:** `locations-admin.png` (preview of the full location taxonomy)

**Voiceover:**
> Step 2 sets your location defaults. Choose your default country, city, and whether you want distances shown in miles or kilometers. These defaults apply to geo-search and the Near Me feature. You can have multiple locations in your directory - this is just the starting point. Fill in your settings and click Next Step.

---

## SCENE 6 — Setup Wizard Step 3: Maps (2:20 - 2:50)

**Duration:** 30 seconds
**Words:** ~75
**B-roll:** Step 3 screen. Toggle between "OpenStreetMap (free)" and "Google Maps". When Google Maps is selected, a Google Maps API key input field appears. The presenter selects OpenStreetMap and clicks Next Step. A small map preview updates at the bottom of the screen.
**Transition:** Cut to Step 4 at 2:50

**On screen:**
- Wizard step indicator: step 3 highlighted
- At 2:22: overlay "Step 3 - Map Provider"

**Reference screenshots:** `settings-map.png`, `google-maps.png`

**Voiceover:**
> Step 3 is your map provider. WB Listora ships with OpenStreetMap via Leaflet at no cost - zero API fees, no account required. If you need satellite view, the Places Autocomplete API for address lookup, or the clustering features, you can enter a Google Maps API key and switch to Google Maps. For this tutorial, OpenStreetMap is selected. Click Next Step.

**Pacing note:** This is a genuine decision point for the viewer. Slow down on "OpenStreetMap via Leaflet at no cost" and "Google Maps API key" - two separate paths.

---

## SCENE 7 — Setup Wizard Step 4: Pages (2:50 - 3:15)

**Duration:** 25 seconds
**Words:** ~65
**B-roll:** Step 4 screen showing a checklist of pages to auto-create: Directory, Add Listing, My Listings. All three checked by default. A preview shows the URL slugs (`/directory/`, `/add-listing/`, `/my-listings/`). The presenter clicks Next Step.
**Transition:** Cut to Step 5 at 3:15

**On screen:**
- Wizard step indicator: step 4 highlighted
- At 2:52: overlay "Step 4 - Pages"

**Voiceover:**
> Step 4 creates the WordPress pages your directory needs. WB Listora auto-creates the Directory page, the Add Listing page for frontend submission, and the My Listings dashboard for vendors. The blocks are pre-inserted - you do not have to edit any page templates yourself. Leave all three checked and click Next Step.

---

## SCENE 8 — Setup Wizard Step 5: Demo Content (3:15 - 3:40)

**Duration:** 25 seconds
**Words:** ~65
**B-roll:** Step 5 screen. A grid of demo pack options with "Restaurant" pre-selected (from Step 1 choice). A count badge reads "128 listings". Preview thumbnail of what the demo directory looks like. The presenter clicks Load Demo Content, and a progress indicator runs briefly.
**Transition:** Cut to Step 6 at 3:40

**On screen:**
- Wizard step indicator: step 5 highlighted
- At 3:17: overlay "Step 5 - Demo Content"

**Reference screenshots:** `home-frontend.png` (demo preview), `directory.png`

**Voiceover:**
> Step 5 loads demo content. Based on the listing type you chose in Step 1, WB Listora seeds real-looking listings - business names, addresses, photos, reviews, hours, and locations - so you have something to show clients or test against from day one. The demo pack runs via WP-CLI under the hood so it never times out. Click Load Demo Content and wait for the progress indicator.

---

## SCENE 9 — Setup Wizard Step 6: Done (3:40 - 3:55)

**Duration:** 15 seconds
**Words:** ~40
**B-roll:** Step 6 "You're all set" completion screen. Checkmarks next to each completed step. Three CTA buttons: "View Your Directory", "Add Your First Listing", "Go to Settings". The presenter clicks View Your Directory.
**Transition:** Cut to the live directory frontend at 3:55

**On screen:**
- Wizard step indicator: step 6 highlighted, all steps complete
- At 3:42: overlay "Step 6 - Done"

**Reference screenshot:** `listora-dashboard.png` (completion state)

**Voiceover:**
> Step 6 is the completion summary. All 6 steps checked. Your directory is live. Click View Your Directory to see the result.

---

## SCENE 10 — First Listing via Frontend Submission (3:55 - 4:40)

**Duration:** 45 seconds
**Words:** ~125
**B-roll:** Visitor view of the directory. Click Add Listing in the navigation. The 4-step frontend submission wizard loads: Basics (name, category, type), Details (address with draggable map pin, hours, phone), Media (logo upload, gallery), Contact (email, website, social links). The presenter fills in a restaurant name, drags the map pin to a location, uploads one photo, and clicks Submit. A toast notification appears: "Listing submitted - pending review."
**Transition:** Cut to admin moderation queue at 4:38

**On screen (text overlays):**
- At 3:57: "Frontend submission - no wp-admin needed"
- At 4:05: "Drag the pin to your exact location"
- At 4:20: "Gallery upload with auto-thumbnails"
- At 4:32: "Submit - goes to moderation queue or auto-publishes"

**Reference screenshots:** `frontend-submission.png`, `listing-lifecycle-dashboard.png`, `moderation-queue.png`

**Voiceover:**
> Click Add Listing and you get the frontend submission wizard. Multi-step, auto-saves drafts at every step, so vendors never lose work. Fill in the basics - business name, category, listing type. Details step has the draggable map pin - drag it to the exact storefront location. Add business hours, phone, website. Media step handles the logo and gallery - photos get thumbnails generated automatically. Contact step takes the email address that leads go to. Submit. Depending on your settings, the listing goes live immediately or waits for your approval in the moderation queue.

---

## SCENE 11 — Listing Live in the Directory (4:40 - 4:55)

**Duration:** 15 seconds
**Words:** ~45
**B-roll:** Admin approves the listing with one click from the moderation queue. Smooth cut to the frontend - the new restaurant listing card appears in the directory grid. Click into it - the full detail page loads with gallery, map, hours, and the contact form.
**Transition:** Slow dissolve to end card at 4:55

**On screen (text overlay):**
- At 4:42: "Approve from wp-admin or set auto-publish"

**Reference screenshots:** `moderation-queue.png`, `business-claims.png`, `contact-form-listing.png`

**Voiceover:**
> Approve the listing from the moderation queue, or set listings to auto-publish in Settings. Either way, the listing card appears in your directory, the detail page is live, and the vendor gets an email confirmation.

---

## SCENE 12 — What to Do Next (4:55 - 5:05)

**Duration:** 10 seconds
**Words:** ~30
**B-roll:** End card with WB Listora logo. Three CTA buttons appear in sequence: "Add Pro", "Read the Docs", "Watch Feature Videos". URL: wblistora.com.
**Transition:** Fade to black

**On screen:** Logo lockup + wblistora.com + 3 CTA links

**Voiceover:**
> That is your directory live in under five minutes. Next: add WB Listora Pro for pricing plans and monetization, browse the full documentation at wblistora.com, or watch the per-feature deep-dives linked below.

---

## Production Notes

**Music:** Light, instructional, background-only. BPM 95-110. No dramatic builds - the viewer is following steps. Epidemic Sound "Ambient" or "Focus" categories. Keep at 15% under voiceover throughout.

**Voice:** Same artist as the product overview tour for brand consistency. Conversational, not robotic. The tone is "knowledgeable colleague doing a screen-share" not "instructional video narrator."

**On-screen text overlays:** Keep all step numbers consistent with the wizard UI. If the wizard changes, update overlays to match - no version drift.

**Captions:** Burned in. Every voiceover line captioned. This is a tutorial - deaf users especially rely on captions to follow the steps.

**Chapters (YouTube):** Upload with chapter markers at each scene transition so viewers can skip to the step they need.

| Chapter | Timestamp |
|---|---|
| Introduction | 0:00 |
| Download from wblistora.com | 0:12 |
| Install and Activate | 0:45 |
| Step 1 - Listing Type | 1:20 |
| Step 2 - Location | 1:55 |
| Step 3 - Maps | 2:20 |
| Step 4 - Pages | 2:50 |
| Step 5 - Demo Content | 3:15 |
| Step 6 - Done | 3:40 |
| Submit Your First Listing | 3:55 |
| Your Listing is Live | 4:40 |
| What to Do Next | 4:55 |

---

## Asset Checklist

| Scene | Screenshot required |
|---|---|
| 2 | `installation-admin-page.png` |
| 3 | `installation-admin-page.png`, `setup-wizard-step1.png` |
| 4 | `setup-wizard-step1.png`, `listing-types.png` |
| 5 | `locations-admin.png` |
| 6 | `settings-map.png`, `google-maps.png` |
| 8 | `home-frontend.png`, `directory.png` |
| 9 | `listora-dashboard.png` |
| 10 | `frontend-submission.png`, `listing-lifecycle-dashboard.png`, `moderation-queue.png` |
| 11 | `moderation-queue.png`, `business-claims.png`, `contact-form-listing.png` |

---

## Related

- [Product Overview Tour](01-product-overview.md) - 2-minute pitch for prospective buyers.
- [Feature Deep-Dives](03-feature-demos.md) - 60-second per-feature clips for after setup.
- [Shot List Template](shot-list-template.md) - equipment, mic, and color-grade reference.
