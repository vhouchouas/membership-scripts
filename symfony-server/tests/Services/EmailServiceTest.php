<?php

declare(strict_types=1);

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Services\EmailService;
use App\Services\SlackService;
use App\Entity\Member;
use App\Models\SlackMembersTimestamped;

final class EmailServiceTest extends KernelTestCase {

	public function test_sendNotificationForAdminsAboutNewcomersWhenThereAreNewMembers(): void {
		// Setup
		$this->setMailMock(true);
		$debug = false;
		$sut = self::getContainer()->get(EmailService::class);
		$newMember = new Member();
		$newMember->setLastRegistrationDate(new \DateTime());

		// Act (assertion is on the mock)
		$sut->sendNotificationForAdminsAboutNewcomers([$newMember], $debug);
	}

	public function test_sendNotificationForAdminsAboutNewcomersWhenThereAreNoNewMembers(): void {
		// Setup
		$this->setMailMock(true);
		$debug = false;
		$sut = self::getContainer()->get(EmailService::class);

		// Act (assertion is on the mock)
		$sut->sendNotificationForAdminsAboutNewcomers([], $debug);
	}

	public function test_dontSendNotificationForAdminsAboutNewcomersInDebugMode(): void {
		// Setup
		$this->setMailMock(false);
		$debug = true;
		$sut = self::getContainer()->get(EmailService::class);

		// Act (assertion is on the mock)
		$sut->sendNotificationForAdminsAboutNewcomers(array(), $debug);
	}

	public function test_sendEmailAboutSlackMembersToReactivate(): void {
		// Setup
		$this->setSlackMock(["someone@mail.com"]);
		$this->setMailMock(true);
		$debug = false;
		$sut = self::getContainer()->get(EmailService::class);

		// Act (assertions are on the mocks)
		$sut->sendEmailAboutSlackMembersToReactivate($debug);
	}

	public function test_dontSendEmailAboutSlackMembersToReactivateInDebugMode(): void {
		// Setup
		$this->setSlackMock(["someone@mail.com"]);
		$this->setMailMock(false);
		$debug = true;
		$sut = self::getContainer()->get(EmailService::class);

		// Act (assertions are on the mocks)
		$sut->sendEmailAboutSlackMembersToReactivate($debug);
	}

	public function test_dontSendEmailAboutSlackMembersToReactivateIfThereAreNoSuchMembers(): void {
		// Setup
		$this->setSlackMock([]);
		$this->setMailMock(false);
		$debug = false;
		$sut = self::getContainer()->get(EmailService::class);

		// Act (assertions are on the mocks)
		$sut->sendEmailAboutSlackMembersToReactivate($debug);
	}

	private function setMailMock(bool $expectMailToBeSent): void {
		$mailerMock = $this->createMock(MailerInterface::class);
		$mailerMock->expects($expectMailToBeSent ? self::once() : self::never())->method('send');
		self::getContainer()->set(MailerInterface::class, $mailerMock);
	}

	private function setSlackMock(array $deactivatedMembers): void {
		$slackMock = $this->createMock(SlackService::class);
		$slackMock->expects(self::once())->method('findDeactivatedMembers')->willReturn(new SlackMembersTimestamped((new \DateTime())->getTimestamp(), $deactivatedMembers, true));
		self::getContainer()->set(SlackService::class, $slackMock);
	}
}
