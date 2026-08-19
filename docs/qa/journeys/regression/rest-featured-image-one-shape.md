---
journey: rest-featured-image-one-shape
plugin: wb-listora
roles: [anonymous]
priority: high
covers: [BC-10194450677, BC-10203381688, Image_Schema, featured_image, app-contract]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing with a featured image whose attachment has thumbnail/medium/medium_large/large registered"
  - "That listing must appear in /search results and in some listing's /related"
estimated_runtime_minutes: 4
---

# One `featured_image` shape on every endpoint

Three hand-maintained builders produced three different payloads for the same attachment:

    /detail   id, alt, thumbnail, medium, large,        full
    /related  id, alt, thumbnail, medium, medium_large, full
    /search   id, alt, thumbnail, medium,               full

They also disagreed on three things nobody had recorded: a missing size was `false` from one builder
and `''` from the others (so the field's **type** depended on the endpoint), a listing with no image
was `[]` on `/detail` and `null` elsewhere, and the detail builder's docblock *claimed* it matched
the search controller — which is worse than no comment, because it tells the next reader the
invariant already holds.

`Image_Schema` is now the only builder. The published set is the **union** of what the three
returned, so every endpoint gained sizes and none lost one.

> **Control for the data.** Different listings have different attachments, and an attachment
> genuinely missing a size will look like an endpoint bug. Always compare the SAME listing across
> all three endpoints, and confirm the attachment has every size registered first.

## Steps

### 1 — Confirm the attachment is not the variable

```bash
wp eval 'echo implode(",", array_keys(wp_get_attachment_metadata(<ATT_ID>)["sizes"] ?? [])) . "\n";'
```

- **Expect** `thumbnail`, `medium`, `medium_large`, `large` present. If a size is genuinely absent,
  pick a different listing — otherwise step 2 cannot distinguish a code bug from missing data.

### 2 — All three endpoints agree, on one listing

```bash
wp eval '
function shp($i){ if(!is_array($i)) return "(".var_export($i,true).")"; $k=array_keys($i); sort($k); return implode(",",$k); }
$t = <LISTING_ID>;
$rel = rest_do_request(new WP_REST_Request("GET","/listora/v1/listings/<PARENT_ID>/related"))->get_data();
$rel = $rel["items"] ?? $rel;
$det = rest_do_request(new WP_REST_Request("GET","/listora/v1/listings/$t/detail"))->get_data();
$sr  = rest_do_request(new WP_REST_Request("GET","/listora/v1/search"))->get_data();
$rows = $sr["items"] ?? $sr["listings"] ?? $sr; $hit=null;
foreach((array)$rows as $r){ if((int)($r["id"]??0)===$t){$hit=$r;break;} }
foreach(["related"=>$rel[0]["featured_image"]??null,"detail"=>$det["featured_image"]??null,"search"=>$hit["featured_image"]??null] as $k=>$v)
  echo str_pad($k,9) . shp($v) . "\n";'
```

- **Expect** three identical key lists:
  `alt, full, id, large, medium, medium_large, thumbnail`.
- Any endpoint short a size is the regression.

### 3 — Types are stable

- **Expect** every size value to be a **string**. A missing size is `""`, never `false` and never
  `null`. A client doing a `typeof` check must get the same answer from every endpoint.

### 4 — No image is `null`, everywhere

Request `/detail` for a listing with no featured image.

- **Expect** the `featured_image` key **present** with value `null`.
- `[]` is the old detail-builder behaviour.
- **Assert with `array_key_exists`, not `??`** — `??` treats `null` as missing and will report a
  correct payload as broken. (This caught me during the fix.)

### 5 — `image_sizes` still trims

`GET /listora/v1/listings/{id}/detail?image_sizes=thumbnail,medium`

- **Expect** exactly `id, alt, thumbnail, medium`.
- An unknown size (`?image_sizes=banana`) falls back to the full set rather than erroring — a client
  asking for something we do not publish gets the standard payload, not a broken one.

### 6 — Nothing was removed

- **Expect** `large` still on `/detail` and `medium_large` still on `/related`. The union was chosen
  precisely so no existing client loses a field. If either is gone, someone "standardised" on the
  intersection and broke a live consumer.

## Cleanup

None — all steps are read-only.
