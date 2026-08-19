---
journey: search-cache-ttl-zero-disables
plugin: wb-listora
roles: [admin]
priority: high
covers: [BC-10203769600, Cache::ttl, search_cache_ttl, facet_cache_ttl, transient-expiry, migration-1.6.0]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin access to Settings → Search"
  - "At least one listing type with published listings"
estimated_runtime_minutes: 4
---

# A cache TTL of 0 disables caching instead of caching forever

The settings screen says **"Set to 0 to disable caching"**. That value was passed straight to
`set_transient()`, where `0` is WordPress' code for *never expire*. So the one control an owner
reaches for to turn caching OFF was the control that made every entry permanent.

Both engines had the shape (`Search_Engine::cache_result`, `Facets::get_cached`), and neither gated
the **read** either — so a site that hit this and later set a sane TTL would still be served the
permanent rows it had already accumulated. Fixing only the write would have left those installs
stale indefinitely.

The failure is invisible from the UI: search keeps working, it just serves whatever it cached the
first time and the `wp_options` table grows without bound.

> `Cache::ttl()` is the single rule. A non-positive value means skip the cache entirely, on read as
> well as write.

## Steps

### 1 — TTL 0 writes nothing

Set **Settings → Search → Search cache TTL** to `0`, then:

```bash
wp eval '
global $wpdb;
$q = "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE \"_transient_listora_search_%\"";
$b = (int) $wpdb->get_var($q);
wb_listora_service("search_engine")->search(["type"=>"restaurant","per_page"=>5]);
echo "before=$b after=" . (int) $wpdb->get_var($q) . "\n";'
```

- **Expect** the counts to be equal — nothing cached.
- A new row is the regression. Check it for a matching `_transient_timeout_` row: if there is none,
  that row is **permanent** and this is the original bug exactly.

### 2 — TTL 0 does not SERVE an existing permanent row

Still at `0`, plant one and confirm it is ignored:

```bash
wp eval 'set_transient("listora_search_qa_probe","STALE",0);
echo get_transient("listora_search_qa_probe") ? "planted\n" : "failed\n";'
```

Run a search that would hit that key shape.

- **Expect** fresh results, not the planted value. A read gated only on the write side is a
  half-fix.

### 3 — A real TTL caches WITH an expiry

Set the TTL to `15`, run a search, then:

```bash
wp eval '
global $wpdb;
$perm = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} o
  WHERE o.option_name LIKE \"_transient_listora_search_%\"
  AND NOT EXISTS (SELECT 1 FROM (SELECT option_name FROM {$wpdb->options}) t
    WHERE t.option_name = CONCAT(\"_transient_timeout_\", SUBSTRING(o.option_name, 12)))");
echo "permanent rows: $perm\n";'
```

- **Expect** at least one cached row, and **`permanent rows: 0`**.

### 4 — Facets obey the same rule

Repeat steps 1 and 3 against **facet cache TTL**, asserting on
`_transient_listora_facets_%`. Both engines must agree; they were fixed together because they had
the same bug independently.

### 5 — The migration cleared what the bug already wrote

On a site upgraded from before 1.6.0:

- **Expect** zero `_transient_listora_search_%` / `_transient_listora_facets_%` rows lacking a
  timeout row. Migration `1.6.0` sweeps exactly those.
- A normal cached entry (one that HAS a timeout) must survive — the sweep is deliberately narrow and
  must not flush a healthy cache.

## Cleanup

Restore the site's original TTL values. Delete `listora_search_qa_probe` if step 2 left it behind.
