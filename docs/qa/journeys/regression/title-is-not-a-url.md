---
journey: title-is-not-a-url
plugin: wb-listora
priority: normal
roles: [member]
covers: [10213032167]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who can submit listings"
estimated_runtime_minutes: 4
---

# A web address is not a business name

Submission required only that the title was non-empty, so
`https://example.com/thing` was accepted and became the business name on the
card, the detail page, the browser tab and the permalink
(`/listing/https-example-com-thing/`).

That is what automated spam posting looks like: flood a directory with URL
titles to get links in front of visitors.

**The interesting half of this journey is what must still be ALLOWED.** The rule
is deliberately narrow, and the risk of a rule like this is not that it fails to
catch spam — it is that it starts refusing real business names.

## Steps

### 1. A title that IS a web address is refused

- **Action**: POST `/listora/v1/submit` with each title below.
- **Expect**: `400` with code `listora_title_is_url` and `field: title`.

  | Title |
  |---|
  | `https://example.com/thing` |
  | `http://example.com` |
  | `www.example.com` |
  | `HTTPS://EXAMPLE.COM/X` (case must not matter) |

### 2. Real names are NOT refused

- **Action**: POST the same endpoint with each of these.
- **Expect**: `201` every time.

  | Title | Why it must pass |
  |---|---|
  | `Booking.com` | A real company's real name |
  | `example.com` | A bare domain is indistinguishable from a brand |
  | `Best Pizza, see foo.com` | A name that mentions an address is still a name |
  | `W Hotels` | Ordinary |
  | `Cafe 24.7` | Dots in names are common |

- **On fail**: this is the direction that matters. Refusing `Booking.com` is a
  worse product than accepting the occasional bare domain, so if the rule is
  ever widened, widen it away from these.

### 3. Editing is not a second way in

- **Action**: create a listing with a normal title, then edit it to
  `https://example.com/x`.
- **Expect**: `400`, and the original title unchanged on the listing.
- **On fail**: a guard on create and not on update reads as fixed while the gap
  stays open.

## Notes

- **Whitespace is the discriminator.** A title containing any whitespace is
  treated as somebody's actual name, so only a single unbroken token can be
  refused at all. That is what keeps `Best Pizza, see foo.com` safe.
- **Bare domains are allowed on purpose.** There is no way to tell a spammy
  `example.com` from a legitimate brand, and the Booking.com case decides it.
- This journey covers one of four cases on BC 10213032167. The empty-listing-type
  and unregistered-Need-type cases were fixed earlier. The fourth — a Need whose
  type does not match its title — is not a validation problem; see the card.
