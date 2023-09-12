#!/bin/bash -ex
if [ -L "$0" ] && [ -x "$(which readlink)" ]; then
	THIS_FILE="$(readlink -mn "$0")"
else
	THIS_FILE="$0"
fi
THIS_DIR="$(dirname "$THIS_FILE")"
BIN_DIR="$THIS_DIR/../bin"
OAG_VERSION=7.0.0
OAG_JAR="$BIN_DIR/openapi-generator-cli.$OAG_VERSION.jar"
OAS_FILE="$THIS_DIR/../openapi.yaml"
OAS_LOGIN_FILE="$THIS_DIR/../openapi-login.yaml"
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

echo "Generating the angular modules"
# For angular we generate the sources in the project because it seems to be the most convenient way
# to make the sources available
ANGULAR_GENERATION_ROOT="$THIS_DIR/../angular-front/src/app/generated"
rm -rf "$ANGULAR_GENERATION_ROOT"
java -jar "$OAG_JAR" generate -i "$OAS_FILE" -g typescript-angular -o "$ANGULAR_GENERATION_ROOT/api"
java -jar "$OAG_JAR" generate -i "$OAS_LOGIN_FILE" -g typescript-angular --additional-properties "apiModulePrefix=Login,serviceSuffix=LoginService" -o "$ANGULAR_GENERATION_ROOT/login"

