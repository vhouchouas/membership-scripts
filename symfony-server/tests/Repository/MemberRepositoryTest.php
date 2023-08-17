<?php
declare(strict_types=1);

require_once __DIR__ . '/../TestHelperTrait.php';

use App\Repository\MemberRepository;
use App\Models\RegistrationEvent;
use App\Entity\Member;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MemberRepositoryTest extends KernelTestCase {
	use TestHelperTrait;

	private MemberRepository $memberRepository;

	protected function setUp(): void {
		self::bootKernel();
		$container = static::getContainer();
		$this->memberRepository = $container->get(MemberRepository::class);
	}

	public function test_addOrUpdateMember() {
		$debug = false;
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		// Act to add members
		$this->memberRepository->addOrUpdateMember($registrationBob, $debug);
		$this->memberRepository->addOrUpdateMember($registrationAlice, $debug);

		// Assert on added members
		$members = $this->memberRepository->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertEquals(2, count($members), "2 members have registered");
		$this->assertExpectedMember($aliceRegistrationDate, $aliceRegistrationDate, "alice", "wonderland", "al@ice.com", $members[0]);
		$this->assertExpectedMember($bobRegistrationDate, $bobRegistrationDate, "bob", "dylan", "bob@dylan.com", $members[1]);

		// Setup to update a member
		$bobUpdateDate = "2020-09-08";
		$updateBob = $this->buildHelloassoEvent($bobUpdateDate, "bob", "dylan", "bob@dylan.com");

		// Act to update Bob
		$this->memberRepository->addOrUpdateMember($updateBob, $debug);

		// Assert on bob's update
		$members = $this->memberRepository->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertEquals(2, count($members), "The last registration was an update, we should still have only 2 members");
		$this->assertExpectedMember($aliceRegistrationDate, $aliceRegistrationDate, "alice", "wonderland", "al@ice.com", $members[0]);
		$this->assertExpectedMember($bobRegistrationDate, $bobUpdateDate, "bob", "dylan", "bob@dylan.com", $members[1]);

		// Leverage the setup to assert on getOrderedListOfLastRegistrations behavior
		$members = $this->memberRepository->getOrderedListOfLastRegistrations(new DateTime("1900-01-01"));
		$this->assertEquals(1, count($members), "There should be a single registration after the 'since' date passed");
	}

	public function test_noUpdateIsPerformedIfTheRegistrationHandledLastIsOlderThanTheCurrentData() {
		// Setup
		$debug = false;
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$this->memberRepository->addOrUpdateMember($registrationBob, $debug);

		// // Precondition check
		$members = $this->memberRepository->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertExpectedMember($bobRegistrationDate, $bobRegistrationDate, "bob", "dylan", "bob@dylan.com", $members[0]);

		// Act 1: add a previous registration
		$bobPreviousRegistrationDate = "1985-01-01";
		$otherRegistrationBob = $this->buildHelloassoEvent($bobPreviousRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$this->memberRepository->addOrUpdateMember($otherRegistrationBob, $debug);

		// Assert 1
		$members = $this->memberRepository->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertExpectedMember($bobPreviousRegistrationDate, $bobRegistrationDate, "bob", "dylan", "bob@dylan.com", $members[0]);

		// Act 2: add a registration in between
		$bobInBetweenRegistrationDate = "1985-02-01";
		$inBetweenRegistrationBob = $this->buildHelloassoEvent($bobInBetweenRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$this->memberRepository->addOrUpdateMember($inBetweenRegistrationBob, $debug);

		// Assert 2
		$members = $this->memberRepository->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertExpectedMember($bobPreviousRegistrationDate, $bobRegistrationDate, "bob", "dylan", "bob@dylan.com", $members[0]);
	}

	public function test_getListOfRegistrationsOlderThan() {
		// Setup
		$debug = false;
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		// Act
		$this->memberRepository->addOrUpdateMember($registrationBob, $debug);
		$this->memberRepository->addOrUpdateMember($registrationAlice, $debug);

		// Assert
		$this->assertEquals(1, count($this->memberRepository->getListOfRegistrationsOlderThan(new DateTime("1900-01-01"))), "only 1 member registered before that date");
		$this->assertEquals(2, count($this->memberRepository->getListOfRegistrationsOlderThan(new DateTime("2025-01-01"))), "all 2 members registered before that date");

		// Act & assert again to make sure the last registration date is taken into account
		$bobUpdateDate = "2030-09-08";
		$updateBob = $this->buildHelloassoEvent($bobUpdateDate, "bob", "dylan", "bob@dylan.com");
		$this->memberRepository->addOrUpdateMember($updateBob, $debug);
		$this->assertEquals(1, count($this->memberRepository->getListOfRegistrationsOlderThan(new DateTime("2025-01-01"))), "The last registration of Bob is now after that date so we should have only alice registration");
	}

	public function test_deleteRegistrationsOlderThan() {
		// Setup
		$debug = false;
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		$this->memberRepository->addOrUpdateMember($registrationBob, $debug);
		$this->memberRepository->addOrUpdateMember($registrationAlice, $debug);

		$this->assertEquals(2, count($this->memberRepository->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "Pre-condition: we should have 2 registrations at this stage");

		// Act
		$this->memberRepository->deleteMembersOlderThan(new DateTime("1900-01-01"), $debug);

		// Assert
		$this->assertEquals(1, count($this->memberRepository->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "1 (and only 1) registration should have been deleted, leaving only 1");
	}

	public function test_notificationHasBeenSentStatus() {
		// Setup
		$debug = false;
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		$this->memberRepository->addOrUpdateMember($registrationBob, $debug);
		$this->memberRepository->addOrUpdateMember($registrationAlice, $debug);

		// Act & assert: case 1: just after registration we haven't send a notification about anyone
		$members = $this->memberRepository->getMembersForWhichNoNotificationHasBeenSentToAdmins();
		$this->assertEquals(2, count($members));

		// Act & assert: case 2: we consider that notifications where sent for a member
		$bob = $this->memberRepository->findOneBy(['email' => "bob@dylan.com"]);
		$this->memberRepository->updateMembersForWhichNotificationHasBeenSentoToAdmins([$bob], $debug);

		$members = $this->memberRepository->getMembersForWhichNoNotificationHasBeenSentToAdmins();
		$this->assertEquals(1, count($members), "now, we did not sent notification about Alice only");
		$this->assertExpectedObjectMember($aliceRegistrationDate, $aliceRegistrationDate, "alice", "wonderland", "al@ice.com", $members[0]);

		//  Act & assert: case 3: if bob registers again we should not send a new notification about him
		$bobUpdateDate = "2020-09-08";
		$updateBob = $this->buildHelloassoEvent($bobUpdateDate, "bob", "dylan", "bob-new@email.com");
		$this->memberRepository->addOrUpdateMember($updateBob, $debug);

		$this->assertEquals(1, count($members), "now, we did not sent notification about Alice only");
		$this->assertExpectedObjectMember($aliceRegistrationDate, $aliceRegistrationDate, "alice", "wonderland", "al@ice.com", $members[0]);
	}

	public function test_getMembersPerPostalCode() {
		// Setup
		$this->memberRepository->addOrUpdateMember($this->buildHelloassoEvent("2020-01-01", "name1", "name1", "email1", "92100"), false); // Old one, should be ignored
		$this->memberRepository->addOrUpdateMember($this->buildHelloassoEvent("2023-01-01", "name2", "name2", "email2", "92100"), false);
		$this->memberRepository->addOrUpdateMember($this->buildHelloassoEvent("2023-01-01", "name3", "name3", "email3", "92100"), false);
		$this->memberRepository->addOrUpdateMember($this->buildHelloassoEvent("2023-01-01", "name4", "name4", "email4", "92100"), false);
		$this->memberRepository->addOrUpdateMember($this->buildHelloassoEvent("2023-01-01", "name5", "name5", "email5", "75018"), false);

		// Act
		$membersPerPostalCode = $this->memberRepository->getMembersPerPostalCode(\DateTime::createFromFormat(\DateTimeInterface::ISO8601, '2022-01-01T00:00:00Z'));

		// Assert
		$this->assertEquals(2, count($membersPerPostalCode));
		$this->assertEquals("92100", $membersPerPostalCode[0]["postalCode"]);
		$this->assertEquals(3, $membersPerPostalCode[0]["count"]);
		$this->assertEquals("75018", $membersPerPostalCode[1]["postalCode"]);
		$this->assertEquals(1, $membersPerPostalCode[1]["count"]);
	}

	public function test_debugModeDoesNotWrite() {
		// Setup
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		$this->memberRepository->addOrUpdateMember($registrationBob, false);
		$this->memberRepository->addOrUpdateMember($registrationAlice, false);

		// Act & Assert 1: can read
		$this->assertEquals(2, count($this->memberRepository->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "A debug connector should be able to read the existing members");

		// Act & Assert 2: can't insert or update
		$charlesRegistrationDate = "2020-09-08";
		$registrationCharles = $this->buildHelloassoEvent($charlesRegistrationDate, "Charles", "Edouard", "charles@something.com");
		$this->memberRepository->addOrUpdateMember($registrationCharles, true);
		$this->assertEquals(2, count($this->memberRepository->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "We should still have only 2 members because the last one was not persisted to db because of debug mode");

		// Act & Assert 3: can't delete
		$this->memberRepository->deleteMembersOlderThan(new DateTime("1900-01-01"), true);
		$this->assertEquals(2, count($this->memberRepository->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "We should still have only 2 members because we did not delete anything");

		// Act & Assert 4: can't update 'notification sent' status
		$this->assertEquals(2, count($this->memberRepository->getMembersForWhichNoNotificationHasBeenSentToAdmins()), "Precondition");
		$bob = $this->memberRepository->findOneBy(['email' => "bob@dylan.com"]);
		$this->memberRepository->updateMembersForWhichNotificationHasBeenSentoToAdmins([$bob], true);
		$this->assertEquals(2, count($this->memberRepository->getMembersForWhichNoNotificationHasBeenSentToAdmins()), "We should still consider we haven't sent notifications for 2 members because we didn't update any status");
	}

	private function assertExpectedMember(string $firstRegistrationDate, string $lastRegistrationDate, string $firstName, string $lastName, string $email, array $actualMember) {
		$this->assertEquals(new DateTimeImmutable($firstRegistrationDate), $actualMember["firstRegistrationDate"]);
		$this->assertEquals(new DateTimeImmutable($lastRegistrationDate), $actualMember["lastRegistrationDate"]);
		$this->assertEquals($firstName, $actualMember["firstName"]);
		$this->assertEquals($lastName, $actualMember["lastName"]);
		$this->assertEquals($email, $actualMember["email"]);
	}

	private function assertExpectedObjectMember(string $firstRegistrationDate, string $lastRegistrationDate, string $firstName, string $lastName, string $email, Member $actualMember) {
		$this->assertEquals(new DateTimeImmutable($firstRegistrationDate), $actualMember->getFirstRegistrationDate());
		$this->assertEquals(new DateTimeImmutable($lastRegistrationDate), $actualMember->getLastRegistrationDate());
		$this->assertEquals($firstName, $actualMember->getFirstName());
		$this->assertEquals($lastName, $actualMember->getLastName());
		$this->assertEquals($email, $actualMember->getEmail());
	}
}
