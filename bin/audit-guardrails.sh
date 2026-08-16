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

# ── G6: admin writes must not run inside a render callback ──────────────────
# A save that redirects cannot run at render time: admin body output has begun,
# wp_safe_redirect() warns "headers already sent" and the exit after it ends the
# request where it stands. The record IS written; only the navigation fails, so
# the user sits on the form they just submitted and often saves again.
# Coupons hit this (9927893041), then Badges (10199668750) — a sweep found four
# pages in the same shape. The fix is to capture the hook from
# add_submenu_page() and handle writes on `load-{$hook}`.
echo "G6 — admin writes run before output (add_submenu_page + redirect needs load-{hook})"
G6_HITS=""
for base in "$FREE_DIR" "$PRO_DIR"; do
  [ -z "$base" ] && continue
  [ -d "$base/includes" ] || continue
  while IFS= read -r f; do
    [ -z "$f" ] && continue
    grep -q "add_submenu_page" "$f" || continue
    grep -qE "wp_safe_redirect|wp_redirect" "$f" || continue
    # Any hook that runs BEFORE admin body output is sanctioned:
    # `load-{$hook}` (per-screen), `admin_init`, or `admin_post_*`. The Setup
    # Wizard uses admin_init priority 1 and class-admin.php uses admin_post_*;
    # both are correct and must not be flagged.
    #
    # Must be a real add_action() — matching the bare hook name also matched
    # the DOCBLOCKS describing the pattern, which exempted every file that
    # merely talked about it and made this check pass on a genuine regression.
    grep -qE "add_action\(\s*[\"']load-\{\\$|add_action\(\s*[\"']admin_init[\"']|add_action\(\s*[\"']admin_post_" "$f" && continue
    G6_HITS="$G6_HITS
    $(basename "$f")"
  done <<< "$(grep -rlE "add_submenu_page" "$base/includes" 2>/dev/null || true)"
done
if [ -n "${G6_HITS// /}" ]; then
  violation "admin page redirects with no load-{\$hook} handler — the save will lose its headers:$G6_HITS"
else
  ok "every admin page that redirects handles its writes on load-{\$hook}"
fi

# ── G7: admin list screens must paginate ────────────────────────────────────
# An unpaginated list is a scale bug that only shows up on a customer's site.
# The Moderators screen rendered 60 rows in one table while its card recorded
# "not at scale today (6 users)" (10199612602).
echo "G7 — admin list screens paginate"
G7_HITS=""
for base in "$FREE_DIR" "$PRO_DIR"; do
  [ -z "$base" ] && continue
  [ -d "$base/includes" ] || continue
  while IFS= read -r f; do
    [ -z "$f" ] && continue
    # Only screens that actually render a table of records.
    grep -q "add_submenu_page" "$f" || continue
    grep -qE "listora-table|wp-list-table|<table" "$f" || continue
    # Setup wizards are step-based FORMS. Their tables are data-entry grids
    # (rows of inputs the owner fills in) and their queries are existence
    # checks with posts_per_page => 1 — there is no record set to page.
    # Named exemption rather than a looser rule, so it stays reviewable.
    case "$(basename "$f")" in class-setup-wizard.php) continue ;; esac
    # Must actually query a record set — the Pro promotion page renders a
    # marketing comparison table with nothing to page.
    grep -qE "get_posts\(|get_users\(|->get_results\(|->get_col\(" "$f" || continue
    grep -qE "paginate_links|listora-pagination" "$f" && continue
    G7_HITS="$G7_HITS
    $(basename "$f")"
  done <<< "$(grep -rlE "add_submenu_page" "$base/includes" 2>/dev/null || true)"
done
if [ -n "${G7_HITS// /}" ]; then
  violation "admin list screen renders a table with no pager — records past page one are unreachable:$G7_HITS"
else
  ok "admin list screens paginate"
fi

# ── G8: icon pickers cannot offer what the renderer cannot draw ─────────────
# The Type Editor offered its own hardcoded list and the taxonomy picker the
# entire Lucide set, against a 42-icon PHP map. Anything outside the map
# rendered as an empty string — label visible, icon silently gone, no error
# (10194825231, 10198996635).
echo "G8 — icon pickers constrained to the renderable set"
G8_FAIL=""
grep -q "function wb_listora_get_icon_choices" "$FREE_DIR/includes/class-template-helpers.php" \
  || G8_FAIL="$G8_FAIL no-canonical-icon-list-helper"
grep -q "listoraIconChoices" "$FREE_DIR/assets/js/admin/taxonomy-fields.js" \
  || G8_FAIL="$G8_FAIL taxonomy-picker-not-reading-canonical-list"
# The call site, not the definition — grepping the bare name also matched
# `private static function icon_choices()` and so passed on a real regression.
grep -qE "foreach \(\s*self::icon_choices\(\)" "$FREE_DIR/includes/admin/class-type-editor.php" \
  || G8_FAIL="$G8_FAIL type-editor-not-reading-canonical-list"
if [ -n "$G8_FAIL" ]; then
  violation "an icon picker can offer an icon the frontend cannot draw:$G8_FAIL"
else
  ok "both pickers build options from the renderer's own map"
fi

# ── G9: Pro must not use client-side Lucide on the frontend ────────────────
# lucide.min.js is enqueued only in wp-admin, so `<i data-lucide>` on a frontend
# surface stays empty forever: window.lucide is undefined, no console error, no
# fallback (10199529746). Frontend icons are server-rendered via
# wb_listora_render_icon().
echo "G9 — no client-side Lucide on frontend surfaces"
G9_HITS=""
if [ -n "$PRO_DIR" ] && [ -d "$PRO_DIR/includes" ]; then
  # Inspect the BODY of each frontend renderer. Filtering matched LINES by
  # keyword missed a data-lucide sitting anywhere else inside the method,
  # which is most of it — the check passed on a deliberate regression.
  BADGES_FILE="$PRO_DIR/includes/features/class-badges.php"
  if [ -f "$BADGES_FILE" ]; then
    for METHOD in render_card_badges render_detail_badges; do
      BODY="$(awk "/function ${METHOD}\\(/,/^\t\}/" "$BADGES_FILE" 2>/dev/null || true)"
      # Strip comments first: the fix itself documents what it replaced, and
      # a detector that trips on the note explaining the bug is a detector
      # nobody will keep.
      BODY="$(printf '%s' "$BODY" | grep -vE "^\s*(//|\*|/\*|#)" || true)"
      if printf '%s' "$BODY" | grep -q "data-lucide"; then
        G9_HITS="$G9_HITS
    class-badges.php::${METHOD}()"
      fi
    done
  fi
  # Any Pro template is frontend by definition.
  TPL_HITS="$(grep -rn "data-lucide" "$PRO_DIR/templates" 2>/dev/null || true)"
  [ -n "$TPL_HITS" ] && G9_HITS="$G9_HITS
$TPL_HITS"
fi
if [ -n "${G9_HITS// /}" ]; then
  violation "frontend markup uses <i data-lucide> — lucide is admin-only, so it renders nothing:$G9_HITS"
else
  ok "frontend icons are server-rendered"
fi

# ── G10: a hook fired on one path must fire on its sibling ─────────────────
# The wp-admin Claims screen changed claim status without firing
# wb_listora_after_update_claim — only the REST path did — so every listener
# missed every admin approval, including the audit trail (10199419982).
echo "G10 — claim status transitions fire the canonical hook on every path"
G10_FAIL=""
grep -q "wb_listora_after_update_claim" "$FREE_DIR/includes/admin/class-admin.php" \
  || G10_FAIL="$G10_FAIL admin-claims-path-does-not-fire-after_update_claim"
if [ -n "$G10_FAIL" ]; then
  violation "a claim transition path does not fire the canonical hook:$G10_FAIL"
else
  ok "admin and REST claim paths both fire wb_listora_after_update_claim"
fi

# ── G11: consent + filter bases that must not be empty ────────────────────
# Two dead-write-path regressions with the same signature: a value is stored and
# then never read. agree_terms was never checked server-side (10195308842), and
# every consumer of wb_listora_review_criteria passed an EMPTY base so the
# stored criteria were ignored (10199712310).
echo "G11 — stored values are actually read (terms consent, review criteria)"
G11_FAIL=""
grep -q "agree_terms" "$FREE_DIR/includes/rest/class-submission-controller.php" \
  || G11_FAIL="$G11_FAIL submission-controller-does-not-check-agree_terms"
if grep -rnE "apply_filters\(\s*'wb_listora_review_criteria',\s*array\(\)" \
  "$FREE_DIR/includes" "$FREE_DIR/blocks" "$FREE_DIR/templates" >/dev/null 2>&1; then
  G11_FAIL="$G11_FAIL review-criteria-filtered-from-an-empty-base"
fi
if [ -n "$G11_FAIL" ]; then
  violation "a stored value is written and never read:$G11_FAIL"
else
  ok "terms consent enforced; review criteria filtered from the stored value"
fi

# ── G12: every dispatched webhook event is a registered trigger ─────────────
# coupon_redeemed and need_posted were dispatched by real handlers for
# releases while being absent from Pro's EVENTS constant, so the admin UI
# never offered them, no subscriber could exist, and every dispatch built a
# payload and discarded it. EVENTS is gone; this is what keeps it gone.
echo "G12 — dispatched events are registered triggers"
G12_FAIL=""
if [ -n "$PRO_DIR" ] && [ -f "$PRO_DIR/includes/features/class-outgoing-webhooks.php" ]; then
  DISPATCHED="$(grep -oE "dispatch_event\(\s*'[a-z_]+'" \
    "$PRO_DIR/includes/features/class-outgoing-webhooks.php" \
    | sed -E "s/.*'([a-z_]+)'/\1/" | sort -u)"
  DECLARED="$(grep -horE --include='*.php' "'name'\s*=>\s*'[a-z_]+'" \
    "$FREE_DIR/includes/automation/" "$PRO_DIR/includes/automation/" 2>/dev/null \
    | sed -E "s/.*'([a-z_]+)'/\1/" | sort -u)"
  for ev in $DISPATCHED; do
    echo "$DECLARED" | grep -qx "$ev" || G12_FAIL="$G12_FAIL $ev"
  done
fi
if [ -n "$G12_FAIL" ]; then
  violation "dispatched but not registered (nobody can subscribe):$G12_FAIL"
else
  ok "every dispatched event is a registered trigger"
fi

# ── G14: every registered trigger has a schema file on disk ─────────────────
# A published webhook contract that points at a schema which doesn't exist
# is worse than an undocumented one — the admin UI advertises it, a
# subscriber maps fields against it, and nothing ever validates the shape.
# --include='*.php' matters here the same way it mattered for G12: this scan
# is recursive over includes/automation/, and a stray .bak/.orig left behind
# by a merge conflict or an editor swap file would otherwise silence the
# check by never matching a real 'schema' => '...' declaration OR — worse —
# by matching a stale one and reporting a false failure. Scope to PHP only.
echo "G14 — every trigger has a schema on disk"
G14_FAIL=""
for dir in "$FREE_DIR" "$PRO_DIR"; do
  [ -z "$dir" ] && continue
  [ -d "$dir/includes/automation" ] || continue
  while IFS= read -r schema; do
    [ -z "$schema" ] && continue
    found=""
    for base in "$FREE_DIR" "$PRO_DIR"; do
      [ -z "$base" ] && continue
      [ -f "$base/includes/automation/schemas/$schema" ] && found="yes"
    done
    [ -n "$found" ] || G14_FAIL="$G14_FAIL $schema"
  done <<< "$(grep -rhoE --include='*.php' "'schema'\s*=>\s*'[a-z0-9_.]+'" "$dir/includes/automation/" 2>/dev/null \
        | sed -E "s/.*'([a-z0-9_.]+)'/\1/" | sort -u)"
done
if [ -n "$G14_FAIL" ]; then
  violation "trigger declares a schema file that does not exist:$G14_FAIL"
else
  ok "every declared schema file exists"
fi

# ── G15: a published schema file is immutable ───────────────────────────────
# The one check that protects live integrations. A subscriber whose payload
# shape changes under them does not get an error — their automation quietly
# stops mapping a field, and they find out days later from missing data.
# Discovery serves schemas by filename from the registry's `schema` field, so
# ANY byte change to a schema that already existed at origin/main is the
# violation — there is no escape hatch where adding a `vN+1.json` sibling
# excuses also mutating the original. (An earlier version of this check only
# required the vN+1 sibling to exist, without checking the original was left
# alone — a developer could edit v1 in place, add a v2, and get a green
# build while the mutated v1 shipped. That's the exact failure this check
# exists to catch, so the escape hatch was removed, not tightened.)
# Compares each side's schemas against ITS OWN origin/main, so it only fires
# on a real edit to a schema that was already published — a schema added on
# this branch (never on origin/main) is a new event or a deliberate first
# version and is exempt. Runs against both Free and Pro; Pro ships 8 of the
# 34 schemas and a schema edited in place there is exactly as much of a
# broken contract as one edited in Free.
echo "G15 — published schemas are immutable"
G15_FAIL=""
G15_RAN=""
check_schema_immutable() {
  local base="$1"
  [ -z "$base" ] && return
  git -C "$base" rev-parse --verify origin/main >/dev/null 2>&1 || return
  G15_RAN="yes"
  local changed f file_base event ver next
  changed="$(git -C "$base" diff --name-only origin/main...HEAD -- 'includes/automation/schemas/*.json' 2>/dev/null)"
  while IFS= read -r f; do
    [ -z "$f" ] && continue
    # A NEW schema file is fine — it is a new event or a deliberate new
    # version. Only one that already existed at origin/main (and is
    # therefore reported by the diff above as changed) is the problem — no
    # exception for also having added a new version file alongside it.
    if git -C "$base" cat-file -e "origin/main:$f" 2>/dev/null; then
      file_base="$(basename "$f")"
      event="${file_base%%.v*}"
      ver="$(echo "$file_base" | sed -E 's/.*\.v([0-9]+)\.json/\1/')"
      next="$((ver + 1))"
      G15_FAIL="$G15_FAIL ${file_base}(revert it, add ${event}.v${next}.json instead, repoint the trigger's schema+version)"
    fi
  done <<< "$changed"
}
check_schema_immutable "$FREE_DIR"
check_schema_immutable "$PRO_DIR"
if [ -n "$G15_FAIL" ]; then
  violation "a published schema was modified in place:$G15_FAIL"
elif [ -n "$G15_RAN" ]; then
  ok "no published schema modified in place"
else
  ok "origin/main not resolvable in Free or Pro — G15 skipped (no base ref to diff against)"
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
