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

# Load conf
LOCAL_CONF_FILE="$SCRIPT_DIR"/config.sh
if [ -e "$LOCAL_CONF_FILE" ]; then
  source "$LOCAL_CONF_FILE"
else
  echo Can\'t find file with local conf $LOCAL_CONF_FILE
  exit 1
fi

# Warn if the repo isn't clean , to prevent releasing corrupted code
if ! [ -z "$(git status --porcelain)" ]; then
  read -p "Repo not clean. Are you sure you want to deploy? [yN]" ANSWER
  if [ "x$ANSWER" != "xy" ]; then
    echo aborting
    exit 1
  fi
fi

function testFileOrDie {
  if [ ! -f "$1" ]; then
    echo File "$1" is needed but it missing. Setup may not be completed. See README for more info
    exit 1
  fi
}

# Exit if some files which should have been added during setup are missing
# Here we don't juste warn, we exit, because missing files will most certainly lead
# to breaking the prod or to add security holes
testFileOrDie "$FILES_DIR"/.htaccess
testFileOrDie "$FILES_DIR"/config.php
testFileOrDie "$LOCAL_CONF_FILE"
testFileOrDie "$FILES_DIR"/google/credentials.json
testFileOrDie "$FILES_DIR"/google/token.json

"$SCRIPT_DIR"/installDependencies.sh

# Warn if debug statements may have been left
if grep -r var_dump "$FILES_DIR" | grep -v "google/vendor\|Binary" ; then
  read -p "var_dump statement found in the source. Are you sure you want to deploy? [yN]" ANSWER
  if [ "x$ANSWER" != "xy" ]; then
    echo aborting
    exit 1
  fi
fi

# Going to run the tests
echo "We're going to run the tests"
if ! "$SCRIPT_DIR/runTests.sh"; then
  read -p "Some tests fail. Are you sure you want to deploy? [yN]" ANSWER
  if [ "x$ANSWER" != "xy" ]; then
    echo aborting
    exit 1
  fi
fi

# Releasing
echo "All good, we're going to perform the release"
rsync -avz --delete "$FILES_DIR/" "$RSYNC_DESTINATION"

echo DONE
