# Shot List Template — Production Reference

The canonical production reference for all WB Listora video content. Use this alongside any script in this directory. Every video that goes through production should be checked against each section of this document before the shoot and before final export.

---

## Equipment List

### Camera

**Primary (screen-record):**
- Recording software: Loom, CleanMyMac Screen Recorder, or OBS. Resolution: 1920x1080 minimum. Frame rate: 60fps for smooth scroll and animation. Do not use 30fps for screen-recording - mouse movement stutters.
- Cursor: Use a large, high-contrast cursor highlight plugin (e.g., Mouseposé on macOS) for all UI walkthroughs. Viewers on mobile need to see where you are clicking.
- Display: Retina/HiDPI display. Record at 1920x1080 (not at the native 2x resolution - export at 1x for clean 1920x1080 master).

**Talking-head / B-roll camera (if used):**
- Minimum: Sony ZV-E10, Canon M50, or equivalent APS-C. 1080p60 minimum.
- Frame: Medium close-up (chest to top of head). Neutral background - solid white or light gray, or a very slightly blurred office/studio environment.
- Lighting: See Lighting section below.

### Microphone

**Primary:**
- Shure SM7dB, Rode PodMic, or Electro-Voice RE20 into an audio interface (Focusrite Scarlett Solo or similar).
- Record at 48kHz / 24-bit WAV. Do not record compressed formats (MP3, AAC) for the VO track.
- USB fallback: Blue Yeti or Rode NT-USB - acceptable for tutorial content, not for paid ads.

**Voiceover direction:**
- Mouth-to-mic distance: 6-8 inches. Pop filter required.
- Room: A treated room or a closet lined with soft materials. Hard parallel walls cause reflections.
- Take direction: "Knowledgeable colleague on a screen-share, not a TV presenter." Record 2-3 takes per scene. Keep the one that sounds most natural - not the most technically perfect.
- Pace target: Product overview = 155-165 words/min. Tutorial = 145-155 words/min. Ads = 155-170 words/min (faster is fine for 15s ads).

### Lighting (talking-head / b-roll only)

**Three-point setup:**
- Key light: 60W LED softbox, camera-left, 45-degree angle. Use 5600K daylight if possible.
- Fill light: 30W LED panel or a reflector on the camera-right side. Ratio approximately 2:1 (key is twice as bright as fill).
- Back/hair light: Small LED behind the subject to separate from the background.
- No overhead fluorescents. They cause green color casts and harsh shadows.

**For screen-record-only videos:** No lighting setup needed. Ensure the display is not reflecting glare if a camera shows the screen at an angle.

---

## Voiceover Specs

### Pacing by video type

| Video type | Words/min | Notes |
|---|---|---|
| Product overview (2-min tour) | 155-165 | Varies by scene - hook scene 145, main scenes 160-165 |
| Installation tutorial (5-min) | 145-155 | Slower - viewer is following along |
| Feature demo clips (60s) | 155-165 | Keep tight so the body fits before the CTA |
| 15-second ads | 165-175 | Fast but clear. Every word earns its place. |
| 30-second ads | 155-165 | Similar to feature demos |
| 60-second ads | 150-160 | Room for one deeper breath per section |

### Voice character

The voice reads as a knowledgeable colleague doing a screen-share - not a marketing presenter, not a documentary narrator. Specific guidance to give to a VO artist:

- Conversational register, not broadcast register
- Upward inflections only on actual questions - not on statements (this makes claims sound like suggestions)
- Natural breathing rhythm - do not clip breath sounds, they are normal in a conversational read
- When naming technical items (REST endpoints, hooks, WP-CLI commands) - pronounce them as if explaining to a smart non-expert, not as if reading a spec sheet
- Emphasis: use a slight volume rise, not a pitch rise, on the key benefit word in each sentence

### Take direction for studio sessions

Brief the VO artist with this before recording:

> "Imagine you are doing a demo screen-share with a potential client who is evaluating this plugin. You know it well. You are not selling - you are showing. You want them to leave the call knowing whether this is right for them. Read it like that, not like a commercial."

---

## Caption Rendering Specs

**Font:** Inter Bold, or system-sans fallback. Never a serif or script font for captions.

**Size at 1920x1080 master:**
- Font size: 28px
- Line height: 1.4
- Max characters per line: 42 (hard limit - anything over wraps awkwardly on 390px mobile)
- Max lines at any moment: 2

**Color treatment:**
- Text color: White (#FFFFFF)
- Outline: 2px solid black (#000000)
- No caption background box unless the video has a white scene - then add a semi-transparent dark bar

**Position:** Bottom center. Bottom edge of the caption should be 8% from the bottom of the frame. This avoids overlap with platform UI chrome (YouTube progress bar, Instagram bottom nav).

**Timing:** Captions are timed to the word, not to the sentence. If a sentence takes 3 seconds, do not show all words at once - show them as the VO speaks them. This is what makes burned-in captions readable at silent autoplay.

**9:16 re-cut captions:** Adjust font to 32px (slightly larger for smaller mobile screens). Same max-chars rule applies.

**Tools:** CapCut, Premiere Pro auto-caption with manual correction, or Descript. Do not publish auto-generated captions without a manual review pass - technical terms (Haversine, Interactivity API, wblistora.com) will be mis-captioned.

---

## Music Selection Guide

### Brand mood reference

WB Listora's brand voice is confident, direct, and collegial. Music should reinforce this - optimistic without being hype-y, focused without being corporate drone.

**Always avoid:** Tropical house drops, aggressive EDM, "inspirational corporate" cliché piano + strings, anything with a discernible lyric.

**Always prefer:** Mid-tempo tracks with a clear rhythmic pulse that does not fight the VO. Music is felt in the background, not heard over the VO.

### BPM and mood guide by scene type

| Scene type | BPM range | Mood | Epidemic Sound library suggestion |
|---|---|---|---|
| Hook / opening | 120-135 | Alert, forward-moving | "Confident", "Forward Motion" |
| Product walkthrough / demo | 110-125 | Focused, productive | "Productive", "Inspiring" |
| Technical feature details | 100-115 | Analytical, precise | "Corporate", "Focus" |
| Monetization / pricing | 115-130 | Business-confident | "Corporate Success" |
| CTA / end card | 120-135 | Uplifting (slight swell) | Match the opening track |
| Tutorial / instructional | 95-110 | Calm, instructional | "Ambient", "Calm" |
| 15-second ad | 130-145 | Punchy, immediate | "Upbeat", "Energetic" |

**Volume mixing guide:**
- Under VO: -18 to -20 dB relative to VO (approximately 15-20% of full volume)
- At end card (no VO): -10 dB (let the music breathe for 2-3 seconds)
- Fade in: 0.5 second fade at the top of each video
- Fade out: 1 second fade at the end (not abrupt cut)

### Royalty-free library options

| Library | Notes |
|---|---|
| Epidemic Sound | Preferred. YouTube monetization safe. Search by BPM and mood. Subscribe per channel. |
| Artlist | Good catalog. Annual subscription covers unlimited use. |
| Musicbed | Premium tracks. Slightly more distinctive but higher cost. |
| Free Music Archive | Free for commercial use (verify each track's license). Inconsistent quality. |

Do not use YouTube Audio Library tracks in paid social ad placements without verifying the ad-use license for each track individually.

---

## Color Treatment

### Brand palette

**Primary:** Ocean Blue - reference `#0077B6`. Use this for overlay bars, text overlays on light backgrounds, end card background, button highlights in screen-records.

**Accent:** Coral - reference `#FF6B6B`. Use for CTA text on the end card, highlight overlays on key feature moments (not more than once per clip).

**Neutral text:** White `#FFFFFF` on dark backgrounds. Near-black `#1A1A2E` on light backgrounds.

**Background (end card):** Ocean blue `#0077B6` or a very dark navy `#0A1628`. Avoid pure black - it reads as low-budget.

### Color grade reference

Apply a light film LUT to all screen-record footage:

- Lift shadows slightly (+5 to +10 on lift) so UI elements read clearly on mobile at small size
- Reduce highlights slightly (-5 to -10) to prevent the white WordPress admin background from blowing out
- Add a very subtle warm tint to talking-head footage if used (skin tones should not be desaturated)
- Do not add heavy color grading to screen-records - it makes the UI look incorrect to viewers who use the same UI

**On-screen text overlay bars:** Semi-transparent ocean blue bar at 75% opacity. White Inter Bold text. Used for feature labels and step indicators as described in individual scripts.

### Consistency rule

Every video in this series should look like it came from the same production. Run the same LUT and the same brand color overlays on every clip before export. Inconsistency across the feature demo series signals a low-trust brand.

---

## Aspect-Ratio Re-cut Guide

### Master format

Produce every video at **16:9, 1920x1080, 60fps** as the master. All derivatives come from this master.

### Derivative formats

| Format | Ratio | Resolution | Notes |
|---|---|---|---|
| YouTube (standard) | 16:9 | 1920x1080 | Upload the master directly |
| Landing page embed | 16:9 | 1920x1080 | Same as master |
| YouTube Shorts | 9:16 | 1080x1920 | Crop center of the 16:9 frame. Add top/bottom bleed in brand blue if the main action is in the center third. |
| Instagram Reels | 9:16 | 1080x1920 | Same as Shorts cut |
| TikTok | 9:16 | 1080x1920 | Same as Shorts cut |
| LinkedIn feed | 1:1 | 1080x1080 | Letterbox the 16:9 master with brand blue bars top and bottom. Add WB Listora logo badge in the top-right corner. |
| Twitter/X video | 16:9 or 1:1 | Either | Use the master for Twitter - it plays in 16:9 by default |
| Meta feed ads | 1:1 or 9:16 | Both | Produce both - Meta serves different ratios by placement |

### Cropping priorities for 9:16 re-cut

When cropping the center of a 16:9 frame to 9:16:
- Center the crop on the active UI element being demonstrated (the search bar, the form field, the admin panel)
- If a voiceover slide or graphic fills the full frame, the center crop is fine
- Add the brand blue top/bottom bleed areas for any scene where important content would be cropped
- Never crop out text overlay labels - reposition them if they land in the crop zone

---

## Asset Checklist

Before any video goes to final export, verify every referenced screenshot from the image library is included in the edit. Below is the complete set used across the five videos in this series.

| Image file | Used in |
|---|---|
| `home-frontend.png` | Overview S2, Installation S8, Ads 30s-A, 60s-A |
| `setup-wizard-step1.png` | Overview S2, Installation S3-S4, Ads 15s-C, 30s-B |
| `search-and-filters.png` | Overview S3, Feature #1, Ads 60s-A |
| `multi-criteria-reviews.png` | Overview S3, Feature #3, Ads 30s-D |
| `compare-listings.png` | Overview S3, Feature #6 |
| `comparison.png` | Feature #6 |
| `business-claims.png` | Overview S3, Installation S11 |
| `listing-lifecycle-dashboard.png` | Overview S4, Feature #2, Installation S10 |
| `user-dashboard.png` | Overview S4, Ads 15s-B, 30s-E |
| `saved-searches.png` | Overview S4 |
| `pricing-plans-admin.png` | Overview S5, Feature #4, Ads 15s-D, 30s-A, 60s-C |
| `credits-and-plans.png` | Overview S5, Feature #4, Ads 60s-C |
| `buy-credits-page.png` | Overview S5, Feature #4, Ads 15s-D, 60s-C |
| `transactions.png` | Overview S5, Feature #4 + #13, Ads 15s-D, 60s-C |
| `coupons.png` | Overview S5, Ads 60s-C |
| `needs-marketplace.png` | Overview S6, Feature #7, Ads 30s-A, 30s-E, 60s-A |
| `installation-admin-page.png` | Installation S2-S3 |
| `listing-types.png` | Installation S4 |
| `locations-admin.png` | Installation S5 |
| `settings-map.png` | Installation S6 |
| `google-maps.png` | Installation S6 |
| `directory.png` | Installation S8 |
| `listora-dashboard.png` | Installation S9 |
| `frontend-submission.png` | Installation S10, Feature #2 |
| `moderation-queue.png` | Installation S10-S11, Feature #8 |
| `contact-form-listing.png` | Installation S11 |
| `advanced-search.png` | Feature #1 |
| `duplicate-check-step.png` | Feature #2 |
| `photo-reviews.png` | Feature #3, Ads 30s-D |
| `reviews-system.png` | Feature #3, Ads 30s-D |
| `lead-forms.png` | Feature #5 |
| `analytics.png` | Feature #5 + #10, Ads 60s-A, 60s-C |
| `moderators.png` | Feature #8 |
| `audit-log-admin.png` | Feature #8 + #10 + #13 |
| `verification-badges.png` | Feature #9 |
| `white-label.png` | Feature #11, Ads 30s-B, 60s-B |
| `coming-soon.png` | Feature #12 |
| `spam-protection-settings.png` | Feature #12, Ads 15s-E |
| `rate-limiting-settings.png` | Ads 15s-E |
| `blocks-overview.png` | Ads 30s-C, 60s-B |
| `directory-page-blocks.png` | Ads 30s-C |

---

## QA Checklist Before Publish

Run through this list for every video before uploading to any channel.

### Audio
- [ ] Voiceover is audible and clear at 50% system volume on headphones
- [ ] No clipping, no room echo, no mouth noise distracting from VO
- [ ] Music is behind the VO, not competing with it
- [ ] Music fades in and fades out (no abrupt start/stop)
- [ ] Audio exported at 48kHz / 16-bit minimum

### Video
- [ ] Resolution is 1920x1080 at minimum for the master (no upscaling from lower resolution)
- [ ] Frame rate consistent throughout (no dropped frames visible in fast-scroll scenes)
- [ ] Color treatment matches the brand palette
- [ ] Cursor is visible and tracked clearly in all UI demos
- [ ] No browser bookmarks bar, personal email tabs, or system notifications visible in screen-records

### Captions
- [ ] Every spoken word is captioned
- [ ] Max 42 characters per caption line
- [ ] No caption overlaps with UI elements being demonstrated
- [ ] Technical terms spelled correctly (wblistora.com, Interactivity API, Haversine, Hold-and-Commit)
- [ ] Captions are bottom-centered, not covering the focal action area

### Content accuracy
- [ ] No mention of Razorpay
- [ ] No mention of EDD or Easy Digital Downloads
- [ ] Hook count: if mentioned, it is 226 (120 actions + 106 filters), never 199
- [ ] Payment integrations: if mentioned by count, it is 7, never 6
- [ ] WordPress requirement: if mentioned, it is 6.9+, never 6.7+
- [ ] Setup wizard: if mentioned by step count, it is 6 steps, never 10
- [ ] No "auto-renew" language - renewal is manual with a 7-day email reminder
- [ ] No specific latency claims (~50ms) - use "scales to 100K listings" instead
- [ ] No install counts from wordpress.org - WB Listora is not listed there
- [ ] All CTAs point to wblistora.com

### Branding
- [ ] Every video ends with the WB Listora logo lockup
- [ ] End card holds for at least 3 seconds
- [ ] wblistora.com is visible and legible on the end card
- [ ] No competitor logos or names visible in any screen-record

### Platform-specific
- [ ] Thumbnail designed and uploaded (do not rely on auto-generated thumbnails)
- [ ] YouTube: chapters added for videos over 2 minutes
- [ ] YouTube: description includes wblistora.com link in the first 2 lines
- [ ] Instagram / TikTok: first-frame hook text is burned in (visible before unmute)
- [ ] LinkedIn: square 1:1 version ready for the feed post

---

## Related

- [Product Overview Tour](01-product-overview.md)
- [Installation & Setup Tutorial](02-installation-setup.md)
- [Feature Deep-Dives](03-feature-demos.md)
- [Short Ads](short-ads.md)
