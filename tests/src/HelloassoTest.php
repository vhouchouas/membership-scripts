<?php
declare(strict_types=1);
if (!defined('ZWP_TOOLS')){
  define('ZWP_TOOLS', __DIR__ . '/../temp-src-copy/');
}
require_once(ZWP_TOOLS . 'lib/helloasso.php');

use PHPUnit\Framework\TestCase;

final class HelloassoTest extends TestCase {
  public function test_validateOkTokens(){
    $okString = '{"access_token":"eyJhbxxx.eyJqdyyy.vl9ALzzz","token_type":"bearer","expires_in":1799,"refresh_token":"Alxyz"}';
    $this->assertTrue(HelloAssoConnector::isValidTokensJson($okString));
  }

  public function test_validateNotJsonTokens(){
    $notJsonString = '{"access_token:"eyJhbxxx.eyJqdyyy.vl9ALzzz","token_type":"bearer","expires_in":1799,"refresh_token":"Alxyz"}';
    $this->assertFalse(HelloAssoConnector::isValidTokensJson($notJsonString));
  }

  public function test_validateMissingAccessTokenString(){
    $missingAccessTokenString = '{"token_type":"bearer","expires_in":1799,"refresh_token":"Alxyz"}';
    $this->assertFalse(HelloAssoConnector::isValidTokensJson($missingAccessTokenString));
  }

  public function test_validateMissingRefreshTokens(){
    $missingRefreshTokenString = '{"access_token":"eyJhbxxx.eyJqdyyy.vl9ALzzz","token_type":"bearer","expires_in":1799}';
    $this->assertFalse(HelloAssoConnector::isValidTokensJson($missingRefreshTokenString));
  }

}
