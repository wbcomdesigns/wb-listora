---
slug: admin-save-confirms-success
priority: normal
covers:
  - BC 10167580523
likely_files:
  - assets/js/admin/type-editor.js
  - assets/js/admin/settings-page.js
  - includes/admin/class-type-editor.php
  - includes/admin/class-settings-page.php
---

# A save that redirects still reports that it saved

Both admin writes toasted on success, and both then destroyed the toast: the
new-type save redirects to the edit screen, and Reset Settings reloads the
page. A toast cannot survive either. From the owner's side the write was
silent, and the only way to be sure was to re-read the form — worst of all
after Reset, which is destructive and irreversible.

The confirmation now travels through the navigation as a URL flag and is
rendered server-side on arrival.

## Steps

1. Log in as an administrator.
2. Go to **Listora → Listing Types → Add New**. Fill in a name and save.
   - **Expect:** you land on the edit screen for the new type AND a green
     notice reads "Listing type saved."
   - **Fail if:** the edit screen loads with no confirmation of any kind.
3. Edit an existing type and save (no redirect on this path).
   - **Expect:** the "Type saved successfully." toast appears and stays.
4. Go to **Listora → Settings → Advanced** and use **Reset Settings**.
   - **Expect:** the page reloads and a green notice reads "All settings were
     reset to their defaults."
   - **Fail if:** the page reloads with defaults restored but says nothing —
     the owner cannot tell whether the reset ran, half-ran, or failed.
