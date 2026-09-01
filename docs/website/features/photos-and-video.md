# Photos & Video

> **Availability:** Free + Pro. The gallery, the carousel, drag-and-drop upload and video embedding are all **Free**. Pro adds [Photo Reviews](photo-reviews.md), where reviewers attach their own images.

Each listing carries a featured image, a gallery, and optionally a video. Visitors browse the gallery as a carousel; members build it from the submission form or their dashboard, by picking from the media library or by dragging a file onto the page.

## What it is

### The carousel

A listing's photos render as a carousel with arrows, dots and a thumbnail strip. Every image is reachable without scrolling back up to the thumbnails, which is what a long gallery used to require.

All three controls - arrows, dots, thumbnails - are driven by a single handler, so they cannot disagree about which image is showing. Clicking the fourth thumbnail moves the dots to the fourth position and the arrows continue from there.

### Drag-and-drop upload

The featured-image box accepts a dragged file. Its label has always invited this; before 1.6.0 the drop did nothing.

A dropped file and a file chosen through the media modal take exactly the same path - both upload through WordPress's own media endpoint and share the same commit step - so they land in identical state and obey the same size limit. There is no second-class route.

### Video

Paste a video URL into a listing's video field and it renders on the listing page. Previously the URL was stored and never shown.

Embedding goes through WordPress's own oEmbed layer, so **every provider WordPress supports works** - YouTube, Vimeo, Dailymotion, TED, and the rest. There is no per-provider list in Listora to fall behind, and a URL WordPress cannot embed is simply not rendered rather than printed raw.

## How you use it

### As a member - add photos to a listing

From the submission form or **Dashboard > Listings > Edit**:

1. Set the featured image - click to open the media library, or drag a file onto the box.
2. Add gallery images. The cap is set by the site owner, 20 by default.
3. Optionally paste a video URL.

### As a site owner - set the gallery limit

**Listora > Settings > Submission > Max gallery images.** Accepts 1 to 100; the default is 20.

The limit is enforced **on the server as well as in the browser**, so it holds for submissions that come in through the API or a native app, not just for people using the form.

## Settings & options

| Setting | Where | Default |
|---|---|---|
| Max gallery images | Settings > Submission | 20 |

Video has no setting - the field is part of the listing form, and what a site can embed is whatever WordPress can embed.

## Good to know

- **The size ceiling is your server's**, not Listora's. WordPress reports the effective upload limit from PHP configuration, and the form validates against it before starting an upload, so a member gets told the file is too large instead of watching a request fail.
- **Uploading requires the same capability as the media library.** A member who cannot upload media in WordPress cannot upload here either; the endpoint is the same one.
- **A listing with no images is a valid listing.** The carousel is absent rather than empty, and cards fall back to whatever the theme uses for an image-less listing.

## Related

- [Frontend Submission](frontend-submission.md) - the form these fields live on
- [Photo Reviews](photo-reviews.md) - Pro, images attached to reviews
- [User Dashboard](user-dashboard.md) - where a member edits an existing listing
- [Listing Lifecycle](listing-lifecycle.md) - what happens after submission
