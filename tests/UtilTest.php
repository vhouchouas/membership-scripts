<?php
declare(strict_types=1);
if (!defined('ZWP_TOOLS')){
  define('ZWP_TOOLS', __DIR__ . '/../files/');
}
require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'lib/mysql.php');

use PHPUnit\Framework\TestCase;

final class UtilTest extends TestCase {
  public function test_sendWeeklyNotification_usesCorrectWorkflow(){
    // Setup
    $mysql = $this->createMock(MysqlConnector::class);
    $emailSender = $this->createMock(EmailSender::class);
    $mysql->expects($this->once())->method('getRegistrationsForWhichNoNotificationHasBeenSentToAdmins')
      ->willReturn(array(1 => $this->buildRegistrationEvent(666)));
    $emailSender->expects($this->once())->method('sendEmailNotificationForAdminsAboutNewcomers')
      ->with(array(1 => $this->buildRegistrationEvent(666)));
    $mysql->expects($this->once())->method('updateRegistrationsForWhichNotificationHasBeenSentoToAdmins')
      ->with(array(1 => $this->buildRegistrationEvent(666)));

    // Perform the test with dates such that we're supposed to send the notification
    $lastSuccessfulRun = new DateTime("2020-03-01");
    $now = new DateTime("2020-04-01");
    sendEmailNotificationForAdminsAboutNewcomersIfneeded($emailSender, $mysql, $lastSuccessfulRun, $now);

    // No assertions since expectations are already set on the mocks
  }

  public function test_dontSendWeeklyNotificationIfItsNotTheTime(){
    // Setup
    $emailSender = $this->createMock(EmailSender::class);
    $mysql = $this->createMock(MysqlConnector::class);
    $mysql->expects($this->never())->method('getRegistrationsForWhichNoNotificationHasBeenSentToAdmins');
    $emailSender->expects($this->never())->method('sendEmailNotificationForAdminsAboutNewcomers');
    $mysql->expects($this->never())->method('updateRegistrationsForWhichNotificationHasBeenSentoToAdmins');

    // Perform the test with dates such that we're NOT supposed to send the notification
    $lastSuccessfulRun = new DateTime("2020-03-01");
    $now = new DateTime("2020-03-01");
    sendEmailNotificationForAdminsAboutNewcomersIfneeded($emailSender, $mysql, $lastSuccessfulRun, $now);

    // No assertions since expectations are already set on the mocks
  }

  public function test_keepOnlyActualRegistrations(){
    // Setup
    $actualRegistration1 = $this->buildRegistrationEvent(1, "pouet@gmail.com");
    $testRegistration2   = $this->buildRegistrationEvent(2, "guillaume.turri+test20210214@gmail.com");
    $actualRegistration3 = $this->buildRegistrationEvent(3, "donald@yahoo.com");
    $registrations = array($actualRegistration1,  $testRegistration2, $actualRegistration3);

    // Perform the test
    $filteredRegistrations = keepOnlyActualRegistrations($registrations);

    // Assertion
    $this->assertEquals(2, count($filteredRegistrations));
    $this->assertContains($actualRegistration1, $filteredRegistrations);
    $this->assertNotContains($testRegistration2, $filteredRegistrations);
    $this->assertContains($actualRegistration3, $filteredRegistrations);

  }

  private function buildRegistrationEvent($helloassoId, $email="toto@toto.fr"){
    $result = new RegistrationEvent();
    $result->helloasso_event_id = $helloassoId;
    $result->email = $email;
    // the other fields don't matters for the tests
    return $result;
  }
}
