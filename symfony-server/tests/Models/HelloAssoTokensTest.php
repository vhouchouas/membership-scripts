<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Models\HelloAssoTokens;
use Psr\Log\LoggerInterface;

final class HelloAssoTokensTest extends TestCase {

	private LoggerInterface $logger;

	protected function setUp(): void {
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	public function test_okTokens() {
		$accessToken = "eyJhbxxx.eyJqdyyy.vl9ALzzz";
		$refreshToken = "Alxyz";
		$okString = '{"access_token":"'.$accessToken.'","token_type":"bearer","expires_in":1799,"refresh_token":"'.$refreshToken.'"}';
		$tokens = HelloAssoTokens::fromContentInRam($okString, $this->logger);

		$this->assertEquals($accessToken, $tokens->getAccessToken());
		$this->assertEquals($refreshToken, $tokens->getRefreshToken());
	}

	public function test_invalidTokens_notJson() {
		$notJsonString = '{"access_token:"eyJhbxxx.eyJqdyyy.vl9ALzzz","token_type":"bearer","expires_in":1799,"refresh_token":"Alxyz"}';
		$this->assertNull(HelloAssoTokens::fromContentInRam($notJsonString, $this->logger));
	}

	public function test_invalidTokens_missingAccessTokenString(){
		$missingAccessTokenString = '{"token_type":"bearer","expires_in":1799,"refresh_token":"Alxyz"}';
		$this->assertNull(HelloAssoTokens::fromContentInRam($missingAccessTokenString, $this->logger));
	}

	public function test_invalidTokens_missingRefreshTokens(){
		$missingRefreshTokenString = '{"access_token":"eyJhbxxx.eyJqdyyy.vl9ALzzz","token_type":"bearer","expires_in":1799}';
		$this->assertNull(HelloAssoTokens::fromContentInRam($missingRefreshTokenString, $this->logger));
	}

	public function test_fromFile_withFileThatDoesNotExists() {
		$this->assertNull(HelloAssoTokens::fromFile("someFileThatDoesNotExists.json", $this->logger),
			"This error case is currently designed to return null (instead of e.g. ");

	}
}
