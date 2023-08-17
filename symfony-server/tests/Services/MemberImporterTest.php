<?php

declare(strict_types=1);
require_once __DIR__ . '/../TestHelperTrait.php';

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use PHPUnit\Framework\Constraint\ObjectEquals;

use App\Models\RegistrationEvent;
use App\Repository\OptionsRepository;
use App\Services\MemberImporter;
use App\Services\HelloAssoConnector;
use App\Services\MailchimpConnector;
use App\Services\GoogleGroupService;
use App\Services\SlackService;

final class MemberImporterTest extends KernelTestCase {
	use TestHelperTrait;

	public function test_happyPath(): void {
		// Setup
		$now = \DateTime::createFromFormat(\DateTimeInterface::ISO8601, '2020-09-08T06:30:00Z');
		$registrationEvent = $this->buildHelloassoEvent('1984-03-04T09:30:00Z', 'firstName', 'lastName', 'me@myself.com'
		);

		self::bootKernel();
		$this->setLastSuccessfulRunDate(\DateTime::createFromFormat(\DateTimeInterface::ISO8601, '2020-09-08T01:00:00Z'));
		$this->setHelloAssoMock($registrationEvent);
		$this->setMailchimpMock($registrationEvent);
		$this->setGoogleMock($registrationEvent);
		$this->setMailMock();
		$this->setSlackMock();

		$sut = self::getContainer()->get(MemberImporter::class);

		// Act
		$sut->runNow(false, $now);
	}

	private function setLastSuccessfulRunDate(\DateTime $now): void {
		static::getContainer()->get(OptionsRepository::class)->writeLastSuccessfulRunDate($now, false);
	}

	private function setHelloAssoMock(RegistrationEvent $registrationEvent): void {
		$helloAssoConnector = $this->createMock(HelloAssoConnector::class);
		$helloAssoConnector->expects(self::once())
			->method('getAllHelloAssoSubscriptions')
			->willReturn([$registrationEvent]);
		self::getContainer()->set(HelloAssoConnector::class, $helloAssoConnector);
	}

	private function setMailMock(): void {
		$mailerMock = $this->createMock(MailerInterface::class);
		$mailerMock->expects(self::never())->method('send');
		self::getContainer()->set(MailerInterface::class, $mailerMock);
	}

	private function setSlackMock(): void {
		$slackMock = $this->createMock(SlackService::class);
		$slackMock->expects(self::once())->method('findDeactivatedMembers');
		self::getContainer()->set(SlackService::class, $slackMock);
	}

	private function setMailchimpMock(RegistrationEvent $expectedRegistrationEvent): void {
		$this->setGroupMock(MailchimpConnector::class, $expectedRegistrationEvent);
	}

	private function setGoogleMock(RegistrationEvent $expectedRegistrationEvent): void {
		$this->setGroupMock(GoogleGroupService::class, $expectedRegistrationEvent);
	}

	private function setGroupMock($class, RegistrationEvent $expectedRegistrationEvent): void {
		$mock = $this->createMock($class);
		$mock->expects(self::once())
			->method('registerEvent')
			->with(new ObjectEquals($expectedRegistrationEvent));
		self::getContainer()->set($class, $mock);
	}
}
