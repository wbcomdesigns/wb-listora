#!/usr/bin/env bash
# Throwaway WordPress PHPUnit environment in Docker — idempotent.
#
#   bash bin/ci-test-db.sh up     # container + database + WP test suite; prints WP_TESTS_DIR
#   bash bin/ci-test-db.sh down   # remove the container
#
# Why: local CI skipped PHPUnit whenever no WP test suite was installed, which
# on a laptop is always - so the suite gated nothing and bugs it would have
# caught shipped (card 10100523205 shipped without a runnable test). This makes
# the suite runnable on any machine with Docker, with no MySQL to install.
#
# Run from a plugin root. Free and Pro share one container and one WordPress
# core download; each plugin gets its own database and test-suite config, so
# the two suites never write over each other.
#
# Overrides: LISTORA_CI_DB_CONTAINER, LISTORA_CI_DB_PORT, LISTORA_CI_DB_IMAGE,
# LISTORA_CI_SLUG (defaults to the current directory name).
#
# The LAST line of `up` output is always the WP_TESTS_DIR path (or empty on
# failure), so callers can capture it: WP_TESTS_DIR=$(bash bin/ci-test-db.sh up | tail -1)
set -uo pipefail

ACTION="${1:-up}"
NAME="${LISTORA_CI_DB_CONTAINER:-listora-ci-db}"
PORT="${LISTORA_CI_DB_PORT:-33306}"
IMAGE="${LISTORA_CI_DB_IMAGE:-mariadb:10.11}"
SLUG="${LISTORA_CI_SLUG:-$(basename "$PWD")}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE="${TMPDIR:-/tmp}"
BASE="${BASE%/}"
DB_NAME="$(printf '%s' "$SLUG" | tr -c 'a-zA-Z0-9\n' '_')_tests"
TESTS_DIR="$BASE/${SLUG}-tests-lib"
CORE_DIR="$BASE/wb-listora-ci-wordpress"

log() { echo "ci-test-db: $*" >&2; }

if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
	log "Docker is not available (install or start it) - cannot provision a test database."
	echo ""
	exit 2
fi

if [ "$ACTION" = "down" ]; then
	docker rm -f "$NAME" >/dev/null 2>&1 && log "removed $NAME"
	exit 0
fi

# 1. Container: reuse a running one, restart a stopped one, create otherwise.
state="$(docker inspect -f '{{.State.Running}}' "$NAME" 2>/dev/null || true)"
if [ "$state" = "false" ]; then
	docker start "$NAME" >/dev/null || { log "could not start $NAME"; echo ""; exit 1; }
elif [ -z "$state" ]; then
	log "starting $IMAGE as $NAME on 127.0.0.1:$PORT"
	docker run -d --name "$NAME" -e MARIADB_ROOT_PASSWORD=root -p "127.0.0.1:${PORT}:3306" "$IMAGE" >/dev/null \
		|| { log "docker run failed"; echo ""; exit 1; }
fi

# 2. Wait for the server to accept connections.
ready=""
for _ in $(seq 1 60); do
	if docker exec "$NAME" mariadb -uroot -proot -e 'SELECT 1' >/dev/null 2>&1; then
		ready=1
		break
	fi
	sleep 1
done
if [ -z "$ready" ]; then
	log "database did not become ready in 60s"
	echo ""
	exit 1
fi

docker exec "$NAME" mariadb -uroot -proot -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`" >/dev/null \
	|| { log "could not create database $DB_NAME"; echo ""; exit 1; }

# 3. WP test suite for this plugin, installed once and pointed at this database.
# Reused only when complete AND configured for this exact database and port -
# a half-finished install (svn failed, download cut off) is wiped and redone,
# or it would be reused and skip PHPUnit forever.
if [ ! -f "$TESTS_DIR/includes/functions.php" ] \
	|| ! grep -q "'${DB_NAME}'" "$TESTS_DIR/wp-tests-config.php" 2>/dev/null \
	|| ! grep -q "127.0.0.1:${PORT}" "$TESTS_DIR/wp-tests-config.php" 2>/dev/null; then
	if ! command -v svn >/dev/null 2>&1; then
		log "svn is required to download the WP test suite (macOS: brew install subversion)"
		echo ""
		exit 1
	fi
	log "installing WordPress test suite into $TESTS_DIR (first run downloads WordPress)"
	rm -rf "$TESTS_DIR"
	if ! WP_TESTS_DIR="$TESTS_DIR" WP_CORE_DIR="$CORE_DIR" \
		bash "$SCRIPT_DIR/install-wp-tests.sh" "$DB_NAME" root root "127.0.0.1:${PORT}" latest true >&2 \
		|| [ ! -f "$TESTS_DIR/includes/functions.php" ]; then
		log "install-wp-tests.sh failed - removing the partial install"
		rm -rf "$TESTS_DIR"
		echo ""
		exit 1
	fi
fi

echo "$TESTS_DIR"
