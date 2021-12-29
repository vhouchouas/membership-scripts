<?php
declare(strict_types=1);
if (!defined('ZWP_TOOLS')){
  define('ZWP_TOOLS', __DIR__ . '/../temp-src-copy/');
}
require_once(ZWP_TOOLS . 'lib/outdatedMemberManager.php');
require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'lib/mysql.php');

use PHPUnit\Framework\TestCase;

final class OutdatedMemberManagerTest extends TestCase {
  public function test_deleteExpectedUsers(){
    // Setup
    // // Create groups with users to delete
    $group1 = $this->createMock(GroupWithDeletableUsers::class);
    $group1->method('getUsers')->willReturn(array('userOk1', 'userToDelete1'));
    $group1->expects($this->once())->method('deleteUsers')->with($this->equalTo(array(1 => 'userToDelete1')));

    $group2 = $this->createMock(GroupWithDeletableUsers::class);
    $group2->method('getUsers')->willReturn(array('userOk2', 'userToDelete2'));
    $group2->expects($this->once())->method('deleteUsers')->with($this->equalTo(array(1 => 'userToDelete2')));

    $groups = array($group1, $group2);

    // // Create a mysql mock to return the list of users
    // // This mock should also be called in order to delete old data in mysql
    $mysql = $this->createMock(MysqlConnector::class);
    $mysql->method('getOrderedListOfLastRegistrations')->willReturn(array(
          $this->buildRegistrationEventWithEmail('userOk1'),
          $this->buildRegistrationEventWithEmail('userOk2')
        )
    );
    $mysql->expects($this->once())->method('getOrderedListOfLastRegistrations')->with($this->equalTo(new DateTime("2019-01-01T00:00:00", new DateTimeZone("Europe/Paris"))));
    $mysql->expects($this->once())->method('deleteRegistrationsOlderThan')->with($this->equalTo(new DateTime("2018-01-01T00:00:00", new DateTimeZone("Europe/Paris"))));

    // Perform the test with dates such that we're suppose to perform deletions
    $sut = new OutdatedMemberManager(new DateTime("2019-02-02Z"), $groups);
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
    $sut = new OutdatedMemberManager(new DateTime("2019-08-02Z"), $groups);
    $sut->deleteOutdatedMembersIfNeeded(new DateTime("2019-07-30Z"), $mysql);

    // No assertions since expectations are already set on the mocks
  }

  public function test_sendMailIfOldMembersRegisteredAgain(){
    // Setup
    $now = new DateTime("2020-06-01");
    // // Create test data. Start with the data we'll use as input for the test
    $inputData = [
      $this->buildRegistrationEventWithEmail("toto@toto.com"),
      $this->buildRegistrationEventWithEmail("titi@titi.com"),
      $this->buildRegistrationEventWithEmail("tutu@tutu.com")
    ];

    // // Create test data which will be returned by the mysql mock. Let's consider it returns only two emails
    $mysql = $this->createMock(MysqlConnector::class);
    $mysql->expects($this->once())->method('findMembersInArrayWhoDoNotRegisteredAfterGivenDate')->with($this->equalTo(array("toto@toto.com", "titi@titi.com", "tutu@tutu.com")), $this->equalTo(new dateTime("2020-01-01", new DateTimeZone("Europe/Paris"))))->willReturn(array("toto@toto.com", "titi@titi.com"));

    // // Setup the EmailSender mock
    $emailSender = $this->createMock(EmailSender::class);
    $emailSender->expects($this->once())->method('sendMailToWarnAboutReturningMembers')->with(array("toto@toto.com", "titi@titi.com"));

    //Act
    $unusedArrayOfConnectors = array();
    $sut = new OutdatedMemberManager($now, $unusedArrayOfConnectors);
    $sut->tellAdminsAboutOldMembersWhoRegisteredAgainAfterBeingOutOfDate($inputData, $mysql, $emailSender);

    // No need to assert because we already set up 'expects'

  }

  private function buildRegistrationEventWithEmail(string $email) : RegistrationEvent {
    $event = new RegistrationEvent();
    $event->email = $email;
    return $event;
  }
}
