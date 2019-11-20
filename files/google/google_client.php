<?php
if(!defined('ZWP_TOOLS')){  die(); }


require __DIR__ . '/vendor/autoload.php';

const TOKEN_JSON_PATH = __DIR__ . "/token.json";

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('G Suite Directory API PHP Quickstart');
    $client->setScopes(array(
        Google_Service_Directory::ADMIN_DIRECTORY_USER_READONLY,
        Google_Service_Directory::ADMIN_DIRECTORY_GROUP
    ));
    $client->setAuthConfig(__DIR__ . '/credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    if (file_exists(TOKEN_JSON_PATH)) {
        $accessToken = json_decode(file_get_contents(TOKEN_JSON_PATH), true);
        $client->setAccessToken($accessToken);
    }

    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }

        if (!file_exists(dirname(TOKEN_JSON_PATH))) {
            mkdir(dirname(TOKEN_JSON_PATH), 0700, true);
        }
        file_put_contents(TOKEN_JSON_PATH, json_encode($client->getAccessToken()));
    }
    return $client;
}
