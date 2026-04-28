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
  --exclude='.DS_Store' \
  --exclude='.phpunit.result.cache' \
  --exclude='.idea/' \
  --exclude='.vscode/' \
  --exclude='tools/' \
  --exclude='node_modules/' \
  --exclude='tests/' \
  --exclude='plans/' \
  --exclude='docs/' \
  --exclude='dist/' \
  --exclude='bin/' \
  --exclude='src/' \
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
  --exclude='seed-demo.php' \
  --exclude='wp-content/' \
  ./ "${STAGE_DIR}/"

# Re-restore composer dev deps after build
if [ -f composer.json ]; then
  composer install --quiet
fi

# 4. Zip
ZIP_PATH="${DIST_DIR}/${SLUG}-${VERSION}.zip"
cd "${DIST_DIR}"
zip -rq "${ZIP_PATH}" "${SLUG}/"

# 5. Cleanup stage
rm -rf "${STAGE_DIR}"

SIZE_BYTES="$(stat -f%z "${ZIP_PATH}" 2>/dev/null || stat -c%s "${ZIP_PATH}")"
SIZE_KB="$((SIZE_BYTES / 1024))"

echo ""
echo "✓ ${SLUG}-${VERSION}.zip — ${SIZE_KB} KB"
echo "  ${ZIP_PATH}"
