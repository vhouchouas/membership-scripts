<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use App\Models\SlackMembersTimestamped;

final class SlackMembersTimestampedTest extends TestCase {
	private $tmpTestFilePath;
	private LoggerInterface $logger;

	protected function setUp(): void {
		$tempTestFile = tmpfile(); // PHP will take care of deleting this file at the end (unless the process crashes ofc)
		$this->assertNotEquals($tempTestFile, false, "pre-condition: make sure we could create the test file");
		$this->tmpTestFilePath = stream_get_meta_data($tempTestFile)['uri'];

		$this->logger = $this->createMock(LoggerInterface::class);
	}

	public function testCanSerializeAndDeserializeAfterwards(): void {
		// Setup
		$now = new DateTime("2020-09-08T06:58:00Z");
		$membersIsh = ["some", "members"]; // IRL items should be instances of ObjsUser, but we simplify this test setup
		$sut = SlackMembersTimestamped::create($now, $membersIsh);

		// // Precondition checks: we should have instantiated the expected object
		$this->assertTrue($sut->isFresh(), "The instance was created from scratch so it should be considered fresh");
		$this->assertEquals($now->getTimestamp(), $sut->getTimestamp());
		$this->assertEquals($membersIsh, $sut->getMembers());

		// // Precondition check: if we deserialize now we get null because the file does not exist yet
		$this->assertThrows(function() use(&$now) {SlackMembersTimestamped::fromFile($this->tmpTestFilePath, $this->logger, $now, 300);},
			"Should throw if no serialized file exists");

		// Act
		$sut->serializeToFile($this->tmpTestFilePath);
		$deserializedSut = SlackMembersTimestamped::fromFile($this->tmpTestFilePath, $this->logger, $now, 300);

		// Assert
		$this->assertFalse($deserializedSut->isFresh(), "The instance was deserialized so it's data should not be considered fresh");
		$this->assertEquals($now->getTimestamp(), $deserializedSut->getTimestamp());
		$this->assertEquals($membersIsh, $deserializedSut->getMembers());
	}

	private function assertThrows($lambda, $message) {
		try {
			$lambda();
		} catch(\Throwable $t) {
			return;
		}
		$this->fail("Expected to throw but did not: $message");
	}
}
