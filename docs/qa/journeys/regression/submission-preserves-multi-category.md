---
journey: submission-preserves-multi-category
plugin: wb-listora
roles: [member]
priority: critical
covers: [BC-10203063915, submission-controller, listora_listing_cat, allowed_categories, silent-data-loss]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Auto-login mu-plugin present (?autologin=1)"
  - "A listing owned by the test member that carries 2+ categories"
  - "A listing whose category is NOT in its type's allowed_categories (seeded data has these)"
estimated_runtime_minutes: 6
---

# Editing a listing does not delete the categories the form cannot show

The form has ONE category `<select>`. The data model, REST reads, importers, exporters, migrators,
schema.org and the related-listings block all treat categories as multi-valued. The write path
passed the form's single value to `wp_set_object_terms()`, which **replaces the entire term set** —
so every category the form could not express was deleted on save, silently, behind an HTTP 200.

Two symptoms, one cause, and they compound:

**Data loss.** A listing with `[Accommodation, Admin]` edited through `/submit` came back as
`[Accommodation]`. No warning, no error, success response.

**Dead-end form.** The edit dropdown was built purely from the type's `allowed_categories`, so a
listing holding an out-of-set category showed "Select a category" with its real category *not among
the options* — on a `required` field. That owner could not save at all without changing their
category to a wrong one, at which point the first bug wiped everything else.

On the dev site that second case was **39 of 99 listings**, all from seed data. This is not an
exotic path.

> The fix treats `category` as what it is: a statement about the ONE slot the form renders. It
> replaces the term the form was showing and preserves the rest. A client that genuinely owns the
> whole set sends `categories` instead.

## Steps

### 1 — A single `category` preserves the siblings

```bash
wp eval '
wp_set_current_user(1);
$c = get_terms(["taxonomy"=>"listora_listing_cat","hide_empty"=>false,"number"=>3]);
$id = wp_insert_post(["post_type"=>"listora_listing","post_status"=>"publish","post_title"=>"QA multicat","post_author"=>1]);
wp_set_object_terms($id, [(int)$c[0]->term_id, (int)$c[1]->term_id], "listora_listing_cat");
echo "before: " . implode(" | ", wp_get_object_terms($id,"listora_listing_cat",["fields"=>"names"])) . "\n";
$r = new WP_REST_Request("POST","/listora/v1/submit");
$r->set_param("listing_id",$id); $r->set_param("title","QA multicat");
$r->set_param("category",(int)$c[2]->term_id); $r->set_param("agree_terms",true);
echo "http: " . rest_do_request($r)->get_status() . "\n";
echo "after : " . implode(" | ", wp_get_object_terms($id,"listora_listing_cat",["fields"=>"names"])) . "\n";
wp_delete_post($id,true);'
```

- **Expect** two categories after the save: the new primary, plus the sibling the form never showed.
- **One category** is the regression — that is the silent delete.

### 2 — `categories[]` IS a complete statement

Repeat step 1 but send `categories` as an array of one term instead of `category`.

- **Expect** exactly that one category. This param is how an API client says "these and only these",
  and it must keep replacing wholesale — otherwise a client can never *remove* a category.

### 3 — Omitting both leaves terms untouched

Send an update with only `title`.

- **Expect** the category set unchanged. A rename must not touch taxonomy.

### 4 — An out-of-set category is offered and pre-selected

Open the frontend edit form for a listing whose category is not in its type's allowlist:
`$SITE_URL/add-listing/?edit=<id>&autologin=1`.

```js
const sel = document.querySelector('select[name="category"]');
({ value: sel.value, selected: sel.options[sel.selectedIndex].text, required: sel.required })
```

- **Expect** the listing's real category present in the options AND selected.
- `value: ""` with "Select a category" on a `required` field is the dead-end regression — that owner
  cannot save their listing at all.

### 5 — The allowlist still governs NEW listings

Start a fresh submission for that same type.

- **Expect** only the type's allowed categories. The union in step 4 applies to the listing being
  edited; it must not widen the picker for everyone.

## Cleanup

Delete the probe listing. Steps 4-5 are read-only — do not save the edit form, or you will change a
seeded listing's category.
