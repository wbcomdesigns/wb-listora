#!/usr/bin/env bash
# bin/cleanup-boundary-check.sh
#
# Detects Pro→Free boundary violations per Coding Rule #10 ("Pro: never
# import Free classes directly; hook into the extension hook and use the
# ServiceContainer"). Each violation is a candidate for refactor — Pro
# should call Free's service via container->get() instead of `use` + new.
#
# Also flags Pro code doing direct $wpdb queries against the Free plugin's
# custom tables — should route through Free's repository service.
#
# Output: audit/cleanup/boundary-violations.json
#
# Template placeholders (substituted by /wp-plugin-onboard scaffold):
#   WBListora    e.g. WPMediaVerse, Jetonomy
#   ../wb-listora-pro       relative path to companion: ../<companion-slug>
#   listora_ table prefix WITHOUT wpdb prefix: mvs_, jto_, wbam_
#   geo|search_index|field_index|reviews|review_votes|favorites|claims|hours|analytics|payments|services   pipe-separated table suffixes (e.g.
#                         "media_index|media_meta|reactions|favorites").
#                         Defaults to ALL when set to "*".

set -euo pipefail

FREE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PRO_DIR="$FREE_DIR/../wb-listora-pro"
OUT_DIR="$FREE_DIR/audit/cleanup"
OUT_FILE="$OUT_DIR/boundary-violations.json"

FREE_NAMESPACE="WBListora"
FREE_TABLE_PREFIX="listora_"
FREE_TABLE_LIST="geo|search_index|field_index|reviews|review_votes|favorites|claims|hours|analytics|payments|services"  # e.g. "media_index|media_meta|reactions" or "*"

mkdir -p "$OUT_DIR"

if [ ! -d "$PRO_DIR" ]; then
    echo "{\"generated_at\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\",\"violations\":[],\"note\":\"Companion plugin directory not found at $PRO_DIR\"}" > "$OUT_FILE"
    echo "No companion directory — wrote empty $OUT_FILE"
    exit 0
fi

JQ="${JQ:-jq}"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

find "$PRO_DIR" -name "*.php" \
    -not -path "*/vendor/*" -not -path "*/node_modules/*" \
    -not -path "*/dist/*" -not -path "*/build/*" \
    -not -path "*/audit/*" -not -path "*/tests/*" \
    -print0 > "$TMP/pro-scan.bin"

# Build table-match pattern. If FREE_TABLE_LIST is "*", match any
# <prefix>* table; otherwise match the explicit list.
if [ "$FREE_TABLE_LIST" = "*" ]; then
    TABLE_PATTERN="${FREE_TABLE_PREFIX}[a-z_]+"
else
    TABLE_PATTERN="${FREE_TABLE_PREFIX}(${FREE_TABLE_LIST})"
fi

# ---------------------------------------------------------------------------
# Violation A: `use WBListora\…` direct imports in companion code
# (except importing from the companion's own sub-namespace, e.g. Pro\Foo)
# ---------------------------------------------------------------------------
xargs -0 grep -nHE "^\s*use\s+${FREE_NAMESPACE}\\\\" < "$TMP/pro-scan.bin" 2>/dev/null \
    | grep -v "${FREE_NAMESPACE}\\\\Pro\\\\" \
    | awk -F: '{file=$1; line=$2; rest=substr($0, length(file)+length(line)+3); print file"\t"line"\t"rest}' \
    | while IFS=$'\t' read -r f l rest; do
        rel="${f#$PRO_DIR/}"
        symbol=$(echo "$rest" | sed -E 's/^\s*use\s+([^;]+);.*/\1/' | tr -d ' ')
        echo "{\"violation\":\"direct_free_import\",\"file\":\"$rel\",\"line\":$l,\"imports\":\"$symbol\",\"rule\":\"Coding Rule #10\",\"fix\":\"Use Plugin::container()->get(<service_key>) — never import Free classes directly\"}"
    done | $JQ -s '.' > "$TMP/imports.json" 2>/dev/null || echo "[]" > "$TMP/imports.json"

# ---------------------------------------------------------------------------
# Violation B: Direct `$wpdb` queries against Free's tables
# ---------------------------------------------------------------------------
xargs -0 grep -nHE "\\\$wpdb->(query|get_var|get_row|get_results|get_col|insert|update|delete|prepare)\s*\(" < "$TMP/pro-scan.bin" 2>/dev/null \
    | while IFS=$'\n' read -r match; do
        file=$(echo "$match" | awk -F: '{print $1}')
        line=$(echo "$match" | awk -F: '{print $2}')
        ctx=$(awk -v l="$line" 'NR>=l-2 && NR<=l+8' "$file" 2>/dev/null)
        if echo "$ctx" | grep -qE "$TABLE_PATTERN" ; then
            rel="${file#$PRO_DIR/}"
            echo "{\"violation\":\"direct_wpdb_on_free_table\",\"file\":\"$rel\",\"line\":$line,\"rule\":\"Coding Rule #16 + boundary\",\"fix\":\"Route through Plugin::container()->get(<repository_service>). Direct wpdb against Free tables bypasses caching, privacy, request-cache invariants.\"}"
        fi
    done | $JQ -s '.' > "$TMP/wpdb.json" 2>/dev/null || echo "[]" > "$TMP/wpdb.json"

# ---------------------------------------------------------------------------
# Violation C: Companion using `new WBListora\…` (fully-qualified)
# ---------------------------------------------------------------------------
xargs -0 grep -nHE "new\s+\\\\?${FREE_NAMESPACE}\\\\" < "$TMP/pro-scan.bin" 2>/dev/null \
    | grep -v "${FREE_NAMESPACE}\\\\Pro\\\\" \
    | grep -v "throw new" \
    | awk -F: '{file=$1; line=$2; print file"\t"line}' \
    | while IFS=$'\t' read -r f l; do
        rel="${f#$PRO_DIR/}"
        echo "{\"violation\":\"new_free_class\",\"file\":\"$rel\",\"line\":$l,\"rule\":\"Coding Rule #10\",\"fix\":\"Free classes should be resolved via the service container, not instantiated by Pro\"}"
    done | $JQ -s '.' > "$TMP/new.json" 2>/dev/null || echo "[]" > "$TMP/new.json"

# ---------------------------------------------------------------------------
# Merge
# ---------------------------------------------------------------------------
$JQ -n --slurpfile i "$TMP/imports.json" \
      --slurpfile w "$TMP/wpdb.json" \
      --slurpfile n "$TMP/new.json" \
'{
  generated_at: (now | strftime("%Y-%m-%dT%H:%M:%SZ")),
  generator: "bin/cleanup-boundary-check.sh",
  violations: ($i[0] + $w[0] + $n[0]),
  counts: {
    direct_free_imports: ($i[0] | length),
    direct_wpdb_on_free_tables: ($w[0] | length),
    new_free_class: ($n[0] | length),
    total: ($i[0] + $w[0] + $n[0] | length)
  }
}' > "$OUT_FILE"

echo "Wrote $OUT_FILE"
$JQ '.counts' "$OUT_FILE"
