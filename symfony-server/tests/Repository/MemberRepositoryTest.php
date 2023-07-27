<?php
declare(strict_types=1);

use App\Repository\MemberRepository;
use App\Models\RegistrationEvent;
use App\Entity\Member;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MemberRepositoryTest extends KernelTestCase {
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

	private $lastHelloAssoEventId = 0;
	private function buildHelloassoEvent($event_date, $first_name, $last_name, $email): RegistrationEvent {
		$ret = new RegistrationEvent();
		$ret->event_date = $event_date;
		$ret->first_name = $first_name;
		$ret->last_name = $last_name;
		$ret->email = $email;
		$ret->postal_code = "75000";
		$ret->city = "Paris";
		$ret->how_did_you_know_zwp = "";
		$ret->want_to_do = "";
		$ret->is_zw_professional = "Non";

		$ret->helloasso_event_id = (string) $this->lastHelloAssoEventId;
		$this->lastHelloAssoEventId++;

		return $ret;
	}

	private function assertExpectedMember(string $firstRegistrationDate, string $lastRegistrationDate, string $firstName, string $lastName, string $email, array $actualMember) {
		$this->assertEquals(new DateTimeImmutable($firstRegistrationDate), $actualMember["firstRegistrationDate"]);
		$this->assertEquals(new DateTimeImmutable($lastRegistrationDate), $actualMember["lastRegistrationDate"]);
		$this->assertEquals($firstName, $actualMember["firstName"]);
		$this->assertEquals($lastName, $actualMember["lastName"]);
		$this->assertEquals($email, $actualMember["email"]);
	}
}
