#!/usr/bin/env bash
# The bundled Credits SDK must be an exact copy of the SDK commit named in
# libs/wbcom-credits-sdk/.bundled-from. Several plugins bundle the SDK and the
# newest copy serves them all, so a hand edit here would ship code that no SDK
# tag contains. Fix upstream, tag, re-bundle.
#
# Needs a clone of vapvarun/wbcom-credits-sdk: $WBCOM_CREDITS_SDK_REPO, or
# ~/code/wbcom-credits-sdk. Without one the check is skipped, not failed.
set -euo pipefail

bundle="libs/wbcom-credits-sdk"
repo="${WBCOM_CREDITS_SDK_REPO:-$HOME/code/wbcom-credits-sdk}"
sha="$(tr -d '[:space:]' < "$bundle/.bundled-from")"

if [ ! -d "$repo/.git" ]; then
  echo "skip: no SDK clone at $repo (set WBCOM_CREDITS_SDK_REPO)"
  exit 0
fi
git -C "$repo" fetch -q --tags 2>/dev/null || true
if ! git -C "$repo" cat-file -e "$sha^{commit}" 2>/dev/null; then
  echo "FAIL: .bundled-from names $sha, which is not a commit in $repo"
  exit 1
fi

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
git -C "$repo" archive "$sha" | tar -x -C "$tmp"
# Developer files the bundle leaves out.
rm -rf "$tmp"/{.github,bin,tests,phpstan.neon.dist,phpunit.xml.dist,PORTFOLIO-PLAN.md,REVIEW-HANDOFF.md}

if ! diff -rq -x .bundled-from "$tmp" "$bundle"; then
  echo "FAIL: $bundle differs from SDK commit $sha. Re-bundle from the tag; never edit the copy."
  exit 1
fi
echo "ok: $bundle matches SDK $(git -C "$repo" describe --tags "$sha" 2>/dev/null || echo "$sha")"
