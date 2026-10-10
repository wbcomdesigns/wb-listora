---
journey: gallery-follows-server-limit
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [10360671784]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
---

# Gallery uploads follow the server limit and keep the photos that fit

A 5 MB Listora cap was checked after upload, and one oversized photo dropped the whole pick (uploaded files stayed as orphans, a toast vanished, no progress). Owner decision: only the server's limit applies.

## Steps

### 1. Mixed pick on a 10 MB server
- **Action**: mu-plugin `upload_size_limit` -> 10 MB. As a subscriber on Add Listing at 390, Add Photos with a 370 KB, an 8 MB and a 27 MB JPEG together.
- **Expect**: "Uploading photo 1 of 2..." then 2 thumbnails; exactly 2 `POST /wp/v2/media`; a red inline message under the gallery naming the 27 MB file and "10 MB", still visible after scrolling.

### 2. Featured image
- **Action**: pick the 27 MB file for Featured Image.
- **Expect**: same inline refusal, no upload request; the hint reads "Up to 10 MB, JPG/PNG/WebP".

### 3. Settings
- **Action**: Settings -> Submissions.
- **Expect**: Max file size shows the server limit read-only, no input.

## Pass criteria

ALL of the following hold:
1. Files under the server limit upload, including 5-10 MB phone photos.
2. Oversized files are refused before upload, by name, inline.
3. No orphan attachments from refused files.
