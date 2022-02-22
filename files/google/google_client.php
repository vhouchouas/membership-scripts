<?php
/*
Copyright (C) 2020-2022  Zero Waste Paris

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/
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
