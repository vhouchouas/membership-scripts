#!/bin/bash -e

# Find out directory of current script
# to make it possible to run this script from any location
if [ -L "$0" ] && [ -x $(which readlink) ]; then
  THIS_FILE="$(readlink -mn "$0")"
else
  THIS_FILE="$0"
fi
SCRIPT_DIR="$(dirname "$THIS_FILE")"

#"$SCRIPT_DIR/phpunit" "$SCRIPT_DIR"/*.php
for TEST_FILE in "$SCRIPT_DIR"/*.php; do
  echo Going to run $TEST_FILE
  "$SCRIPT_DIR/phpunit" "$TEST_FILE"
done
