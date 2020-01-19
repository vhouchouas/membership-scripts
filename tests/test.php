<?php
declare(strict_types=1);
define('ZWP_TOOLS', __DIR__ . '/../files/');
require_once(ZWP_TOOLS . 'util.php');
require_once(ZWP_TOOLS . 'mysql.php');

use PHPUnit\Framework\TestCase;

final class Test_OutdatedMemberDeleter extends TestCase {
  public function test_getDateAfterWhichMembershipIsConsideredValid(){
    $expected = new DateTime("2019-01-01T00:00:00", new DateTimeZone("Europe/Paris"));

    $this->assertCorrectValidMembershipDate($expected, new DateTime("2019-04-03Z"), "Usual case: membership is valid after January 1st");
    $this->assertCorrectValidMembershipDate($expected, new DateTime("2020-01-03Z"), "Business rule: before 1st February we keep all those who registered during year N-1");
    $this->assertCorrectValidMembershipDate($expected, new DateTime("2019-02-01T00:00:00", new DateTimeZone("Europe/Paris")), "Cornercase: we're at 1st February 00h00");
    $this->assertCorrectValidMembershipDate($expected, new DateTime("2020-01-31T23:23:59", new DateTimeZone("Europe/Paris")), "Cornercase: we're 1s before 1st February");
  }

  private function assertCorrectValidMembershipDate(DateTime $expected, DateTime $now, string $explanation): void{
    $unusedArrayOfConnectors = array();
    $deleter = new OutdatedMemberDeleter($now, $unusedArrayOfConnectors);
    $this->assertEquals($expected, $deleter->getDateAfterWhichMembershipIsConsideredValid(), $explanation);
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
    $deleter = new OutdatedMemberDeleter($now, $unusedArrayOfConnectors);
    $this->assertEquals($expected, $deleter->needToDeleteOutdatedMembers($lastRun), $explanation);
  }

  public function test_deleteExpectedUsers(){
    // Setup
    // // Create groups with users to delete
    $group1 = $this->createMock(GroupWithDeletableUsers::class);
    $group1->method('getUsers')->willReturn(array('userOk1', 'userToDelete1'));
    $group1->expects($this->once())->method('deleteUsers')->with($this->equalTo(array(1 => 'userToDelete1')));

    $group2 = $this->createMock(GroupWIthDeletableUsers::class);
    $group2->method('getUsers')->willReturn(array('userOk2', 'userToDelete2'));
    $group2->expects($this->once())->method('deleteUsers')->with($this->equalTo(array(1 => 'userToDelete2')));

    $groups = array($group1, $group2);

    // // Create a mysql mock to return the list of users
    // // This mock should also be called in order to delete old data in mysql
    $mysql = $this->createMock(MysqlConnector::class);
    $mysql->method('getOrderedListOfLastRegistrations')->willReturn(array('userOk1', 'userOk2'));
    $mysql->expects($this->once())->method('getOrderedListOfLastRegistrations')->with($this->equalTo(new DateTime("2019-01-01T00:00:00", new DateTimeZone("Europe/Paris"))));
    $mysql->expects($this->once())->method('deleteRegistrationsOlderThan')->with($this->equalTo(new DateTime("2018-01-01T00:00:00", new DateTimeZone("Europe/Paris"))));

    // Perform the test with dates such that we're suppose to perform deletions
    $sut = new OutdatedMemberDeleter(new DateTime("2019-02-02Z"), $groups);
    $sut->deleteOutdatedMembersIfNeeded(new DateTime("2019-01-30Z"), $mysql);

    // No assertions since expectations are already set on the mocks
  }

  public function test_dontDeleteAnyOneIfWeReNotAtATimeWhenWeShouldDeleteUsers(){
    // Setup
    // // Create a group
    $group = $this->createMock(GroupWithDeletableUsers::class);
    $group->expects($this->never())->method('getUsers');
    $group->expects($this->never())->method('deleteUsers');
    $groups = array($group);

    // // Create a mysql mock
    $mysql = $this->createMock(MysqlConnector::class);
    $mysql->expects($this->never())->method('getOrderedListOfLastRegistrations');
    $mysql->expects($this->never())->method('deleteRegistrationsOlderThan');

    // Perform the test with dates such that we're not suppose to try any deletion
    $sut = new OutdatedMemberDeleter(new DateTime("2019-08-02Z"), $groups);
    $sut->deleteOutdatedMembersIfNeeded(new DateTime("2019-07-30Z"), $mysql);

    // No assertions since expectations are already set on the mocks
  }
}
