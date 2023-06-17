#!/bin/bash -e

# Find out directory of current script
# to make it possible to run this script from any location
if [ -L "$0" ] && [ -x $(which readlink) ]; then
  THIS_FILE="$(readlink -mn "$0")"
else
  THIS_FILE="$0"
fi
TEST_RUNNER_DIR="$(dirname "$THIS_FILE")"

# Install phpunit
pushd "$TEST_RUNNER_DIR"
composer install
popd

# Copy the files in a temporary directory (in order to override the config file without messing with the actual one)
TEMP_DIR="$TEST_RUNNER_DIR"/temp-src-copy
rm -rf "$TEMP_DIR"
mkdir "$TEMP_DIR"
cp -r "$TEST_RUNNER_DIR"/../files/* "$TEMP_DIR"
cp "$TEST_RUNNER_DIR"/../scripts/preprod-config/config.template.php "$TEMP_DIR"/config.php

# Run tests
XDEBUG_MODE=coverage "$TEST_RUNNER_DIR"/vendor/bin/phpunit --coverage-html coverage "$TEST_RUNNER_DIR/src"
