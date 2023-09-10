#!/bin/bash
set -e

# Find out directory of current script
# to make it possible to run this script from any location
if [ -L "$0" ] && [ -x $(which readlink) ]; then
  THIS_FILE="$(readlink -mn "$0")"
else
  THIS_FILE="$0"
fi
SCRIPT_DIR="$(dirname "$THIS_FILE")"

cd "$SCRIPT_DIR"/../symfony-server/

export APP_ENV=test

echo "Creating the test database"
php bin/console --env=test doctrine:database:create
echo "Dropping the schema of the test database"
php bin/console --env=test doctrine:schema:drop --force
echo "Creating the schema of the test database"
php bin/console --env=test doctrine:schema:create
echo "Initialize some values in the test database"
php bin/console doctrine:database:initialize-last-successful-run-date 2022-01-01
php bin/console user:add testuser testpassword

APP_ENV=test XDEBUG_MODE=coverage php bin/phpunit --coverage-html coverage $@
