# WP-CLI Commands

WB Listora ships two CLI command namespaces - `wp listora` (Free) and `wp listora-pro` (Pro) - for the operations admins do most: directory statistics, search-index rebuilds, imports, exports, competitor migrations, database repair, and demo content management. Use them whenever the admin UI's PHP `max_execution_time` would cap a large operation, or when scripting deployments.

## What it is

Both namespaces register at `WP_CLI::add_command()` and extend `\WP_CLI_Command`. Discoverable from any shell with WP-CLI installed: `wp listora` lists Free subcommands; `wp listora-pro` lists Pro subcommands.

## Free namespace - `wp listora`

10 subcommands, every one matching `includes/class-cli-commands.php`.

```bash
# Statistics + health
wp listora stats # Directory totals, index sync %, DB table sizes
wp listora repair # Clean orphaned search_index + geo rows (--dry-run supported)
wp listora reindex # Rebuild search_index for all listings
wp listora reindex --type=restaurant # Reindex one listing type
wp listora reindex --batch-size=500 --dry-run # Preview without writing

# Email + maintenance (since 1.1.0)
wp listora test-email # List the available notification templates
wp listora test-email listing_approved --to=you@example.com # Send one template to verify rendering + delivery
wp listora cleanup # Run the daily housekeeping cron now (email-log + analytics retention, stale unverified listings)

# Type registry
wp listora listing-types # Table of registered types: slug, name, field count, schema

# CSV import / export
wp listora import listings.csv --type=restaurant
wp listora import listings.csv --type=restaurant --dry-run
wp listora export # Default: publish status, all types, dated filename
wp listora export --type=restaurant --output=restaurants.csv
wp listora export --status=pending --output=pending.csv

# Competitor migration (any of 4 sources)
wp listora migrate --from=directorist
wp listora migrate --from=geodirectory --dry-run
wp listora migrate --from=bdp --batch-size=25
wp listora migrate --from=listingpro

# Demo content
wp listora demo seed # All 9 packs + test users (default)
wp listora demo seed --pack=restaurant # One pack only
wp listora demo seed --pack=restaurant,hotel # Multiple packs
wp listora demo seed --pack=all --with-users --reindex
wp listora demo seed --pack=classified --skip-images
wp listora demo remove # Removes only listings tagged _listora_demo_content
wp listora demo reseed --pack=restaurant # Remove + re-seed in one go
```

### Common flags

| Flag | Subcommands | Purpose |
|---|---|---|
| `--type=<slug>` | reindex, import, export | Restrict to a specific listing type. |
| `--to=<email>` | test-email | Recipient address. Defaults to the site admin email. |
| `--status=<status>` | export | Post status filter. Default `publish`. |
| `--batch-size=<N>` | reindex, migrate | Override default batch size (500 reindex, 50 migrate). Lower for memory-constrained servers. |
| `--dry-run` | reindex, import, repair, migrate | Preview without writing. |
| `--output=<path>` | export | Output file path. Default `listora-export-YYYY-MM-DD.csv`. |
| `--from=<source>` | migrate | One of: `directorist`, `geodirectory`, `bdp`, `listingpro`. |
| `--pack=<slug>` | demo seed/reseed | Comma-separated or `all`. Available: `restaurant`, `hotel`, `real-estate`, `job-board`, `general`, `classified`, `education`, `healthcare`, `place`. |
| `--with-users` | demo seed/reseed | Also create the four default test users (`contributor1`, `author1`, `subscriber2`, `subscriber3`). |
| `--skip-images` | demo seed/reseed | Skip image sideloading. Useful for CI / slow networks. |
| `--reindex` | demo seed/reseed | Run `Search_Indexer::batch_reindex()` after seeding. |

## Pro namespace - `wp listora-pro`

1 subcommand, matching `wb-listora-pro/includes/class-cli-commands.php`.

```bash
# Demo QA dataset (full Pro overlay - moderators, badges, plans, coupons, needs, webhooks, audit log)
wp listora-pro demo seed
wp listora-pro demo seed --reindex # Run wp listora reindex after seeding
wp listora-pro demo seed --skip-images # Skip Picsum photo-review sideload
wp listora-pro demo seed --user-id=42 # Use a specific user as primary actor
wp listora-pro demo remove
```

### Pro flags

| Flag | Purpose |
|---|---|
| `--reindex` | After seeding, refresh the Free search index. |
| `--skip-images` | Skip Picsum image sideload for photo reviews (faster, offline-safe). |
| `--user-id=<id>` | Use a specific user as primary actor. When omitted the seeder provisions three QA test users (`pro-vendor`, `pro-moderator`, `pro-customer`). |

Pro management operations that the previous version of this doc claimed as CLI commands (license activate/deactivate, webhooks, credit ledger, audit-log export, feature toggles, pages ensure) are admin-UI only - they're not exposed via WP-CLI. Use the Settings page or the [REST API](rest-api.md) instead.

## How you use it

### Common scripted workflows

```bash
# Onboard a fresh staging clone end-to-end (Free + Pro demo data)
wp listora demo seed --pack=all --with-users --reindex
wp listora-pro demo seed --reindex

# Health check before an upgrade
wp listora stats # Confirm sync % is at 100; flag if not
wp listora repair --dry-run # Preview orphan rows; run without --dry-run to clean

# Bulk import 50K listings without the UI hitting max_execution_time
wp listora import huge-file.csv --type=restaurant

# Reindex after editing field configuration
wp listora reindex --type=restaurant --batch-size=200

# Competitor migration with safety preview
wp listora migrate --from=directorist --dry-run # See row count + sample
wp listora migrate --from=directorist # Run for real

# Reseed demo content after seeder improvements
wp listora demo reseed --pack=all --with-users --reindex

# Verify mail delivery + template rendering after an SMTP change
wp listora test-email listing_approved --to=you@example.com

# Force the daily housekeeping cron immediately (fires wb_listora_daily_cleanup)
wp listora cleanup
```

### Bypass behaviour

WP-CLI execution automatically bypasses:

- **CAPTCHA** - admins running imports shouldn't be CAPTCHA'd.
- **Rate limits** - bulk imports don't trip the per-IP submission cap.
- **Required-login REST permission callbacks** - CLI runs as the system, not as a user.

This is documented behaviour, not a backdoor: WP-CLI execution already requires shell access to the server, a much stronger gate than CAPTCHA or per-IP windows.

### Adding your own subcommands

Add subcommands to either namespace without forking by registering on `WP_CLI`:

```php
add_action( 'cli_init', function () {
WP_CLI::add_command( 'listora my-custom', 'My_Custom_CLI' );
} );

class My_Custom_CLI {

/**
* Check geo-coverage of all listings.
*
* ## EXAMPLES
*
* wp listora my-custom check-geo-coverage
*/
public function check_geo_coverage( $args, $assoc_args ) {
global $wpdb;
$covered = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}listora_geo" );
$total = (int) wp_count_posts( 'listora_listing' )->publish;
WP_CLI::success( sprintf( 'Geo coverage: %d / %d (%.1f%%)', $covered, $total, ( $total ? $covered / $total * 100 : 0 ) ) );
}
}
```

Then: `wp listora my-custom check-geo-coverage`.

## Settings & options

| Class | Namespace | Subcommands |
|---|---|---|
| `WBListora\CLI_Commands` (Free) | `wp listora` | `stats`, `reindex`, `test-email`, `cleanup`, `listing-types`, `import`, `export`, `repair`, `migrate`, `demo` |
| `WBListoraPro\CLI_Commands` (Pro) | `wp listora-pro` | `demo` |

CLI commands fire the same hooks as the equivalent admin / REST actions - `wb_listora_after_create_listing`, `wb_listora_listing_status_changed`, `wb_listora_pro_credits_added`, etc. - so listeners (notifications, audit log, outgoing webhooks) work uniformly whether the action came from a UI click, a REST call, or a CLI script.

## Related

- [Import & Export](../features/import-export.md) - customer-facing CSV / JSON / GeoJSON import-export UI and CLI equivalents.
- [Competitor migration guides](../migrate-from-directorist.md) - step-by-step for each supported source.
- [REST API](rest-api.md) - what CLI commands operate on at the data layer; many CLI flows also have a REST equivalent.
- [Custom Fields](custom-fields.md) - reindex after changing field configuration.
