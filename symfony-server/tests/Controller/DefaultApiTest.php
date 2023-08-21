<?php

namespace App\Tests\Controller;
require_once __DIR__ . '/../TestHelperTrait.php';

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use App\Entity\Member;
use App\Models\RegistrationEvent;
use App\Repository\MemberRepository;
use App\Services\NowProvider;
use App\Controller\DefaultApi;

use OpenAPI\Server\Model\ApiMembersSortedByLastRegistrationDateGet200ResponseInner;

class DefaultApiTest extends KernelTestCase {
	use \TestHelperTrait;

	private array $members = array();
	private DefaultApi $sut;

	// placeholders to be passed by reference
	private $responseCode = 0;
	private array $responseHeaders = array();

	protected function setUp(): void {
		// Some plumbing
		$this->members = array();
		self::bootKernel();

		// Setup placeholder members
		// (Nb: we rRegister in "random" order so that when we test how the API response are sorted, it does not work "by chance")
		$memberRepo = self::getContainer()->get(MemberRepository::class);
		$this->buildAndRegisterMember($memberRepo, "2023-09-08", "tata", "somename", "tata@mail.com", "75018");
		$this->buildAndRegisterMember($memberRepo, "2023-11-08", "toto", "somename", "toto@mail.com", "92100");
		$this->buildAndRegisterMember($memberRepo, "2023-03-04", "titi", "somename", "titi@mail.com", "92100");
		$this->buildAndRegisterMember($memberRepo, "2020-01-01", "soOld", "shouldntBeReturned", "old@mail.com", "75018");

		// Setup "now"
		$nowProviderMock = $this->createMock(NowProvider::class);
		$nowProviderMock->method('getNow')->willReturn(new \DateTime("2023-12-01"));
		self::getContainer()->set(NowProvider::class, $nowProviderMock);

		// Final plumbing
		$this->sut = self::getContainer()->get(DefaultApi::class);
	}

	public function test_apiMembersSortedByLastRegistrationDateGet(): void {
		// Test with a "since" parameter
		$this->assertEquals(
			array($this->members["2023-09-08"], $this->members["2023-11-08"]),
			$this->sut->apiMembersSortedByLastRegistrationDateGet(new \DateTime("2023-04-01"), $this->responseCode, $this->responseHeaders));

		// Test without "since" parameter
		$this->assertEquals(
			array($this->members["2023-03-04"], $this->members["2023-09-08"], $this->members["2023-11-08"]),
			$this->sut->apiMembersSortedByLastRegistrationDateGet(null, $this->responseCode, $this->responseHeaders));
	}

	private function buildAndRegisterMember(MemberRepository $repo, string $event_date, string $first_name, string $last_name, string $email, $postal_code): void {
		$event = $this->buildHelloassoEvent($event_date, $first_name, $last_name, $email, $postal_code);
		$repo->addOrUpdateMember($event, false);
		$this->members[$event_date] = $this->buildApiMembersSortedByLastRegistrationDateGet200ResponseInner($event);
	}

	private function buildApiMembersSortedByLastRegistrationDateGet200ResponseInner(RegistrationEvent $event): ApiMembersSortedByLastRegistrationDateGet200ResponseInner {
		$ret = new ApiMembersSortedByLastRegistrationDateGet200ResponseInner();

		$ret->setHelloAssoLastRegistrationEventId($event->helloasso_event_id);
		$ret->setFirstName($event->first_name);
		$ret->setLastName($event->last_name);
		$ret->setEmail($event->email);
		$ret->setPostalCode($event->postal_code);
		$ret->setCity($event->city);
		$ret->setHowDidYouKnowZwp($event->how_did_you_know_zwp);
		$ret->setWantToDo($event->want_to_do);
		$ret->setFirstRegistrationDate(new \DateTime($event->event_date)); // because these tests don't make user register twice
		$ret->setIsZWProfessional(false);

		return $ret;
	}
}
