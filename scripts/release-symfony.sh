#!/bin/bash
set -e

# Find out directory of current script
# to make it possible to run this script from any location
if [ -L "$0" ] && [ -x $(which readlink) ]; then
  THIS_FILE="$(readlink -mn "$0")"
else
  THIS_FILE="$0"
fi

SCRIPT_DIR="$(realpath "$(dirname "$THIS_FILE")")"
ROOT_DIR="$SCRIPT_DIR/.."
TEMPORARY_RELEASE_DIR="$ROOT_DIR/temp_for_release"
FILES_DIR="$ROOT_DIR/symfony-server"
SLACK_APP_DIR="$ROOT_DIR/slack-agenda-app"

# parse arguments
TARGET_ENVIRONMENT=preprod # default value; overridable by cli options
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
LOCAL_CONF_FILE="$CONF_DIR"/deploysymfony-config.sh


# Exit if some files which should have been added during setup are missing
# Here we don't juste warn, we exit, because missing files will most certainly lead
# to breaking the prod or to add security holes
function testFileOrDie {
  if [ ! -f "$1" ]; then
    echo File "$1" is needed but it missing. Setup may not be completed. See README for more info
    exit 1
  fi
}
testFileOrDie "$CONF_DIR/htaccess"
testFileOrDie "$CONF_DIR/symfony.conf"
testFileOrDie "$CONF_DIR/google_tokens.json"
testFileOrDie "$SLACK_CONF_DIR"/config.json
testFileOrDie "$LOCAL_CONF_FILE"

# Load conf
source "$LOCAL_CONF_FILE"

# Warn if the repo isn't clean , to prevent releasing corrupted code
if ! [ -z "$(git status --porcelain)" ]; then
  git status
  read -p "Repo not clean. Are you sure you want to deploy? [yN]" ANSWER
  if [ "x$ANSWER" != "xy" ]; then
    echo aborting
    exit 1
  fi
fi

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

# Run the tests
pushd "$FILES_DIR"
composer install
"$SCRIPT_DIR"/runSymfonyTests.sh
popd


# Generate the OpenApi files
"$SCRIPT_DIR"/generate.sh

# Copy the files to a temporary directory
rm -rf "$TEMPORARY_RELEASE_DIR"
cp -rL "$FILES_DIR" "$TEMPORARY_RELEASE_DIR"
cd "$TEMPORARY_RELEASE_DIR"

# Clean the files: rm potential leftovers, copy the conf files, install dependencies, warm-up the cache
rm -rf var .env.* vendor tests coverage
cp "$CONF_DIR"/symfony.conf .env.local
mkdir var
cp "$CONF_DIR"/google_tokens.json var/
cp "$CONF_DIR"/htaccess public/.htaccess
cp -f "$CONF_DIR"/favicon.* public/favicon.ico

composer install --optimize-autoloader
## rm the symlink in order to have actuals files
rm vendor/zero-waste-paris/membership-scripts
cp -rL "$ROOT_DIR"/generated/php-server-bundle vendor/zero-waste-paris/membership-scripts

composer dump-env prod
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear

# Install the slack app
pushd $ROOT_DIR
git submodule init
git submodule update
popd

cp -ar "$SLACK_APP_DIR" public
rm -rf public/slack-agenda-app/{.git*,tests}
pushd public/slack-agenda-app
cp "$SLACK_CONF_DIR"/config.json .
composer install --no-dev --optimize-autoloader
popd

# Releasing
echo "All good, we're going to perform the release to $TARGET_ENVIRONMENT"
rsync -avz --delete "$TEMPORARY_RELEASE_DIR/" "$RSYNC_DESTINATION"

echo DONE

