---
journey: bulk-edit-listing-type-renders
plugin: wb-listora
priority: high
roles: [admin]
covers: [BC-10190576873, bulk-edit, quick-edit, admin-columns]
prerequisites:
  - "At least 3 listings of mixed listing types"
  - "2+ registered listing types"
estimated_runtime_minutes: 5
covers_card: 10190576873
---

# The bulk-edit control must actually render

`bulk_edit_custom_box` fires per column, and **WordPress skips the core columns**
— `cb`, `title`, `author`, `date`, `comments`. The listing-type control was
registered against `title`, so the callback was never invoked: the Bulk Edit
panel opened, looked normal, and contained nothing of ours.

There is no error anywhere in this failure. The hook is registered, the callback
is correct, the panel renders. It is only ever visible by looking.

## Steps

### 1. Open Bulk Edit
Listings list → select 2+ rows → Bulk Actions → Edit → Apply.

### 2. The control is present AND computed-visible
Assert the listing-type `<select>` exists **and**
`getComputedStyle(el).display !== 'none'`.
- **Fails if** absent — the column binding is a core column again.

### 3. It is bound to a non-core column
```bash
grep -n "bulk_edit_custom_box\|quick_edit_custom_box" includes/admin/*.php
```
- **Fails if** the column argument is any of `cb`, `title`, `author`, `date`,
  `comments`. Bind to a plugin-owned column (`listora_type`).

### 4. Applying it changes the data
Set a type, Update, and re-read the term from the DB for every selected listing.
- **Fails if** the list table shows the new type but the term did not change, or
  vice versa — that is the counter-vs-list class in a different costume.

### 5. "— No change —" changes nothing
Re-run leaving the control at its default. Every listing keeps its type.
- **Fails if** any listing is reassigned or cleared. A bulk action that
  overwrites on no-op silently rewrites the whole directory.

### 6. Quick Edit too
Same control, single row. The two share a callback and diverge easily.

## Test-data trap

Selecting listings that already share one type makes step 4 pass without the
save path working at all. Use rows of **mixed** types and assert each one
individually.
