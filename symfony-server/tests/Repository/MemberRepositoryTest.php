<?php
declare(strict_types=1);

require_once __DIR__ . '/../TestHelperTrait.php';

use App\Repository\MemberRepository;
use App\Repository\MemberAdditionalEmailRepository;
use App\Models\RegistrationEvent;
use App\Entity\Member;
use App\Entity\MemberAdditionalEmail;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Services\NowProvider;

final class MemberRepositoryTest extends KernelTestCase {
	use TestHelperTrait;

	private MemberRepository $memberRepository;

	protected function setUp(): void {
		self::bootKernel();
	}

	public function test_addOrUpdateMember() {
		$debug = false;
		$sut = self::getContainer()->get(MemberRepository::class);
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		// Act to add members
		$sut->addOrUpdateMember($registrationBob, $debug);
		$sut->addOrUpdateMember($registrationAlice, $debug);

		// Assert on added members
		$members = $sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertEquals(2, count($members), "2 members have registered");
		$this->assertExpectedMember($aliceRegistrationDate, $aliceRegistrationDate, "alice", "wonderland", "al@ice.com", $members[0]);
		$this->assertExpectedMember($bobRegistrationDate, $bobRegistrationDate, "bob", "dylan", "bob@dylan.com", $members[1]);

		// Setup to update a member
		$bobUpdateDate = "2020-09-08";
		$updateBob = $this->buildHelloassoEvent($bobUpdateDate, "bob", "dylan", "bob@dylan.com");

		// Act to update Bob
		$sut->addOrUpdateMember($updateBob, $debug);

		// Assert on bob's update
		$members = $sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertEquals(2, count($members), "The last registration was an update, we should still have only 2 members");
		$this->assertExpectedMember($aliceRegistrationDate, $aliceRegistrationDate, "alice", "wonderland", "al@ice.com", $members[0]);
		$this->assertExpectedMember($bobRegistrationDate, $bobUpdateDate, "bob", "dylan", "bob@dylan.com", $members[1]);

		// Leverage the setup to assert on getOrderedListOfLastRegistrations behavior
		$members = $sut->getOrderedListOfLastRegistrations(new DateTime("1900-01-01"));
		$this->assertEquals(1, count($members), "There should be a single registration after the 'since' date passed");
	}

	public function test_noUpdateIsPerformedIfTheRegistrationHandledLastIsOlderThanTheCurrentData() {
		// Setup
		$debug = false;
		$sut = self::getContainer()->get(MemberRepository::class);
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$sut->addOrUpdateMember($registrationBob, $debug);

		// // Precondition check
		$members = $sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertExpectedMember($bobRegistrationDate, $bobRegistrationDate, "bob", "dylan", "bob@dylan.com", $members[0]);

		// Act 1: add a previous registration
		$bobPreviousRegistrationDate = "1985-01-01";
		$otherRegistrationBob = $this->buildHelloassoEvent($bobPreviousRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$sut->addOrUpdateMember($otherRegistrationBob, $debug);

		// Assert 1
		$members = $sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertExpectedMember($bobPreviousRegistrationDate, $bobRegistrationDate, "bob", "dylan", "bob@dylan.com", $members[0]);

		// Act 2: add a registration in between
		$bobInBetweenRegistrationDate = "1985-02-01";
		$inBetweenRegistrationBob = $this->buildHelloassoEvent($bobInBetweenRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$sut->addOrUpdateMember($inBetweenRegistrationBob, $debug);

		// Assert 2
		$members = $sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"));
		$this->assertExpectedMember($bobPreviousRegistrationDate, $bobRegistrationDate, "bob", "dylan", "bob@dylan.com", $members[0]);
	}

	public function test_getListOfRegistrationsOlderThan() {
		// Setup
		$debug = false;
		$sut = self::getContainer()->get(MemberRepository::class);
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		// Act
		$sut->addOrUpdateMember($registrationBob, $debug);
		$sut->addOrUpdateMember($registrationAlice, $debug);

		// Assert
		$this->assertEquals(1, count($sut->getListOfRegistrationsOlderThan(new DateTime("1900-01-01"))), "only 1 member registered before that date");
		$this->assertEquals(2, count($sut->getListOfRegistrationsOlderThan(new DateTime("2025-01-01"))), "all 2 members registered before that date");

		// Act & assert again to make sure the last registration date is taken into account
		$bobUpdateDate = "2030-09-08";
		$updateBob = $this->buildHelloassoEvent($bobUpdateDate, "bob", "dylan", "bob@dylan.com");
		$sut->addOrUpdateMember($updateBob, $debug);
		$this->assertEquals(1, count($sut->getListOfRegistrationsOlderThan(new DateTime("2025-01-01"))), "The last registration of Bob is now after that date so we should have only alice registration");
	}

	public function test_deleteRegistrationsOlderThan() {
		// Setup
		$debug = false;
		$sut = self::getContainer()->get(MemberRepository::class);
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		$sut->addOrUpdateMember($registrationBob, $debug);
		$sut->addOrUpdateMember($registrationAlice, $debug);

		$this->assertEquals(2, count($sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "Pre-condition: we should have 2 registrations at this stage");

		// Act
		$sut->deleteMembersOlderThan(new DateTime("1900-01-01"), $debug);

		// Assert
		$this->assertEquals(1, count($sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "1 (and only 1) registration should have been deleted, leaving only 1");
	}

	public function test_notificationHasBeenSentStatus() {
		// Setup
		$debug = false;
		$sut = self::getContainer()->get(MemberRepository::class);
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		$sut->addOrUpdateMember($registrationBob, $debug);
		$sut->addOrUpdateMember($registrationAlice, $debug);

		// Act & assert: case 1: just after registration we haven't send a notification about anyone
		$members = $sut->getMembersForWhichNoNotificationHasBeenSentToAdmins();
		$this->assertEquals(2, count($members));

		// Act & assert: case 2: we consider that notifications where sent for a member
		$bob = $sut->findOneBy(['email' => "bob@dylan.com"]);
		$sut->updateMembersForWhichNotificationHasBeenSentoToAdmins([$bob], $debug);

		$members = $sut->getMembersForWhichNoNotificationHasBeenSentToAdmins();
		$this->assertEquals(1, count($members), "now, we did not sent notification about Alice only");
		$this->assertExpectedMember($aliceRegistrationDate, $aliceRegistrationDate, "alice", "wonderland", "al@ice.com", $members[0]);

		//  Act & assert: case 3: if bob registers again we should not send a new notification about him
		$bobUpdateDate = "2020-09-08";
		$updateBob = $this->buildHelloassoEvent($bobUpdateDate, "bob", "dylan", "bob-new@email.com");
		$sut->addOrUpdateMember($updateBob, $debug);

		$this->assertEquals(1, count($members), "now, we did not sent notification about Alice only");
		$this->assertExpectedMember($aliceRegistrationDate, $aliceRegistrationDate, "alice", "wonderland", "al@ice.com", $members[0]);
	}

	public function test_canSetAndGetAndDeleteAdditionalEmails() {
		// Setup
		$debug = false;
		$sut = self::getContainer()->get(MemberRepository::class);
		$registration = $this->buildHelloassoEvent("1985-04-03", "bob", "dylan", "primary@email.com");
		$sut->addOrUpdateMember($registration, $debug);
		$member = $sut->findOneBy(['email' => "primary@email.com"]);
		$additionalEmailsRepository = static::getContainer()->get(MemberAdditionalEmailRepository::class);

		$this->assertEquals(0, count($member->getAdditionalEmails()));

		// Act & Assert 1: can write and read additional emails
		$this->assertTrue($member->addAdditionalEmail("secondary@email.com"), "should be TRUE because we can add this email");
		$this->assertFalse($member->addAdditionalEmail("secondary@email.com", "should be FALSE because the email was already registered"));
		// // Make sure we can register several additional emails for a member (since we had issues with that)
		$member->addAdditionalEmail("second_secondary@email.com");

		$sut->save($member, true);

		$readAgainMember = $sut->findOneBy(['email' => "primary@email.com"]);
		// // Assert can get all additional emails of a member
		$this->assertEquals(2, count($readAgainMember->getAdditionalEmails()));
		$this->assertEquals("secondary@email.com", $readAgainMember->getAdditionalEmails()[0]);
		$this->assertEquals("second_secondary@email.com", $readAgainMember->getAdditionalEmails()[1]);

		// // Assert can check if a member has a given additional email
		$this->assertTrue($member->hasAdditionalEmail("secondary@email.com"));
		$this->assertTrue($member->hasAdditionalEmail("second_secondary@email.com"));
		$this->assertFalse($member->hasAdditionalEmail("unknown@email.com"));

		// Act & Assert 2: can delete additional emails
		$this->assertFalse($member->rmAdditionalEmail("unknown@email.com", $additionalEmailsRepository), "should be FALSE because we can't delete an unkown email");
		$this->assertTrue($member->rmAdditionalEmail("secondary@email.com", $additionalEmailsRepository), "should be TRUE because we can delete this email");
		$sut->save($member, true);

		$readAgainMember = $sut->findOneBy(['email' => "primary@email.com"]);
		$this->assertEquals(1, count($readAgainMember->getAdditionalEmails()));
		$this->assertEquals("second_secondary@email.com", $readAgainMember->getAdditionalEmails()[0]);
		$this->assertFalse($readAgainMember->hasAdditionalEmail("secondary@email.com", true));
		$this->assertTrue($readAgainMember->hasAdditionalEmail("second_secondary@email.com"));

		// Act & Assert 3: deleting member also delete the associated additional emails
		$this->assertEquals(1, count($additionalEmailsRepository->findAll()), "precondition: we should get the existing email");
		$sut->deleteMember("primary@email.com");
		$this->assertEquals(0, count($additionalEmailsRepository->findAll()), "no member and hence no additional email should be left");
	}

	public function test_getMembersPerPostalCode() {
		// Setup
		$sut = self::getContainer()->get(MemberRepository::class);
		$sut->addOrUpdateMember($this->buildHelloassoEvent("2020-01-01", "name1", "name1", "email1", "92100"), false); // Old one, should be ignored
		$sut->addOrUpdateMember($this->buildHelloassoEvent("2023-01-01", "name2", "name2", "email2", "92100"), false);
		$sut->addOrUpdateMember($this->buildHelloassoEvent("2023-01-01", "name3", "name3", "email3", "92100"), false);
		$sut->addOrUpdateMember($this->buildHelloassoEvent("2023-01-01", "name4", "name4", "email4", "92100"), false);
		$sut->addOrUpdateMember($this->buildHelloassoEvent("2023-01-01", "name5", "name5", "email5", "75018"), false);

		// Act
		$membersPerPostalCode = $sut->getMembersPerPostalCode(new \DateTime('2022-01-01T00:00:00Z'));

		// Assert
		$this->assertEquals(2, count($membersPerPostalCode));
		$this->assertEquals("92100", $membersPerPostalCode[0]["postalCode"]);
		$this->assertEquals(3, $membersPerPostalCode[0]["count"]);
		$this->assertEquals("75018", $membersPerPostalCode[1]["postalCode"]);
		$this->assertEquals(1, $membersPerPostalCode[1]["count"]);
	}

	public function test_getAllUpToDateMembers() {
		// Setup
		$nowProvider = $this->createMock(NowProvider::class);
		$nowProvider->method('getNow')->willReturn(new \DateTime("2020-09-08"));
		self::getContainer()->set(NowProvider::class, $nowProvider);

		$sut = self::getContainer()->get(MemberRepository::class);
	
		$oldRegistration = $this->buildHelloassoEvent("1985-03-04", "old", "member", "old@mail.com");
		$sut->addOrUpdateMember($oldRegistration, false);

		$upToDateRegistration= $this->buildHelloassoEvent("2020-03-04", "young", "member", "young@mail.com");
		$sut->addOrUpdateMember($upToDateRegistration, false);

		$this->assertEquals(2, count($sut->findAll()), "Precondition: we should have 2 members in total");

		// Act & Assert
		$upToDateRegistrations = $sut->getAllUpToDateMembers();
		$this->assertEquals(1, count($upToDateRegistrations));
		$this->assertEquals("young", $upToDateRegistrations[0]->getFirstName());
	}

	public function test_debugModeDoesNotWrite() {
		// Setup
		$sut = self::getContainer()->get(MemberRepository::class);
		$bobRegistrationDate = "1985-04-03";
		$registrationBob = $this->buildHelloassoEvent($bobRegistrationDate, "bob", "dylan", "bob@dylan.com");
		$aliceRegistrationDate = "1865-11-01";
		$registrationAlice = $this->buildHelloassoEvent($aliceRegistrationDate, "alice", "wonderland", "al@ice.com");

		$sut->addOrUpdateMember($registrationBob, false);
		$sut->addOrUpdateMember($registrationAlice, false);

		// Act & Assert 1: can read
		$this->assertEquals(2, count($sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "A debug connector should be able to read the existing members");

		// Act & Assert 2: can't insert or update
		$charlesRegistrationDate = "2020-09-08";
		$registrationCharles = $this->buildHelloassoEvent($charlesRegistrationDate, "Charles", "Edouard", "charles@something.com");
		$sut->addOrUpdateMember($registrationCharles, true);
		$this->assertEquals(2, count($sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "We should still have only 2 members because the last one was not persisted to db because of debug mode");

		// Act & Assert 3: can't delete
		$sut->deleteMembersOlderThan(new DateTime("1900-01-01"), true);
		$this->assertEquals(2, count($sut->getOrderedListOfLastRegistrations(new DateTime("1800-01-01"))), "We should still have only 2 members because we did not delete anything");

		// Act & Assert 4: can't update 'notification sent' status
		$this->assertEquals(2, count($sut->getMembersForWhichNoNotificationHasBeenSentToAdmins()), "Precondition");
		$bob = $sut->findOneBy(['email' => "bob@dylan.com"]);
		$sut->updateMembersForWhichNotificationHasBeenSentoToAdmins([$bob], true);
		$this->assertEquals(2, count($sut->getMembersForWhichNoNotificationHasBeenSentToAdmins()), "We should still consider we haven't sent notifications for 2 members because we didn't update any status");
	}

	private function assertExpectedMember(string $firstRegistrationDate, string $lastRegistrationDate, string $firstName, string $lastName, string $email, Member $actualMember) {
		$this->assertEquals(new DateTimeImmutable($firstRegistrationDate), $actualMember->getFirstRegistrationDate());
		$this->assertEquals(new DateTimeImmutable($lastRegistrationDate), $actualMember->getLastRegistrationDate());
		$this->assertEquals($firstName, $actualMember->getFirstName());
		$this->assertEquals($lastName, $actualMember->getLastName());
		$this->assertEquals($email, $actualMember->getEmail());
	}
}
