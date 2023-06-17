<?php
declare(strict_types=1);
if (!defined('ZWP_TOOLS')){
  define('ZWP_TOOLS', __DIR__ . '/../temp-src-copy/');
}

require_once(ZWP_TOOLS . "lib/doctrine/DoctrineConnector.php");

use PHPUnit\Framework\TestCase;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

final class DoctrineConnectorTest extends TestCase {

	public function setUp(): void {
		$entityManagerProvider = new SingleManagerProvider(DoctrineConnector::getEntitymanager());
		$dropHelper = new DoctrineDropDatabaseHelper($entityManagerProvider);
		$dropHelper->dropDatabase();
		$createHelper = new DoctrineCreateDatabaseHelper($entityManagerProvider);
		$createHelper->createDatabase();
	}

	public function test_addAndUpdateMembers() {
		// Setup to add members
		$sut = new DoctrineConnector(false);
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		// Act to add members
		$sut->addOrUpdateMember($registrationBob);
		$sut->addOrUpdateMember($registrationAlice);

		// Assert on added members
		$members = $sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertEquals(2, count($members), "2 members have registered");
		$this->assertExpectedMember($aliceRegistrationDate, $aliceRegistrationDate, "alice", "wonderland", "al@ice.com", $members[0]);
		$this->assertExpectedMember($bobRegistrationDate, $bobRegistrationDate, "bob", "dylan", "bob@dylan.com", $members[1]);

		// Setup to update a member
		$bobUpdateDate = "2020-09-08";
		$updateBob = $this->buildHelloassoEvent($bobUpdateDate, "bob", "dylan", "bob-new@email.com");

		// Act to update Bob
		$sut->addOrUpdateMember($updateBob);

		// Assert on bob's update
		$members = $sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertEquals(2, count($members), "The last registration was an update, we should still have only 2 members");
		$this->assertExpectedMember($aliceRegistrationDate, $aliceRegistrationDate, "alice", "wonderland", "al@ice.com", $members[0]);
		$this->assertExpectedMember($bobRegistrationDate, $bobUpdateDate, "bob", "dylan", "bob-new@email.com", $members[1]);

		// Leverage the setup to assert on getOrderedListOfLastRegistrations behavior
		$members = $sut->getOrderedListOfLastRegistrations(new DateTime("1900-01-01"));
		$this->assertEquals(1, count($members), "There should be a single registration after the 'since' date passed");
	}

	private $lastHelloAssoEventId = 0;
	private function buildHelloassoEvent($event_date, $first_name, $last_name, $email): RegistrationEvent {
		$ret = new RegistrationEvent();
		$ret->event_date = $event_date;
		$ret->first_name = $first_name;
		$ret->last_name = $last_name;
		$ret->email = $email;

		$ret->helloasso_event_id = $this->lastHelloAssoEventId;
		$this->lastHelloAssoEventId++;

		return $ret;
	}

	private function assertExpectedMember(string $firstRegistrationDate, string $lastRegistrationDate, string $firstName, string $lastName, string $email, MemberDTO $actualMember) {
		$this->assertEquals(new DateTimeImmutable($firstRegistrationDate), $actualMember->firstRegistrationDate);
		$this->assertEquals(new DateTimeImmutable($lastRegistrationDate), $actualMember->lastRegistrationDate);
		$this->assertEquals($firstName, $actualMember->firstName);
		$this->assertEquals($lastName, $actualMember->lastName);
		$this->assertEquals($email, $actualMember->email);
	}

}

// *** Helper to create and drop database programmatically ***

use Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\NullOutput;

class DoctrineCreateDatabaseHelper extends CreateCommand {
	public function createDatabase() {
		$input = new ArrayInput([], $this->getDefinition());

		$output = new NullOutput();

		$this->execute($input, $output);
		echo "Created database\n";
	}
}

class DoctrineDropDatabaseHelper extends DropCommand {
	public function dropDatabase() {
		$input = new ArrayInput(["--force" => true], $this->getDefinition());

		$output = new NullOutput();

		$this->execute($input, $output);
		echo "Dropped database\n";
	}
}
