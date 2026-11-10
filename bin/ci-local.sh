#!/usr/bin/env bash
# Alias so the shared release gate (which looks for ci-local.sh) finds the repo CI entry point.
exec bash "$(dirname "${BASH_SOURCE[0]}")/local-ci.sh" "$@"
