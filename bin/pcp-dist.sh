#!/usr/bin/env bash
#
# Run WP Plugin Check (PCP) against the BUILT dist zip, not the dev tree.
#
# Why: PCP scans every file it can see. Run against the working copy it flags
# dev-only files (tests/, tools/, bin/, phpstan-bootstrap.php, vendor/) that
# ship-time exclusions in build-release.sh strip out. Those findings are noise
# — every real customer install only ever sees the dist zip.
#
# Requires:
#   - bin/build-release.sh (produces dist/wb-listora-<version>.zip)
#   - wp-cli with `plugin-check-command` package installed:
#       wp package install wp-cli/plugin-check-command:@stable
#   - A working WordPress install on `LOCAL_CI_SITE_URL` (or `--path` flag).
#
# Usage:
#   bash bin/pcp-dist.sh                     # uses ../../../ as WP root
#   bash bin/pcp-dist.sh /path/to/wp-root    # explicit WP root
#
# Exit codes:
#   0 — no ERROR-level findings
#   1 — PCP found ERROR-level findings (release-blocking)
#   2 — env / build / wp-cli error
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_ROOT="${1:-$(cd "$PLUGIN_DIR/../../../" && pwd)}"

if ! command -v wp >/dev/null 2>&1; then
	echo "ERROR: wp-cli is required. See https://wp-cli.org/#installing" >&2
	exit 2
fi

if ! wp --path="$WP_ROOT" package list 2>/dev/null | grep -q "plugin-check-command"; then
	echo "ERROR: wp-cli/plugin-check-command not installed." >&2
	echo "       Run: wp --path=\"$WP_ROOT\" package install wp-cli/plugin-check-command:@stable" >&2
	exit 2
fi

# Build the dist zip.
echo "→ Building dist zip from $PLUGIN_DIR"
bash "$PLUGIN_DIR/bin/build-release.sh" >/dev/null

DIST_ZIP="$(ls -t "$PLUGIN_DIR/dist/"wb-listora-*.zip 2>/dev/null | head -1)"
if [ -z "$DIST_ZIP" ] || [ ! -f "$DIST_ZIP" ]; then
	echo "ERROR: build-release.sh did not produce a dist zip in $PLUGIN_DIR/dist/" >&2
	exit 2
fi
echo "→ Built: $DIST_ZIP"

# Stage the dist zip in WP_ROOT/wp-content/plugins/ under a temp slug,
# run PCP, then clean up.
STAGE_SLUG="wb-listora-pcp-$$"
STAGE_DIR="$WP_ROOT/wp-content/plugins/$STAGE_SLUG"
trap 'rm -rf "$STAGE_DIR"' EXIT

echo "→ Staging at $STAGE_DIR"
mkdir -p "$STAGE_DIR"
( cd "$STAGE_DIR" && unzip -q "$DIST_ZIP" )

# unzip extracts to wb-listora/ inside our stage dir; move contents up one
# level so wp plugin check sees the right shape.
if [ -d "$STAGE_DIR/wb-listora" ]; then
	mv "$STAGE_DIR/wb-listora/"* "$STAGE_DIR/"
	mv "$STAGE_DIR/wb-listora/".??* "$STAGE_DIR/" 2>/dev/null || true
	rmdir "$STAGE_DIR/wb-listora"
fi

echo "→ Running wp plugin check $STAGE_SLUG --format=json"
PCP_OUTPUT=$(wp --path="$WP_ROOT" plugin check "$STAGE_SLUG" --format=json 2>&1 || true)

# Count ERROR-level findings (vs WARNING). PCP JSON format:
#   [{"file":"...","line":N,"column":N,"type":"ERROR|WARNING",...}, ...]
ERROR_COUNT=$(echo "$PCP_OUTPUT" | grep -oE '"type":"ERROR"' | wc -l | tr -d ' ')

echo ""
echo "$PCP_OUTPUT"
echo ""
echo "→ ERROR-level findings: $ERROR_COUNT"

if [ "$ERROR_COUNT" -gt 0 ]; then
	echo "✗ Plugin Check failed — fix ERRORs before release." >&2
	exit 1
fi

echo "✓ Plugin Check passed (0 ERROR-level findings)."
