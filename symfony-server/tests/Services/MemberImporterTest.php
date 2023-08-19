<?php

declare(strict_types=1);
require_once __DIR__ . '/../TestHelperTrait.php';

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use PHPUnit\Framework\Constraint\ObjectEquals;

use App\Models\RegistrationEvent;
use App\Repository\MemberRepository;
use App\Repository\OptionsRepository;
use App\Services\MemberImporter;
use App\Services\HelloAssoConnector;
use App\Services\MailchimpConnector;
use App\Services\GoogleGroupService;
use App\Services\EmailService;

final class MemberImporterTest extends KernelTestCase {
	use TestHelperTrait;

	private EmailService $mailMock;
	private OptionsRepository $optionRepoMock;
	private MemberRepository $memberRepoMock;
	private HelloAssoConnector $helloAssoMock;
	private GoogleGroupService $googleMock;
	private MailchimpConnector $mailchimpMock;


	protected function setUp(): void {
		self::bootKernel();

		$this->mailMock       = $this->createMock(EmailService::class);
		$this->optionRepoMock = $this->createMock(OptionsRepository::class);
		$this->memberRepoMock = $this->createMock(MemberRepository::class);
		$this->helloAssoMock  = $this->createMock(HelloAssoConnector::class);
		$this->googleMock     = $this->createMock(GoogleGroupService::class);
		$this->mailchimpMock  = $this->createMock(MailchimpConnector::class);

		$this->mailMock->expects(self::once())->method('sendEmailAboutSlackMembersToReactivate');
	}

	private function registerAllMockInContainer(): void {
		self::getContainer()->set(EmailService::class,       $this->mailMock);
		self::getContainer()->set(OptionsRepository::class,  $this->optionRepoMock);
		self::getContainer()->set(MemberRepository::class,   $this->memberRepoMock);
		self::getContainer()->set(HelloAssoConnector::class, $this->helloAssoMock);
		self::getContainer()->set(GooGleGroupService::class, $this->googleMock);
		self::getContainer()->set(MailchimpConnector::class, $this->mailchimpMock);
	}

	public function test_happyPath(): void {
		// Setup
		$now = new \DateTime('2020-09-08T06:30:00Z');
		$lastSuccessfulRunDate = new \DateTime('2020-09-08T01:00:00Z');
		$registrationEvent = $this->buildHelloassoEvent('1984-03-04T09:30:00Z', 'firstName', 'lastName', 'me@myself.com');

		$this->expectsEventRegistration($registrationEvent);
		$this->expectsOldMembersAreNotDeleted();
		$this->expectsNoNotificationsAreSentAboutNewcomers();
		$this->setOptionsRepositoryMock($now, $lastSuccessfulRunDate);

		$this->registerAllMockInContainer();


		// Act
		$sut = self::getContainer()->get(MemberImporter::class);
		$sut->runNow(false, $now);
	}

	private function setOptionsRepositoryMock(\DateTime $now, \DateTime $lastSuccessfulRunDate): void {
		$this->optionRepoMock->expects(self::once())->method('getLastSuccessfulRunDate')->willReturn($lastSuccessfulRunDate);
		$this->optionRepoMock->expects(self::once())->method('writeLastSuccessfulRunDate')->with($this->equalTo($now));
	}

	private function expectsEventRegistration(RegistrationEvent $expected): void {
		$this->helloAssoMock->expects(self::once())->method('getAllHelloAssoSubscriptions')->willReturn([$expected]);
		$this->googleMock->expects(self::once())->method('registerEvent')->with(new ObjectEquals($expected));
		$this->mailchimpMock->expects(self::once())->method('registerEvent')->with(new ObjectEquals($expected));
		$this->memberRepoMock->expects(self::once())->method('addOrUpdateMember')->with(new ObjectEquals($expected));
	}

	private function expectsNoNotificationsAreSentAboutNewcomers(): void {
		$this->mailMock->expects(self::never())->method('sendNotificationForAdminsAboutNewcomers');
		$this->memberRepoMock->expects(self::never())->method('updateMembersForWhichNotificationHasBeenSentoToAdmins');
	}

	private function expectsOldMembersAreNotDeleted(): void {
		$this->memberRepoMock->expects(self::never())->method('deleteMembersOlderThan');
		$this->googleMock->expects(self::never())->method('deleteUser');
		$this->mailchimpMock->expects(self::never())->method('deleteUser');
	}
}
