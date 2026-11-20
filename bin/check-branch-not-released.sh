#!/usr/bin/env bash
# Fails when a version-named branch (2.1.2) is already released (tag v2.1.2 exists) and has commits
# that tag does not contain. Those commits can never ship under that version: cut the next version
# branch from main. Other branches are not checked. A deliberate re-cut sets ALLOW_RELEASED_BRANCH=1.
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
branch="$(git symbolic-ref --short -q HEAD || true)"
[[ "$branch" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || exit 0
git fetch -q --tags origin 2>/dev/null || true
tag="v${branch}"
git rev-parse -q --verify "refs/tags/${tag}" >/dev/null || exit 0
ahead="$(git rev-list --count "${tag}..HEAD")"
[ "$ahead" -gt 0 ] || exit 0
if [ "${ALLOW_RELEASED_BRANCH:-0}" = "1" ]; then
  echo "check-branch-not-released: ${tag} is released and ${branch} is ${ahead} commit(s) past it; allowed for this re-cut"
  exit 0
fi
echo "✗ ${tag} is already released and branch ${branch} is ${ahead} commit(s) past it."
echo "  Those commits will not ship in ${branch}. Cut the next version branch from main"
echo "  (or set ALLOW_RELEASED_BRANCH=1 for a deliberate re-cut)."
exit 1
