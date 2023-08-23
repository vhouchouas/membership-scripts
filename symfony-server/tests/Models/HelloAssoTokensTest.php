<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Models\HelloAssoTokens;
use Psr\Log\LoggerInterface;

final class HelloAssoTokensTest extends TestCase {

	private LoggerInterface $logger;
	private string $placeholderAccessToken;
	private string $placeholderRefreshToken;
	private string $placeholderTokens;

	protected function setUp(): void {
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->placeholderAccessToken = "eyJhbxxx.eyJqdyyy.vl9ALzzz";
		$this->placeholderRefreshToken = "Alxyz";
		$this->placeholderTokens = '{"access_token":"'.$this->placeholderAccessToken.'","token_type":"bearer","expires_in":1799,"refresh_token":"'.$this->placeholderRefreshToken.'"}';
	}

	public function test_okTokens() {
		$tokens = HelloAssoTokens::fromContentInRam($this->placeholderTokens, $this->logger);

		$this->assertEquals($this->placeholderAccessToken, $tokens->getAccessToken());
		$this->assertEquals($this->placeholderRefreshToken, $tokens->getRefreshToken());
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

	public function test_fromFile_withValidTokens() {
		// Setup
		$tempTestFile = tmpfile(); // PHP will take care of deleting this file at the end (unless the process crashes ofc)
		$this->assertNotEquals($tempTestFile, false, "pre-condition: make sure we could create the test file");
		$tmpTestFilePath = stream_get_meta_data($tempTestFile)['uri'];

		file_put_contents($tmpTestFilePath, $this->placeholderTokens);

		// Act
		$tokens = HelloAssoTokens::fromFile($tmpTestFilePath, $this->logger);

		// Assert
		$this->assertEquals($this->placeholderAccessToken, $tokens->getAccessToken());
		$this->assertEquals($this->placeholderRefreshToken, $tokens->getRefreshToken());


	}
}
