#!/bin/bash -e
if [ -L "$0" ] && [ -x "$(which readlink)" ]; then
	THIS_FILE="$(readlink -mn "$0")"
else
	THIS_FILE="$0"
fi
THIS_DIR="$(dirname "$THIS_FILE")"
BIN_DIR="$THIS_DIR/../bin"
OAG_JAR="$BIN_DIR/openapi-generator-cli.jar"
OAG_VERSION=6.6.0
OAS_FILE="$THIS_DIR/../openapi.yaml"
GIT_USER_ID=zero-waste-paris
GIT_PROJECT=membership-scripts
PHP_OUT="$THIS_DIR/../generated/php-server-bundle"

mkdir -p "$BIN_DIR"

if [ ! -e "$OAG_JAR" ]; then
	echo "Downloading openapi-generator-cli jar"
	curl "https://repo1.maven.org/maven2/org/openapitools/openapi-generator-cli/$OAG_VERSION/openapi-generator-cli-$OAG_VERSION.jar" > "$OAG_JAR"
fi

echo "Generating the server module"
java -jar "$OAG_JAR" generate --git-user-id "$GIT_USER_ID" --git-repo-id "$GIT_PROJECT" -i "$OAS_FILE" -g php-symfony -o "$PHP_OUT"
# rm generated tests files because
# - we don't need it
# - it would generate errors like "does not comply with psr-4 autoloading standard"
rm -r "$PHP_OUT"/Tests # rm test files

echo "Generating a bash client"
java -jar "$OAG_JAR" generate -i "$OAS_FILE" -g bash -o "$THIS_DIR/../generated/bash-client"
