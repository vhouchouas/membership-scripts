<?php
declare(strict_types=1);
define('ZWP_TOOLS', __DIR__ . '/../files/');
require_once(ZWP_TOOLS . 'registrationDateUtil.php');

use PHPUnit\Framework\TestCase;

final class Test_RegistrationDateUtil extends TestCase {
  public function test_getDateAfterWhichMembershipIsConsideredValid(){
    $expected = new DateTime("2019-01-01T00:00:00", new DateTimeZone("Europe/Paris"));

    $this->assertCorrectValidMembershipDate($expected, new DateTime("2019-04-03Z"), "Usual case: membership is valid after January 1st");
    $this->assertCorrectValidMembershipDate($expected, new DateTime("2020-01-03Z"), "Business rule: before 1st February we keep all those who registered during year N-1");
    $this->assertCorrectValidMembershipDate($expected, new DateTime("2019-02-01T00:00:00", new DateTimeZone("Europe/Paris")), "Cornercase: we're at 1st February 00h00");
    $this->assertCorrectValidMembershipDate($expected, new DateTime("2020-01-31T23:23:59", new DateTimeZone("Europe/Paris")), "Cornercase: we're 1s before 1st February");
  }

  private function assertCorrectValidMembershipDate(DateTime $expected, DateTime $now, string $explanation): void{
    $unusedArrayOfConnectors = array();
    $sut = new RegistrationDateUtil($now, $unusedArrayOfConnectors);
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
    $unusedArrayOfConnectors = array();
    $sut = new OutdatedMemberManager($now, $unusedArrayOfConnectors);
    $this->assertEquals($expected, $sut->needToDeleteOutdatedMembers($lastRun), $explanation);
  }
}
