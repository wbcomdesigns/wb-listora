#!/usr/bin/env bash
# bin/audit-guardrails.sh — static drift / boundary / gating guardrails.
#
# Run via: composer guardrails  (also runs as a stage inside `composer ci`)
#
# These checks encode the systemic failure patterns surfaced by the 2026-07
# product audit so buyers stop re-hitting the same classes of bug:
#
#   G1  dual-source drift   — reading rating from never-written post-meta
#   G2  Free/Pro boundary   — Free reading a Pro-namespaced option directly
#   G3  schema drift        — Free's and Pro's payments DDL out of sync
#   G4  config-state gating — credit surfaces not routed through the canonical
#                             member-credits gate (leak on Free-only)
#
# Each check appends to $VIOLATIONS and the script exits non-zero if any fail.
# Exit 0 = all guardrails green.

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FREE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PRO_DIR="$(cd "$FREE_DIR/../wb-listora-pro" 2>/dev/null && pwd || true)"

VIOLATIONS="$(mktemp)"
trap 'rm -f "$VIOLATIONS"' EXIT
violation() { echo "  ✗ $*"; echo "v" >> "$VIOLATIONS"; }
ok() { echo "  ✓ $*"; }

echo "=== WB Listora audit guardrails ==="

# ── G1: rating must be read from search_index, never from post-meta ──────────
# _listora_average_rating / _listora_review_count are NEVER written; the canonical
# source is the search_index table (Free) via wb_listora_card_index_row(). Reading
# them as post-meta silently returns empty (AUDIT-H6).
echo "G1 — rating dual-store (no get_post_meta on never-written rating keys)"
G1_HITS=""
for base in "$FREE_DIR" "$PRO_DIR"; do
  [ -z "$base" ] && continue
  # Only PHP under includes/blocks/templates; wpml-config.xml + docs are exempt.
  HIT="$(grep -rnE "get_post_meta\s*\([^)]*_listora_(average_rating|review_count)" \
        "$base/includes" "$base/blocks" "$base/templates" 2>/dev/null || true)"
  [ -n "$HIT" ] && G1_HITS="$G1_HITS
$HIT"
done
if [ -n "${G1_HITS// /}" ]; then
  violation "get_post_meta() reads a never-written rating key — use wb_listora_card_index_row():$G1_HITS"
else
  ok "no post-meta rating reads"
fi

# ── G2: Free must not read Pro-namespaced options directly (INV-12c) ─────────
echo "G2 — Free/Pro boundary (Free never reads a wb_listora_pro_* option)"
G2_HIT="$(grep -rnE "get_option\(\s*['\"]wb_listora_pro_" \
        "$FREE_DIR/includes" "$FREE_DIR"/*.php 2>/dev/null || true)"
if [ -n "$G2_HIT" ]; then
  violation "Free reads a Pro option directly — consume a filter instead:
$G2_HIT"
else
  ok "Free reads no Pro options"
fi

# ── G3: payments DDL byte-identical between Free and Pro ─────────────────────
# Both plugins dbDelta the shared payments table; a divergence makes a version
# bump on one side ALTER the table and fight the other (AUDIT-M). Compare the
# normalized column/key body of each CREATE TABLE payments block.
echo "G3 — payments DDL sync (Free activator == Pro migrator)"
if [ -n "$PRO_DIR" ]; then
  extract_payments_ddl() {
    # Pull the lines between "CREATE TABLE {prefix}payments (" and the closing ");",
    # keep only column/key lines, strip leading/trailing whitespace + trailing
    # commas + ENGINE clause so cosmetic differences don't trip the check.
    awk '/CREATE TABLE .*payments \(/{f=1;next} f&&/\)[[:space:]]*(ENGINE=[^ ]+ )?\{?\$charset_collate\}?;/{f=0} f' "$1" \
      | sed -E 's/^[[:space:]]+//; s/[[:space:]]+$//; s/,$//' \
      | grep -vE '^$'
  }
  FREE_DDL="$(extract_payments_ddl "$FREE_DIR/includes/class-activator.php")"
  PRO_DDL="$(extract_payments_ddl "$PRO_DIR/includes/db/class-pro-migrator.php")"
  if [ -z "$FREE_DDL" ] || [ -z "$PRO_DDL" ]; then
    violation "could not extract a payments DDL block from one side (check the CREATE TABLE markers)"
  elif [ "$FREE_DDL" != "$PRO_DDL" ]; then
    violation "Free and Pro payments DDL differ:
$(diff <(echo "$FREE_DDL") <(echo "$PRO_DDL") || true)"
  else
    ok "payments DDL identical"
  fi
else
  ok "Pro not present — payments DDL cross-check skipped"
fi

# ── G4: credit surfaces gated through the canonical member-credits helper ────
# The submission block + user dashboard must decide whether to show credit chrome
# via wb_listora_should_show_member_credits() (Pro active AND a purchase path), not
# a raw class_exists/is_enabled check — the SDK ships in Free, so a raw check leaks
# credit UI on Free-only installs (AUDIT-H8).
echo "G4 — credit-surface gating (renders use wb_listora_should_show_member_credits)"
G4_FAIL=""
for f in "$FREE_DIR/blocks/listing-submission/render.php" "$FREE_DIR/blocks/user-dashboard/render.php"; do
  [ -f "$f" ] || continue
  grep -q "wb_listora_should_show_member_credits" "$f" || G4_FAIL="$G4_FAIL $f"
done
if [ -n "$G4_FAIL" ]; then
  violation "credit-surface render(s) not routed through the canonical gate:$G4_FAIL"
else
  ok "credit surfaces use the canonical gate"
fi

# ── G5: field-options shape contract (BC 10162700303 live-site fatal) ────────
# The Type Editor once wrote plain-string options into _listora_field_groups;
# PHP 8 readers doing $opt['value'] fataled the public submission page.
# PHPStan (level 7) cannot see this — Field::get() returns untyped mixed —
# so the contract is pinned here: the constructor must normalize, the save
# path must normalize, and the editor JS must never write scalar options.
echo "G5 — field-options shape contract (normalize on read + write, no scalar JS writes)"
G5_FAIL=""
grep -Eq "props\['options'\]\s*=\s*self::normalize_options" \
  "$FREE_DIR/includes/core/class-field.php" \
  || G5_FAIL="$G5_FAIL Field-constructor-no-longer-normalizes(class-field.php)"
grep -q "normalize_options" \
  "$FREE_DIR/includes/core/class-listing-type-registry.php" \
  || G5_FAIL="$G5_FAIL save-path-no-longer-normalizes(class-listing-type-registry.php)"
if grep -nE "options\.push\(\s*['\"]|field\.options\[\s*idx\s*\]\s*=\s*this\.value\s*;" \
  "$FREE_DIR/assets/js/admin/type-editor.js" >/dev/null 2>&1; then
  G5_FAIL="$G5_FAIL type-editor.js-writes-scalar-options"
fi
if [ -n "$G5_FAIL" ]; then
  violation "field-options shape contract broken:$G5_FAIL"
else
  ok "options normalized on read + write; editor writes {value,label} only"
fi

# ── Summary ──────────────────────────────────────────────────────────────────
COUNT="$(wc -l < "$VIOLATIONS" | tr -d ' ')"
echo ""
if [ "$COUNT" -gt 0 ]; then
  echo "audit guardrails FAILED: $COUNT violation(s)"
  exit 1
fi
echo "audit guardrails: all green"
exit 0
