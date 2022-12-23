#!/bin/bash

# Find out directory of current script
# to make it possible to run this script from any location
if [ -L "$0" ] && [ -x $(which readlink) ]; then
  THIS_FILE="$(readlink -mn "$0")"
else
  THIS_FILE="$0"
fi
SCRIPT_DIR="$(dirname "$THIS_FILE")"
FILES_DIR="$SCRIPT_DIR/../files"

pushd "$FILES_DIR"/google
composer install
popd
