#!/usr/bin/env bash
# Prints "<count> <repo-relative path>" for every PHP file that reaches the Credits SDK or its ledger
# table without going through the credit layer (includes/credits-helpers.php, Core\Charge).
# Matches in CALL context only: a bare 'wb-listora' is also the text domain, so it never counts.
# Usage: credits-layer-scan.sh <plugin-root>
set -uo pipefail
ROOT="$1"
cd "$ROOT" || exit 1
PATTERN="Credits\\\\(Credits|Ledger|Gateways|Adapters|Consumer|Money)\\b|Credits::[a-z_]+\(|credit_ledger['\"]"
grep -rEc "$PATTERN" --include='*.php' includes blocks templates *.php 2>/dev/null \
  | grep -v ':0$' \
  | grep -vE '^(includes/credits-helpers\.php|includes/core/class-charge\.php|includes/(db/)?class-(migrator|activator)\.php|includes/db/class-pro-migrator\.php|includes/privacy/privacy-helpers\.php|libs/)' \
  | awk -F: '{print $2" "$1}' | sort -k2
