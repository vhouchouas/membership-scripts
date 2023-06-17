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

	public function test_getListOfRegistrationsOlderThan() {
		// Setup
		$sut = new DoctrineConnector(false);
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		// Act
		$sut->addOrUpdateMember($registrationBob);
		$sut->addOrUpdateMember($registrationAlice);

		// Assert
		$this->assertEquals(1, count($sut->getListOfRegistrationsOlderThan(new DateTime("1900-01-01"))), "only 1 member registered before that date");
		$this->assertEquals(2, count($sut->getListOfRegistrationsOlderThan(new DateTime("2025-01-01"))), "all 2 members registered before that date");

		// Act & assert again to make sure the last registration date is taken into account
		$bobUpdateDate = "2030-09-08";
		$updateBob = $this->buildHelloassoEvent($bobUpdateDate, "bob", "dylan", "bob-new@email.com");
		$sut->addOrUpdateMember($updateBob);
		$this->assertEquals(1, count($sut->getListOfRegistrationsOlderThan(new DateTime("2025-01-01"))), "The last registration of Bob is now after that date so we should have only alice registration");
	}

	public function test_deleteRegistrationsOlderThan() {
		// Setup
		$sut = new DoctrineConnector(false);
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		$sut->addOrUpdateMember($registrationBob);
		$sut->addOrUpdateMember($registrationAlice);

		$this->assertEquals(2, count($sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "Pre-condition: we should have 2 registrations at this stage");

		// Act
		$sut->deleteRegistrationsOlderThan(new DateTime("1900-01-01"));

		// Assert
		$this->assertEquals(1, count($sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "1 (and only 1) registration should have been deleted, leaving only 1");
	}

	public function test_getMemberMatchingRegistration() {
		// Setup
		$sut = new DoctrineConnector(false);
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		$sut->addOrUpdateMember($registrationBob);
		$sut->addOrUpdateMember($registrationAlice);

		// Act & assert
		$this->assertExpectedMember($bobRegistrationDate, $bobRegistrationDate, "bob", "dylan", "bob@dylan.com",
				$sut->getMemberMatchingRegistration($registrationBob));

		$this->assertEquals(null,
				$sut->getMemberMatchingRegistration($this->buildHelloassoEvent("2020-01-01", "someone", "else", "somone@oneelse.com")),
				"we should get null when we look for an unknown member");

		// Act & assert when a member has an updated registration
		$bobUpdateDate = "2020-09-08";
		$updateBob = $this->buildHelloassoEvent($bobUpdateDate, "bob", "dylan", "bob-new@email.com");
		$sut->addOrUpdateMember($updateBob);

		$this->assertExpectedMember($bobRegistrationDate, $bobUpdateDate, "bob", "dylan", "bob-new@email.com",
				$sut->getMemberMatchingRegistration($registrationBob),
				"even if we search with an old RegistrationEvent we should find the latest information (behavior useful for the offlineIntegrityComparator ");
	}

	public function test_findMembersInArrayWhoDoNotRegisteredAfterGivenDate() {
		// Setup
		$sut = new DoctrineConnector(false);
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");
		$charlesRegistrationDate = "2020-09-08";
		$registrationCharles = $this->buildHelloassoEvent($charlesRegistrationDate, "Charles", "Edouard", "charles@something.com");

		$sut->addOrUpdateMember($registrationBob);
		$sut->addOrUpdateMember($registrationAlice);
		$sut->addOrUpdateMember($registrationCharles);

		$this->assertEquals(3, count($sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "Pre-condition: all 3 members should be in database");

		// Act & assert
		$members = $sut->findMembersInArrayWhoDoNotRegisteredAfterGivenDate(['bob@dylan.com', 'al@ice.com'], new DateTime("1900-01-01"));
		$this->assertEquals(1, count($members), "Only alice matches: bob registered after the date, and charles is not in the array");
		$this->assertExpectedMember($aliceRegistrationDate, $aliceRegistrationDate, "alice", "wonderland", "al@ice.com", $members[0]);
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
