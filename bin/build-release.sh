#!/usr/bin/env bash
#
# Build a clean WordPress plugin release zip from the current working tree.
#
# Usage:  bin/build-release.sh
# Output: dist/wb-listora-<version>.zip
#
# Excluded from the zip: dev tooling, source maps, tests, plans, dotfiles,
# vendor dev deps, build configs. What ships: PHP runtime + built JS/CSS +
# templates + languages + readme + license.

set -euo pipefail

SKIP_BROWSER_SMOKE=0
for arg in "$@"; do
  case "$arg" in
    --skip-browser-smoke) SKIP_BROWSER_SMOKE=1 ;;
    *) echo "unknown flag: $arg" >&2; exit 2 ;;
  esac
done

SLUG="wb-listora"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${PLUGIN_DIR}/dist"
STAGE_DIR="${DIST_DIR}/${SLUG}"

VERSION="$(grep -m1 "Version:" "${PLUGIN_DIR}/${SLUG}.php" | awk -F': *' '{print $2}' | tr -d ' \r\n')"

if [ -z "${VERSION}" ]; then
  echo "✗ Could not read Version from ${SLUG}.php"
  exit 1
fi

echo "→ Building ${SLUG} v${VERSION}"

cd "${PLUGIN_DIR}"

# 1. Install runtime PHP deps without dev packages
if [ -f composer.json ]; then
  echo "→ composer install --no-dev"
  composer install --no-dev --optimize-autoloader --quiet
fi

# 2. Build JS/CSS assets
if [ -f package.json ]; then
  echo "→ npm install + build"
  if [ ! -d node_modules ]; then
    npm install --silent
  fi
  npm run build --silent
fi

# 2b. Refresh the compiled PHP translation catalogues.
#
# WordPress 6.5+ loads `.l10n.php` in PREFERENCE to `.mo`, so shipping a
# `.l10n.php` older than its `.po` ships the OLD translations no matter how
# correct the .po and .mo are — and every catalogue check still reports 100%.
# The i18n toolchain's compile step does not emit these despite `makePhp: true`
# in .wbcom-i18n.json, so the release regenerates them itself rather than
# trusting that someone remembered. coding-rules Rule 11 fails the build if
# they are stale, but this makes the zip correct by construction.
if [ -d languages ] && command -v wp >/dev/null 2>&1; then
  echo "→ wp i18n make-php languages"
  wp i18n make-php languages --quiet || echo "  (make-php failed — check Rule 11 before shipping)"
fi

# 3. Stage clean copy
rm -rf "${DIST_DIR}"
mkdir -p "${STAGE_DIR}"

rsync -a --delete \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.gitignore' \
  --exclude='.gitattributes' \
  --exclude='.gitmodules' \
  --exclude='.gitkeep' \
  --exclude='.editorconfig' \
  --exclude='.distignore' \
  --exclude='.wbcom-i18n.json' \
  --exclude='.DS_Store' \
  --exclude='.phpunit.result.cache' \
  --exclude='.idea/' \
  --exclude='.vscode/' \
  --exclude='/tools/' \
  --exclude='node_modules/' \
  --exclude='/tests/' \
  --exclude='/plan/' \
  --exclude='/plans/' \
  --exclude='/audit/' \
  --exclude='.contract-audit-baseline.json' \
  --exclude='/docs/' \
  --exclude='/dist/' \
  --exclude='/bin/' \
  --exclude='/src/' \
  --exclude='*.map' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='webpack.config.js' \
  --exclude='phpcs.xml' \
  --exclude='phpcs.xml.dist' \
  --exclude='phpstan.neon' \
  --exclude='phpstan-baseline.neon' \
  --exclude='phpstan-bootstrap.php' \
  --exclude='phpstan-stubs/' \
  --exclude='phpunit.xml' \
  --exclude='phpunit.xml.dist' \
  --exclude='wpml-config.xml.bak' \
  --exclude='CLAUDE.md' \
  --exclude='*.log' \
  --exclude='wp-content/' \
  --exclude='libs/wbcom-credits-sdk/CHANGELOG.md' \
  --exclude='libs/wbcom-credits-sdk/README.md' \
  --exclude='libs/wbcom-credits-sdk/composer.json' \
  --exclude='/vendor/wbcom-credits-sdk/' \
  --exclude='/vendor/wbcom-credits-sdk' \
  ./ "${STAGE_DIR}/"

# 3.1. Credits SDK zip-leak guard (1.1.0 regression).
# In 1.1.0 the Wbcom Credits SDK lived as a git submodule at
# vendor/wbcom-credits-sdk and leaked its dev artifacts into the customer
# zip. It has since been re-homed composer-free to libs/wbcom-credits-sdk/.
# The rsync excludes above (a) hard-drop any stray legacy
# vendor/wbcom-credits-sdk path so the leak can never recur, and (b) strip
# the live SDK's dev-only docs (CHANGELOG/README/composer.json) from the
# shipped libs/ copy. Runtime SDK code under libs/wbcom-credits-sdk/src/
# and templates/ still ships — it powers credit features.

# Re-restore composer dev deps after build
if [ -f composer.json ]; then
  composer install --quiet
fi

# 3.5. Browser smoke gate — refuses to package unless a fresh green smoke
# report exists. Protects first-hand customer experience: no release ships
# unless a run of docs/qa/AGENT_SMOKE_RUNBOOK.md (dispatched to Sonnet via
# the wb-listora-pro-smoke skill in wb-listora-pro/.claude/skills/) reported
# zero failures and zero debug_log_issues.
SMOKE_REPORT="${PLUGIN_DIR}/docs/qa/.last-smoke-pass.json"
echo "→ Smoke gate"
if [ "${SKIP_BROWSER_SMOKE}" -eq 1 ]; then
  echo "  WARN: browser smoke gate skipped (--skip-browser-smoke). Not for customer releases."
elif [ ! -f "${SMOKE_REPORT}" ]; then
  echo "  FAIL: no browser smoke report at ${SMOKE_REPORT}" >&2
  echo "        Run the wb-listora-pro-smoke skill first to generate it." >&2
  echo "        Emergency only: rerun with --skip-browser-smoke." >&2
  exit 30
else
  REPORT_VERSION="$(grep -oE '"release_version"[[:space:]]*:[[:space:]]*"[^"]+"' "${SMOKE_REPORT}" | head -1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' || true)"
  if [ "${REPORT_VERSION}" != "${VERSION}" ]; then
    echo "  FAIL: smoke report version (${REPORT_VERSION}) doesn't match release version (${VERSION})" >&2
    echo "        Rerun the wb-listora-pro-smoke skill against HEAD before packaging." >&2
    exit 30
  fi
  if grep -qE '"failures"[[:space:]]*:[[:space:]]*\[[[:space:]]*\{' "${SMOKE_REPORT}"; then
    echo "  FAIL: smoke report has failures. Fix them before packaging." >&2
    exit 30
  fi
  if grep -qE '"debug_log_issues"[[:space:]]*:[[:space:]]*\[[[:space:]]*\{' "${SMOKE_REPORT}"; then
    echo "  FAIL: smoke report recorded debug.log entries during the walk. Fix before packaging." >&2
    exit 30
  fi

  # Coverage gate.
  #
  # The two checks above only ask "did the walk find anything?" — never "did the
  # walk actually look?". The 2026-08-11 combo run finished 14 passed / 3 failed
  # / 133 SKIPPED (~10% coverage) with sections B and F executing zero rows, and
  # once its three failures were fixed it would have opened this gate on a walk
  # that never exercised upgrade or cross-browser at all.
  #
  # A thin walk is not a green walk. Refuse to package when most of the runbook
  # was skipped, or when any section ran nothing whatsoever.
  if command -v python3 >/dev/null 2>&1; then
    COVERAGE_ERR="$(python3 - "${SMOKE_REPORT}" <<'PY'
import json, sys
try:
    r = json.load(open(sys.argv[1]))
except Exception as e:
    print(f"smoke report is not valid JSON: {e}")
    sys.exit(0)

sections = r.get("sections") or {}
if not sections:
    print("smoke report has no sections{} — cannot prove coverage")
    sys.exit(0)

ran = skipped = 0
empty = []
for name, s in sections.items():
    p, f, k = s.get("pass", 0), s.get("fail", 0), s.get("skipped", 0)
    ran += p + f
    skipped += k
    if p + f == 0 and k > 0:
        empty.append(f"{name} ({k} skipped, 0 executed)")

if empty:
    print("these sections executed nothing:\n        " + "\n        ".join(empty))
elif ran and skipped > ran:
    pct = 100.0 * ran / (ran + skipped)
    print(f"only {ran} of {ran + skipped} rows ran ({pct:.0f}% coverage)")
elif not ran:
    print("no rows executed at all")
PY
)"
    if [ -n "${COVERAGE_ERR}" ]; then
      echo "  FAIL: smoke walk did not cover enough to gate a release." >&2
      echo "        ${COVERAGE_ERR}" >&2
      echo "        Run the [CORE] rows in docs/qa/AGENT_SMOKE_RUNBOOK.md, then re-walk the rest." >&2
      exit 30
    fi
  else
    echo "  WARN: python3 unavailable — coverage gate skipped, only failures were checked."
  fi

  echo "  smoke report OK"
fi

# 4. Zip
ZIP_PATH="${DIST_DIR}/${SLUG}-${VERSION}.zip"
cd "${DIST_DIR}"
zip -rq "${ZIP_PATH}" "${SLUG}/"

# 4.1. Zip-content assertion — the 1.1.0 zip-leak guard, verified.
# Excludes can silently rot (a rename, a new submodule path, an rsync flag
# change). This re-reads the finished zip and refuses to ship if the leak
# recurred, or if the live SDK loader the plugin depends on went missing.
echo "→ Zip-content assertion"
ZIP_LIST="$(unzip -Z1 "${ZIP_PATH}")"

# (a) No legacy submodule path may appear anywhere in the zip.
if printf '%s\n' "${ZIP_LIST}" | grep -qE '(^|/)vendor/wbcom-credits-sdk(/|$)'; then
  echo "  FAIL: vendor/wbcom-credits-sdk leaked into the zip (1.1.0 regression)." >&2
  echo "        Remove the stray submodule path; the SDK lives at libs/wbcom-credits-sdk/." >&2
  rm -f "${ZIP_PATH}"
  exit 31
fi

# (b) The live SDK dev artifacts must not ship.
if printf '%s\n' "${ZIP_LIST}" | grep -qE 'libs/wbcom-credits-sdk/(CHANGELOG\.md|README\.md|composer\.json)$'; then
  echo "  FAIL: bundled SDK dev artifacts (CHANGELOG/README/composer.json) leaked into the zip." >&2
  rm -f "${ZIP_PATH}"
  exit 31
fi

# (c) Every bundled library that ships a src/ tree MUST land that src/ in the
# zip. The 1.2.1 regression shipped the SDK loaders but an unanchored .distignore
# `src` glob stripped EVERY libs/**/src — disabling credit AND license/update
# features on every fresh install. The old guard only checked the loader file
# (which the `src` glob never touched), so it sailed straight past the real bug.
# This walks each libs/*/src present in the staged tree and refuses to ship
# unless its PHP source actually made it into the finished zip.
for sdk_src_dir in "${STAGE_DIR}"/libs/*/src; do
  [ -d "${sdk_src_dir}" ] || continue
  lib_name="$(basename "$(dirname "${sdk_src_dir}")")"
  rel="libs/${lib_name}/src/"
  if ! printf '%s\n' "${ZIP_LIST}" | grep -qE "${rel}.+\.php\$"; then
    echo "  FAIL: bundled library source ${rel} is in the staged tree but missing from the zip." >&2
    echo "        A .distignore/exclude glob stripped it — the feature it powers would break (1.2.1 regression)." >&2
    rm -f "${ZIP_PATH}"
    exit 31
  fi
done
echo "  zip contents OK"

# 5. Cleanup stage
rm -rf "${STAGE_DIR}"

SIZE_BYTES="$(stat -f%z "${ZIP_PATH}" 2>/dev/null || stat -c%s "${ZIP_PATH}")"
SIZE_KB="$((SIZE_BYTES / 1024))"

echo ""
echo "✓ ${SLUG}-${VERSION}.zip — ${SIZE_KB} KB"
echo "  ${ZIP_PATH}"
