#!/bin/bash -e

# Find out directory of current script
# to make it possible to run this script from any location
if [ -L "$0" ] && [ -x $(which readlink) ]; then
  THIS_FILE="$(readlink -mn "$0")"
else
  THIS_FILE="$0"
fi
SCRIPT_DIR="$(dirname "$THIS_FILE")"

# Install phpunit
pushd "$SCRIPT_DIR"
../scripts/composer.phar install
popd

# Copy the files in a temporary directory (in order to override the config file without messing with the actual one)
TEMP_DIR="$SCRIPT_DIR"/temp-src-copy
rm -rf "$TEMP_DIR"
mkdir "$TEMP_DIR"
cp -r "$SCRIPT_DIR"/../files/* "$TEMP_DIR"
cp "$SCRIPT_DIR"/../files/config.template.php "$TEMP_DIR"/config.php

# Run tests
"$SCRIPT_DIR"/vendor/bin/phpunit "$SCRIPT_DIR/src"

# cleanup
rm -rf "$TEMP_DIR"
