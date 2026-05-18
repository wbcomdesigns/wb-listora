# QA Flows — WB Listora (Free)

Human-runnable checklist. Run the full list before every release. **Not** a Playwright script — open the site, click through, eyeball each state.

## How to use

For every flow below:

- [ ] Happy path works
- [ ] Empty state renders (no data)
- [ ] Loading state visible (spinner / skeleton)
- [ ] Error state shows user-friendly message (server 500, validation fail)
- [ ] Success feedback visible (toast / inline confirmation)
- [ ] Mobile 390px: layout, tap targets ≥44px, no horizontal scroll
- [ ] Keyboard: Tab through, Enter/Space activate, Esc closes modals
- [ ] Screen reader: interactive elements announce their purpose

Mark the release version + date when a flow is verified:

```
✅ v1.0.0 / 2026-04-17 — VD
```

---

## Admin flows

### A1. Setup wizard (first-activation)

1. Deactivate + delete plugin data, reactivate
2. Gets redirected to `admin.php?page=listora-setup`
3. Step 1: pick listing type → saves selection
4. Step 2: set default map location
5. Step 3: create demo content (optional)
6. Step 4: create pages (directory, submission, dashboard) — verify pages exist after
7. Dismiss → onboarding notice on dashboard

### A2. Listings CPT — create / edit / trash / quick-edit

1. Admin → Listings → Add New
2. Fill title, description, type, category, location, media
3. Publish → verify on frontend at `/listora/{slug}/`
4. Edit → change category → save → re-check frontend
5. Quick Edit row action — change status → verify
6. Bulk trash 3 listings → verify gone from search index
7. Restore from Trash → verify returns to index

### A3. Taxonomies

1. Categories: add with icon (Lucide picker), image, color → save → verify on frontend card
2. Locations: add hierarchical — Country → State → City
3. Features: add amenity (WiFi, Parking) → verify on detail page
4. Listing Types editor: duplicate an existing type → rename → customize fields → save → verify

### A4. Reviews moderation

1. Submit a review as a logged-in user (frontend)
2. Admin → Listora → Reviews — verify it appears as "pending"
3. Inline reply (REST) → verify saves
4. Approve → verify appears on listing detail
5. Report review → flag → delete
6. Filter: by status, by listing, by date

### A5. Claims moderation

1. Submit a claim with documents (frontend)
2. Admin → Listora → Claims — verify in queue
3. Approve → verify listing is re-assigned to claimant user
4. Reject → verify claimant sees rejection in dashboard

### A6. Settings (9 tabs)

For each tab, make a change → save → reload → verify persisted:

- General (per-page, slug, currency, date format)
- Maps (provider, default coords, clustering)
- Submissions (guest toggle, reCAPTCHA, terms page)
- Reviews (auto-approve, min length, one-per-listing)
- Advanced (cache clear, debug mode)
- Import/Export (download JSON, re-upload)
- Migration (run competitor importer dry-run)
- *(Pro placeholder tabs visible but disabled)*

### A7. Admin columns + filters

1. Listings list → type, location, rating, reviews columns render
2. Sort by rating → descending
3. Filter by type dropdown → list updates
4. Filter by location hierarchy → list updates
5. Status filter includes `listora_expired`, `listora_rejected`

### A8. Import / Export

1. Export listings as CSV → verify columns
2. Modify 5 rows, re-import → verify updates applied
3. Import GeoJSON → verify geo rows created
4. Run a competitor migrator (Directorist) dry-run → see report without writes

### A9. Onboarding checklist widget

1. Dashboard widget shows progress bar (listings, reviews, settings)
2. Click incomplete item → jumps to setting
3. Dismiss banner → verify persists via REST

---

## Frontend / user flows

### U1. Search + filters

1. Directory page with search block
2. Type keyword → debounced, results update without reload
3. Apply type filter → facet counts update
4. Apply location hierarchy → results narrow
5. "Near me" → browser geo prompt → results within radius
6. Clear all filters → results reset
7. Sort (featured, rating, newest, alpha)
8. URL reflects state → copy link → new session restores same filters

### U2. Grid / list / map views

1. Toggle grid ↔ list view — cards re-layout
2. Toggle map view — markers appear at listing geo
3. Click map marker → highlight card
4. Pan/zoom map → refetch listings in view
5. Infinite scroll (or pagination) loads next page

### U3. Listing detail

1. Click card → navigate to detail
2. Tabs: Overview / Reviews / Services / Map / Related / Owner
3. Gallery: swipe, keyboard arrows, thumbnails
4. Share button: copy link + social intents
5. "Feature this listing" — if owner, triggers paid upgrade flow
6. Claim button → modal → submit claim

### U4. Favorites

1. Click heart on card → requires login (show login modal if guest)
2. Logged in → favorite added → heart fills
3. Dashboard → Favorites tab → listing appears
4. Unfavorite → removed from list
5. Collection: create "Must-visit" → move favorite into it

### U5. Reviews

1. Detail page → Reviews tab → "Write a review" button
2. Rating stars (1–5) + text (min length enforced)
3. Submit → review appears in list (auto-approve if admin enabled)
4. Helpful vote on someone else's review → count increments
5. Edit own review → save → version updates
6. Owner reply appears inline under review

### U6. Claim a listing

1. Detail page → "Claim this listing"
2. Modal: name, email, phone, upload business license
3. Terms checkbox required
4. Submit → "Claim pending" badge on listing
5. Dashboard → Claims tab → see status pending

### U7. Submit new listing (multi-step)

1. Submission page
2. Step: Type selection (radio) — select Restaurant
3. Step: Details — type-driven fields render (cuisine, price range, etc.)
4. Step: Media upload — drag-reorder gallery, main image
5. Step: Preview → data summary
6. Step: Terms checkbox
7. Submit → redirect to dashboard → "Pending review"
8. Admin approves → frontend shows listing live

### U8. User dashboard

1. Visit `/my-account/` (or configured page)
2. Tab: My Listings — statuses (draft/pending/published/expired) with actions
3. Tab: My Reviews — edit, delete
4. Tab: Favorites — remove, open detail
5. Tab: Profile — name, bio, avatar upload, change password

### U9. Category / location browse

1. Click a category card on homepage
2. Directory page filtered to that category (URL includes taxonomy)
3. Breadcrumb shows path
4. Listing count matches taxonomy term count

### U10. Calendar (event listings)

1. Calendar block on directory page
2. Navigate months/weeks/days
3. Click event day → popover with listings on that date
4. Click event → detail page

### U11. Featured carousel

1. Homepage featured block
2. Auto-scrolls (if enabled)
3. Prev / Next buttons work
4. Click card → detail

---

## Regression passes

Run after every release AND after any commit that touches:

- Interactivity store (`src/interactivity/store.js`)
- Any REST controller (`includes/rest/*`)
- Search engine or indexer (`includes/search/*`)
- Settings saving (`includes/admin/class-settings-page.php`)
- Block rendering (`blocks/*/render.php`)

---

## Accessibility spot-checks

Pick 2 flows at random per release and run through:

- Tab through every interactive element — focus ring always visible
- Screen reader (VoiceOver on macOS / NVDA on Win): announcements match visible state
- Color contrast: all text ≥ 4.5:1 against background (use DevTools)
- Reduced motion: `prefers-reduced-motion` disables autoplay / parallax / long transitions

---

## Cross-browser smoke test

At minimum, the search + submission + detail flows:

- Chrome (current)
- Safari (current)
- Firefox (current)
- Mobile Safari (390px simulated)
- Mobile Chrome (390px simulated)

---

## Sign-off template

Copy to the PR description for every release:

```
QA pass — WB Listora vX.Y.Z — YYYY-MM-DD — <reviewer>

Admin:   A1 ✅ / A2 ✅ / A3 ✅ / A4 ✅ / A5 ✅ / A6 ✅ / A7 ✅ / A8 ✅ / A9 ✅
User:    U1 ✅ / U2 ✅ / U3 ✅ / U4 ✅ / U5 ✅ / U6 ✅ / U7 ✅ / U8 ✅ / U9 ✅ / U10 ✅ / U11 ✅
Mobile:  U1, U3, U7 at 390px ✅
A11y:    U3, U5 keyboard + screen reader ✅
Browser: Chrome / Safari / Firefox / Mobile Safari / Mobile Chrome ✅

Regressions: none found.
```
