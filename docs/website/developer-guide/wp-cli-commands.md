# WP-CLI Commands

WB Listora exposes two CLI command namespaces — `wp listora` (Free) and `wp listora-pro` (Pro) — for the operations admins do most: imports, exports, competitor migrations, seed-demo, cron management, search-index rebuild, and Pro-specific tools (license activate/deactivate, webhook tests). Use them when the admin UI's PHP `max_execution_time` would limit a large operation, or when scripting deployments.

## What it is

Both namespaces register at `WP_CLI::add_command()` and extend `\WP_CLI_Command`. Discoverable from any shell with WP-CLI installed: `wp listora` lists subcommands; `wp listora-pro` does the same for Pro.

## Free namespace — `wp listora`

```bash
# Setup + housekeeping
wp listora seed-demo                              # Load demo content (5 type-specific packs)
wp listora rebuild-search-index                   # Refresh the search_index denormalized table
wp listora flush-rewrite                          # Flush rewrite rules (after URL changes)

# Bulk import / export
wp listora import file.csv --type=restaurant      # CSV import
wp listora import file.json                       # JSON import (no --type when rows carry their own)
wp listora import file.geojson --type=restaurant  # GeoJSON FeatureCollection import
wp listora export --type=restaurant --output=restaurants.csv
wp listora export --status=publish --output=full.csv
wp listora export --output=full.json --format=json

# Competitor migration
wp listora migrate --from=geodirectory --dry-run  # Preview row count + sample
wp listora migrate --from=geodirectory            # Run
wp listora migrate --from=directorist
wp listora migrate --from=bdp
wp listora migrate --from=listingpro

# Maintenance
wp listora cron list                              # List scheduled cron events (AS + WP-Cron)
wp listora cron run wb_listora_check_expirations  # Trigger one immediately
wp listora cleanup-orphans                        # Find + clean orphaned meta/term relationships
wp listora rebuild-thumbnails                     # Regenerate the medium image used in the map popup
```

### Common flags

| Flag | Purpose |
|---|---|
| `--type={slug}` | Restrict to a specific listing type. |
| `--status={publish\|pending\|draft\|listora_expired}` | Filter by status. |
| `--batch-size=N` | Override the default 100 batch. Useful for memory-constrained servers. |
| `--dry-run` | Preview without writing. Available on migrate + cleanup. |
| `--output={path}` | Write output to file (export commands). |
| `--format={csv\|json}` | Output format. |

## Pro namespace — `wp listora-pro`

```bash
# License
wp listora-pro license activate <KEY>             # Activate a license key
wp listora-pro license deactivate
wp listora-pro license check                      # Force a remote check now (skip the weekly cron)
wp listora-pro license info                       # Show current state

# Webhooks (outgoing)
wp listora-pro webhook list                       # List all registered outgoing webhooks
wp listora-pro webhook test {endpoint-id}         # Fire a test payload at a specific endpoint
wp listora-pro webhook deliver {webhook-id}       # Manually trigger a queued delivery
wp listora-pro webhook log --recent=50            # Show recent delivery logs

# Audit Log
wp listora-pro audit-log export --output=audit.csv --since="2026-01-01"
wp listora-pro audit-log prune --older-than=180   # Manual prune (cron handles this automatically)

# Credits
wp listora-pro credits balance <user>             # Show a user's credit balance + ledger summary
wp listora-pro credits add <user> --amount=100 --note="manual grant"
wp listora-pro credits deduct <user> --amount=10
wp listora-pro credits ledger <user> --recent=20  # Recent ledger entries

# Setup helpers
wp listora-pro seed-demo                          # Pro demo overlay (adds Pro-feature demo data)
wp listora-pro pages ensure                       # Re-create Compare / Buy Credits / Needs pages if deleted
wp listora-pro features list                      # Show every feature toggle's current state
wp listora-pro features toggle quick_view --on    # Programmatically toggle a feature
```

## How you use it

### Common scripted workflows

```bash
# Onboard a new staging clone: refresh search index + ensure pages + seed demo
wp listora rebuild-search-index
wp listora-pro pages ensure
wp listora seed-demo
wp listora-pro seed-demo

# Bulk import 50K listings without the UI hitting max_execution_time
wp listora import huge-file.csv --type=restaurant --batch-size=200

# Audit-log compliance export (90 days)
wp listora-pro audit-log export --since="$(date -v-90d '+%Y-%m-%d')" --output=audit-$(date +%F).csv

# Cron health check (after a busy spam event, verify retries cleared)
wp listora cron list | grep wb_listora_pro_deliver_webhook

# Manual license re-check after an outage
wp listora-pro license check
```

### Bypass behaviour

WP-CLI commands automatically bypass:
- CAPTCHA (via `listora_should_skip_captcha()` helper) — admins running imports shouldn't be CAPTCHA'd.
- Rate limits — bulk imports don't trip the per-IP submission cap.
- Required-login REST permission callbacks — CLI runs as the system, not a user.

This is documented behaviour, not a backdoor: WP-CLI execution requires shell access to the server, which is already a much stronger gate than CAPTCHA or rate limits.

### Adding your own subcommands

Hook into the existing command class to add subcommands without forking:

```php
add_action( 'init', function () {
    if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) return;
    WP_CLI::add_command( 'listora my-custom', 'My_Custom_CLI' );
} );

class My_Custom_CLI {
    public function check_geo_coverage( $args, $assoc_args ) {
        // ...
        WP_CLI::success( 'Geo coverage: 97%' );
    }
}
```

Then: `wp listora my-custom check-geo-coverage`.

## Settings & options

| Class | Namespace | Subcommands |
|---|---|---|
| `WBListora\CLI_Commands` (Free) | `wp listora` | seed-demo, rebuild-search-index, flush-rewrite, import, export, migrate, cron, cleanup-orphans, rebuild-thumbnails |
| `WBListoraPro\CLI_Commands` (Pro) | `wp listora-pro` | license, webhook, audit-log, credits, seed-demo, pages, features |

CLI commands fire the same hooks as the equivalent admin actions — `wb_listora_after_create_listing`, `wb_listora_pro_credits_added`, etc. — so listeners (audit log, outgoing webhooks) work uniformly whether the action came from a UI click or a CLI script.

## Related

- [Import & Export](../features/import-export.md) — most CLI imports/exports also have a UI equivalent.
- [Credits & Pricing Plans (Pro)](../features/credits-and-plans.md) — the credit ledger the `credits` subcommands operate on.
- [Audit Log (Pro)](../features/audit-log.md) — CLI actions are recorded with the system user.
- [License Management (Pro)](../getting-started/pro-license.md) — UI equivalent for `license` subcommands.
