---
journey: rest-strings-arrive-decoded
plugin: wb-listora
roles: [anonymous, member]
priority: high
covers: [BC-10202832578, BC-10195032749, wb_listora_decode_text, term-names, KSES, app-contract]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing whose title contains an ampersand"
  - "That listing's featured image has alt text containing the same ampersand"
estimated_runtime_minutes: 4
---

# Every human-facing string leaves REST decoded

Whether a value arrived decoded used to depend on which line of PHP built it. The same source string
could answer twice on one row:

    title : Central Park — The Mall & Bethesda Terrace
    alt   : Central Park — The Mall &#038; Bethesda Terrace

Term names are the same problem from the other end. `wp_insert_term()` runs names through KSES, so
"Fitness Centers & Gyms" is stored in `wp_terms` **already encoded** — and the categories endpoint
returned it raw, while `view.js` assigns it with `textContent`, rendering a literal `&amp;` in the
add-listing dropdown.

What hid this for so long: there was exactly ONE `html_entity_decode()` in the codebase, in the card
helper. So the most-looked-at field — the card title — was the one that was already right.

> `wb_listora_decode_text()` is the rule now. Decode-on-output, because these values are consumed by
> native clients that render plain text and have no HTML parser. Templates still escape at their own
> point of output; this is for API payloads.

## Steps

### 1 — Two fields, one row, same answer

```bash
wp eval '
$d = rest_do_request(new WP_REST_Request("GET","/listora/v1/search"))->get_data();
foreach ((array)($d["items"] ?? $d) as $r) {
  if (strpos((string)($r["title"]??""), "&") !== false) {
    echo "title: " . $r["title"] . "\n";
    echo "alt  : " . ($r["featured_image"]["alt"] ?? "(none)") . "\n"; break;
  }
}'
```

- **Expect** both rendered with a literal `&`.
- `&#038;` or `&amp;` in **either** field is the regression. They must agree — disagreement is the
  whole bug.

### 2 — Excerpts too

Check `excerpt` on `/search` and `/listings/{id}/detail` for a listing whose excerpt contains a
curly apostrophe or an ampersand.

- **Expect** `Manhattan's`, not `Manhattan&#8217;s`.

### 3 — Term names, including KSES-encoded ones

```bash
wp eval '
$r = wp_insert_term("QA Fitness Centers & Gyms", "listora_listing_cat");
$id = $r["term_id"]; $t = get_term($id);
echo "DB stores : " . $t->name . "\n";
echo "decoded   : " . wb_listora_decode_text($t->name) . "\n";
wp_delete_term($id, "listora_listing_cat");'
```

- **Expect** the DB to hold `QA Fitness Centers &amp; Gyms` (that is core KSES, not our bug) and the
  decoded output to read `QA Fitness Centers & Gyms`.
- If the DB value is already clean, KSES behaviour changed upstream — re-verify before assuming the
  fix is what is being tested.

### 4 — The dropdown the card reported

Open `$SITE_URL/add-listing/`, pick a type that has a category containing `&`.

- **Expect** the option to read `B&B`, not `B&amp;B`. This is the endpoint from step 3 feeding
  `opt.textContent`.

### 5 — Term names are decoded everywhere they surface, not just the dropdown

Check `type.name` / category names on `/search` rows and on `/listings/{id}/detail`.

- **Expect** decoded. The fix was applied at every REST output point deliberately — a category
  reading `Yoga &amp; Pilates` on a card is the same defect on a different screen.

### 6 — Nothing is double-decoded

- **Expect** a title that legitimately contains `&amp;` as *literal text* to survive. Confirm no
  field is passed through the decoder twice — grep for
  `wb_listora_decode_text( wb_listora_decode_text` should return nothing.

## Cleanup

Step 3 deletes its own probe term. Nothing else to undo.
