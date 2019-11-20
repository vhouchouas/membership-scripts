<?php
define('ZWP_TOOLS', __DIR__ . '/../');

include 'google_client.php';

if (file_exists(TOKEN_JSON_PATH)) {
    unlink(TOKEN_JSON_PATH) or die("Couldn't delete file");
}

try {
    $client = getClient();
} catch (Exception $e) {
    print($e->getMessage());
}
