# 31 — Verification Badges (Pro)

## Scope: Pro Only

---

## Overview

Verified badges build trust. Like Twitter's blue check or Yelp's verified badge — they signal that a listing owner's identity has been confirmed.

---

## Badge Types

| Badge | Visual | Meaning |
|-------|--------|---------|
| Claimed | ✓ Claimed | Owner has claimed the listing |
| Verified | ✓ Verified | Admin has verified owner's identity |
| Featured | ⭐ Featured | Paid premium placement |

---

## Verification Flow

1. Listing owner claims listing (see `20-claim-listing.md`)
2. Admin reviews claim documents
3. Admin approves → "Claimed" badge appears
4. Admin additionally marks as "Verified" → stronger badge appears
5. Verification stored as `_listora_verified = 1` post meta

---

## Display

**On card:**
```
Pizza Palace ✓
```

**On detail:**
```
Pizza Palace
✓ Verified Business · Owner since March 2026
```

**In search index:** `is_verified = 1` allows filtering to verified-only listings.

---

## Admin UI

In listing edit screen sidebar:
```
Verification Status:
☐ Claimed
☐ Verified
Notes: [ Verified via business license #12345 ]
```
