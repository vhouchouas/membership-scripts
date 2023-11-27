<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\RegistrationDateUtil;
use App\Services\NowProvider;

final class RegistrationDateUtilTest extends TestCase {
	public function test_getDateAfterWhichMembershipIsConsideredValid(){
		$expected = new DateTime("2018-09-01T00:00:00", new DateTimeZone("Europe/Paris")); // According to current status

		$this->assertCorrectValidMembershipDate($expected, new DateTime("2019-04-03Z"), "Usual case: membership is valid after January 1st");
		$this->assertCorrectValidMembershipDate($expected, new DateTime("2020-01-03Z"), "Business rule: before 1st February we keep all those who registered during year N-1");
		$this->assertCorrectValidMembershipDate($expected, new DateTime("2019-02-01T00:00:00", new DateTimeZone("Europe/Paris")), "Cornercase: we're at 1st February 00h00");
		$this->assertCorrectValidMembershipDate($expected, new DateTime("2020-01-31T23:23:59", new DateTimeZone("Europe/Paris")), "Cornercase: we're 1s before 1st February");
	}

	private function assertCorrectValidMembershipDate(DateTime $expected, DateTime $now, string $explanation): void{
		$sut = $this->buildRegistrationDateUtil($now);
		$this->assertEquals($expected, $sut->getDateAfterWhichMembershipIsConsideredValid(), $explanation);
	}

	public function test_needToDeleteOutdatedMember(){
		$test_case = "We're in plain summer, deletion shouldn't run";
		$now = new DateTime("2019-08-17Z");
		$lastRun = new DateTime("2019-08-10Z");
		$this->assertCorrectlyDetectNeedToDeleteOutdatedMember(false, $now, $lastRun, $test_case);

		$test_case = "Last run was before deadline, we're now after it. Deletion should run";
		$now = new DateTime("2019-02-02Z");
		$lastRun = new DateTime("2019-01-31Z");
		$this->assertCorrectlyDetectNeedToDeleteOutdatedMember(true, $now, $lastRun, $test_case);

		$test_case = "Corner case: we're at exactly the dead line. Deletion should run";
		$now = new DateTime("2019-02-01T00:00:00", new DateTimeZone("Europe/Paris"));
		$lastRun = new DateTime("2019-01-31Z");
		$this->assertCorrectlyDetectNeedToDeleteOutdatedMember(true, $now, $lastRun, $test_case);

		$test_case = "Corner case: last run was exactly at the dead line. Deletion hence already occured and shouldn't run now";
		$now = new DateTime("2019-02-02Z");
		$lastRun = new DateTime("2019-02-01T00:00:00", new DateTimeZone("Europe/Paris"));
		$this->assertCorrectlyDetectNeedToDeleteOutdatedMember(false, $now, $lastRun, $test_case);
	}

	private function assertCorrectlyDetectNeedToDeleteOutdatedMember(bool $expected, DateTime $now, DateTime $lastRun, string $explanation){
		$sut = $this->buildRegistrationDateUtil($now);
		$this->assertEquals($expected, $sut->needToDeleteOutdatedMembers($lastRun), $explanation);
	}

	public function test_needToSendNotificationAboutLatestRegistrations(){
		date_default_timezone_set("Europe/Paris");
		$now = new DateTime("2020-08-08T00:00:00", new DateTimeZone("Europe/Paris")); // Saturday
		$sut = $this->buildRegistrationDateUtil($now);

		// Test with a few days before or after the deadline
		$this->assertCorrectlyDetectNeedToSendNotificationAboutLatestRegistrations(true, $now, new DateTime("2020-08-04"), "last run occured on Tuesday so it's time to send notification");
		$this->assertCorrectlyDetectNeedToSendNotificationAboutLatestRegistrations(false, $now, new DateTime("2020-08-06"), "last run occured on Friday so we don't need to send notification");

		// Test with a few seconds before or after the deadline
		$oneSecondBefore = new DateTime("2020-08-05T17:59:59", new DateTimeZone("Europe/Paris"));
		$oneSecondAfter  = new DateTime("2020-08-05T18:00:01", new DateTimeZone("Europe/Paris"));
		$twoSecondsAfter = new DateTime("2020-08-05T18:00:02", new DateTimeZone("Europe/Paris"));
		$this->assertCorrectlyDetectNeedToSendNotificationAboutLatestRegistrations(true, $oneSecondAfter, $oneSecondBefore, "last run occured a few second before deadline. We're a few second after. We should send the notif");
		$this->assertCorrectlyDetectNeedToSendNotificationAboutLatestRegistrations(false, $twoSecondsAfter, $oneSecondAfter, "last run occured 1 second after this week's deadline so we don't need to send the notif");
	}

	private function assertCorrectlyDetectNeedToSendNotificationAboutLatestRegistrations(bool $expected, DateTime $now, DateTime $lastRun, string $explanation){
		$sut = $this->buildRegistrationDateUtil($now);
		$this->assertEquals($expected, $sut->needToSendNotificationAboutLatestRegistrations($lastRun), $explanation);
	}

	public function test_getDateBeforeWhichAllRegistrationsHaveBeenHandled() {
		$lastSuccesfulRun = new DateTime("2021-02-10T19:16:00Z");
		$enoughInThePast = new DateTime("2021-02-10T18:16:00Z");
		$this->assertEquals($enoughInThePast, RegistrationDateUtil::getDateBeforeWhichAllRegistrationsHaveBeenHandled($lastSuccesfulRun));
		$this->assertEquals(new DateTime("2021-02-10T19:16:00Z"), $lastSuccesfulRun, "the input parameter shouldn't be mutated");
	}

	private function buildRegistrationDateUtil(\DateTime $now): RegistrationDateUtil {
		$nowProviderMock = $this->createMock(NowProvider::class);
		$nowProviderMock->method('getNow')->willReturn($now);

		return new RegistrationDateUtil($nowProviderMock);
	}
}
