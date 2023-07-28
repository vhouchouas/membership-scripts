<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Models\HelloAssoTokens;

final class HelloAssoTokensTest extends TestCase {
	public function test_okTokens() {
		$accessToken = "eyJhbxxx.eyJqdyyy.vl9ALzzz";
		$refreshToken = "Alxyz";
		$okString = '{"access_token":"'.$accessToken.'","token_type":"bearer","expires_in":1799,"refresh_token":"'.$refreshToken.'"}';
		$tokens = HelloAssoTokens::fromContentInRam($okString);

		$this->assertEquals($accessToken, $tokens->getAccessToken());
		$this->assertEquals($refreshToken, $tokens->getRefreshToken());
	}

	public function test_invalidTokens_notJson() {
		$notJsonString = '{"access_token:"eyJhbxxx.eyJqdyyy.vl9ALzzz","token_type":"bearer","expires_in":1799,"refresh_token":"Alxyz"}';
		$this->assertNull(HelloAssoTokens::fromContentInRam($notJsonString));
	}

	public function test_invalidTokens_missingAccessTokenString(){
		$missingAccessTokenString = '{"token_type":"bearer","expires_in":1799,"refresh_token":"Alxyz"}';
		$this->assertNull(HelloAssoTokens::fromContentInRam($missingAccessTokenString));
	}

	public function test_invalidTokens_missingRefreshTokens(){
		$missingRefreshTokenString = '{"access_token":"eyJhbxxx.eyJqdyyy.vl9ALzzz","token_type":"bearer","expires_in":1799}';
		$this->assertNull(HelloAssoTokens::fromContentInRam($missingRefreshTokenString));
	}
}
