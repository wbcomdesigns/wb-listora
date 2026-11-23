---
journey: listing-photos-stay-out-of-admin-library
group: submission
actor: member
mode: mixed
plugin: wb-listora
priority: high
roles: [admin, subscriber, customer]
covers: [member-media, listora-v1-media, upload_files]
prerequisites:
  - "A subscriber member, and a WooCommerce customer member with Customer ticked under Who can add listings"
  - "One image file (JPEG or PNG) and one non-image renamed to .jpg"
estimated_runtime_minutes: 10
last_verified: unrecorded
verified_against: free 2.1.2
viewports: [1440, 390]
themes: [reign, buddyx]
fails_on: unrecorded
site_profile: required
---

# Members' listing photos stay out of the admin's Media Library

## Why
Listora gave every listing submitter core `upload_files` so the wizard could upload. That opened the Media menu to members and filled the admin's Media Library and every media picker with members' photos. Career Board and MediaVerse never grant it; Listora now uploads through its own route like they do.

## Steps

### 1. Member uploads
As the subscriber open Add Listing, add a featured image and two gallery photos. Each upload is `POST /listora/v1/media` with status 201 and the previews show. Submit the listing. Edit it from the dashboard and add a service photo: also `POST /listora/v1/media`, 201.

### 2. Member has no library access
As the subscriber, `/wp-admin/upload.php` is refused and the admin bar shows no Media item. `POST /wp/v2/media` with a file returns 403.

### 3. Non-image refused
Upload the renamed non-image: the wizard shows "Please upload a JPEG, PNG, WebP or GIF image." and nothing is added.

### 4. Admin library is clean
As admin, Media > Library in grid and list view does not show the member's photos. Opening Set featured image on any post does not list them either.

### 5. Admin can still find them
On the listing's edit screen, the featured-image picker's "Uploaded to this listing" filter shows them. Media > Library (list view) > Listing member media shows all of them, and nothing the admin uploaded.

### 6. Customer role
Repeat step 1 as the customer. Same result.

## Pass criteria
Steps 1-6 hold at 1440 and 390. No PHP notice in debug.log.

## Negative case
Before 2.1.2 a subscriber had `upload_files`: step 2 loaded the Media Library and step 4 listed the member's photos.
