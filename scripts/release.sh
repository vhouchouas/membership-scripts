#!/bin/bash -e

# Find out directory of current script
# to make it possible to run this script from any location
if [ -L "$0" ] && [ -x $(which readlink) ]; then
  THIS_FILE="$(readlink -mn "$0")"
else
  THIS_FILE="$0"
fi
SCRIPT_DIR="$(realpath "$(dirname "$THIS_FILE")")"
ROOT_DIR="$SCRIPT_DIR/.."
FILES_DIR="$ROOT_DIR/files"
SLACK_APP_DIR="$ROOT_DIR/slack-agenda-app"
TEMPORARY_RELEASE_DIR="$ROOT_DIR/temp_for_release"

TARGET_ENVIRONMENT=preprod # default value; overridable by cli options

# parse arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    --env)
      TARGET_ENVIRONMENT="$2"
      shift # past argument
      shift # past value
      ;;
    -h|--help)
      echo "Options:"
      echo " --env"
      echo "   either 'prod' or 'preprod' (or a custom value if you defined such config). Default to 'preprod'"
      echo "   E.g.: $0 --env prod"
      echo " -h|--help: displays this help and exits"
      exit 0
      ;;
   *)
     echo "Unknown option $1"
     exit 1
     ;;
 esac
done
echo "Going to deploy to environment: $TARGET_ENVIRONMENT"

CONF_DIR="$SCRIPT_DIR"/"$TARGET_ENVIRONMENT-config"
SLACK_CONF_DIR="$CONF_DIR"/slack-config

# Ensure the submodule is initialized
pushd $ROOT_DIR
git submodule init
git submodule update
popd

# Load conf
LOCAL_CONF_FILE="$CONF_DIR"/deploy-config.sh
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
testFileOrDie "$CONF_DIR"/config.php
testFileOrDie "$LOCAL_CONF_FILE"
testFileOrDie "$FILES_DIR"/google/credentials.json
testFileOrDie "$FILES_DIR"/google/token.json
testFileOrDie "$SLACK_CONF_DIR"/config.json

"$SCRIPT_DIR"/installDependencies.sh

# Warn if debug statements may have been left
for F in $(git ls-files "$FILES_DIR"); do
  if grep var_dump "$F"; then
    read -p "var_dump statement found in $F. Are you sure you want to deploy? [yN]" ANSWER
    if [ "x$ANSWER" != "xy" ]; then
      echo aborting
      exit 1
    fi
  fi
done

# Going to run the tests
echo "We're going to run the tests"
if ! "$SCRIPT_DIR/runTests.sh"; then
  read -p "Some tests fail. Are you sure you want to deploy? [yN]" ANSWER
  if [ "x$ANSWER" != "xy" ]; then
    echo aborting
    exit 1
  fi
fi

# Copy the files in the temporary release dir
pushd "$SLACK_APP_DIR"
"$SCRIPT_DIR"/composer.phar install --no-dev
popd
rm -rf "$TEMPORARY_RELEASE_DIR"
cp -ar "$FILES_DIR" "$TEMPORARY_RELEASE_DIR"
cp -ar "$SLACK_APP_DIR" "$TEMPORARY_RELEASE_DIR"
rm -rf "$TEMPORARY_RELEASE_DIR"/slack-agenda-app/{.git*,tests}

# Copy the config files
cp "$CONF_DIR"/config.php "$TEMPORARY_RELEASE_DIR"/
cp "$SLACK_CONF_DIR/config.json" "$TEMPORARY_RELEASE_DIR"/slack-agenda-app

# Releasing
echo "All good, we're going to perform the release to $TARGET_ENVIRONMENT"
rsync -avz --delete "$TEMPORARY_RELEASE_DIR/" "$RSYNC_DESTINATION"

echo DONE
