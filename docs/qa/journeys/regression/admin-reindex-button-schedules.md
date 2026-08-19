---
journey: admin-reindex-button-schedules
plugin: wb-listora
roles: [admin]
priority: normal
covers: [BC-10203331648, Search_Indexer, admin_init-handlers, nonce-verification, listora_reindex]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin (or a user with manage_listora_settings only, for step 4)"
  - "Action Scheduler / WP-Cron reachable"
estimated_runtime_minutes: 3
---

# The Rebuild Search Index button actually rebuilds the search index

The button shipped pointing at `?page=listora-settings&action=reindex` with a `listora_reindex`
nonce, and **nothing consumed either**. Clicking it reloaded the settings page and did nothing.

That is worse than an ordinary missing feature. The button's own help text tells owners to run it
"after bulk-editing listings or changing custom fields", so anyone following that advice believed
their index had been rebuilt when it had not — and a stale search index fails **silently**, by
returning wrong results rather than an error.

This is the third instance of the same class in this codebase: an admin action handler running too
late in the request, or not at all. The other two were `listora-credit-mappings` and Pro's badges
page. The handler lives on `admin_init` for that reason — a render callback fires after admin chrome
has begun printing, so `wp_safe_redirect()` would warn "headers already sent" and no confirmation
would ever appear.

## Steps

### 1 — A bogus nonce is rejected

Visit `$SITE_URL/wp-admin/admin.php?page=listora-settings&action=reindex&_wpnonce=bogus`.

- **Expect** HTTP **403** ("link expired").
- **HTTP 200 with the settings page rendered normally is the original regression** — that is exactly
  what an unhandled action looks like, and it is how this bug was proven to exist. Do not read a
  clean-looking page as a pass.

### 2 — The real button schedules the job

Go to **Settings → Advanced/Maintenance** and click **Rebuild Search Index**.

- **Expect** a redirect to `?page=listora-settings&listora_reindexed=1` with a success notice
  reading that the rebuild is scheduled.
- Then:

```bash
wp eval '
$h = \WBListora\Search\Search_Indexer::REINDEX_CRON_HOOK;
echo "scheduled: " . var_export(wp_next_scheduled($h), true) . "\n";
echo "offset: " . var_export(get_option("wb_listora_reindex_offset","unset"), true) . "\n";'
```

- **Expect** a scheduled timestamp, and the offset cursor **cleared** so the rebuild starts from
  zero rather than resuming a stale cursor.

### 3 — It is scheduled, not run inline

- **Expect** the click to return immediately. The rebuild is batched across cron ticks on purpose —
  a 100k-listing site must not rebuild inside a page load. A click that hangs for seconds means
  someone inlined the work.

### 4 — Capability, not just nonce

As a user holding `manage_listora_settings` but not `manage_options`:

- **Expect** the button to work — it is gated on the same capability the settings page requires.

As a subscriber, hitting the URL directly with a valid-looking nonce:

- **Expect** no reindex scheduled.

### 5 — The index actually repopulates

Let cron run, then compare `{prefix}listora_search_index` row count against published listings.

- **Expect** the table repopulated. Scheduling without rebuilding is a different failure wearing the
  same success notice.

## Cleanup

None required — a reindex is idempotent. If you want to avoid the background work on a large site,
`wp eval 'wp_unschedule_event(wp_next_scheduled(\WBListora\Search\Search_Indexer::REINDEX_CRON_HOOK), \WBListora\Search\Search_Indexer::REINDEX_CRON_HOOK);'`
