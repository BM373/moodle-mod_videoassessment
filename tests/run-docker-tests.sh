#!/usr/bin/env bash
# Run mod_videoassessment's PHPUnit (and optionally phpcs/grunt) inside the
# already-running moodle-docker dev environment.
#
# Usage:
#   tests/run-docker-tests.sh                # full PHPUnit + phpcs + grunt
#   tests/run-docker-tests.sh phpunit        # just PHPUnit
#   tests/run-docker-tests.sh phpcs          # just phpcs (host-side)
#   tests/run-docker-tests.sh grunt          # just grunt (host-side)
#   tests/run-docker-tests.sh phpunit ::test_xyz   # filter
#
# Avoids round-tripping every change through GitHub Actions during
# active development. The matrix CI is still authoritative for
# pre-merge gating.

set -euo pipefail

CONTAINER="${MOODLE_DOCKER_CONTAINER:-moodle-docker-dev-45-webserver-1}"
PLUGIN_PATH="/var/www/html/mod/videoassessment"
MOODLE_HOST_ROOT="${MOODLE_HOST_ROOT:-/Users/fuwa/workspace/moodle-dev-45/moodle}"
PHPCS_HOME="${PHPCS_HOME:-/tmp/mood-cs}"

run_phpunit() {
    echo "🧪 PHPUnit (mod_videoassessment) inside ${CONTAINER}"
    docker exec "${CONTAINER}" sh -c "
        cd /var/www/html &&
        vendor/bin/phpunit --testsuite mod_videoassessment_testsuite ${1:-}
    "
}

run_phpcs() {
    echo "🔍 phpcs --standard=moodle (host)"
    if [ ! -x "${PHPCS_HOME}/vendor/bin/phpcs" ]; then
        echo "  Install moodle-cs first: cd ${PHPCS_HOME} && composer require moodlehq/moodle-cs"
        return 1
    fi
    cd "${MOODLE_HOST_ROOT}/mod/videoassessment"
    "${PHPCS_HOME}/vendor/bin/phpcs" \
        --standard=moodle \
        --extensions=php,phtml,html \
        --ignore='vendor/*,node_modules/*,amd/build/*,DetectRTC.js,RecordRTC.js,jquery-sortable.js,getHTMLMediaElement.js' \
        .
}

run_grunt() {
    echo "🛠  Grunt (host, --max-lint-warnings=0)"
    cd "${MOODLE_HOST_ROOT}"
    npx grunt --root=mod/videoassessment --max-lint-warnings=0
}

case "${1:-all}" in
    phpunit)
        shift || true
        run_phpunit "$@"
        ;;
    phpcs)
        run_phpcs
        ;;
    grunt)
        run_grunt
        ;;
    all|"")
        run_phpcs && run_grunt && run_phpunit
        ;;
    *)
        echo "Unknown command: $1" >&2
        echo "Usage: $0 [phpunit|phpcs|grunt|all]" >&2
        exit 2
        ;;
esac
