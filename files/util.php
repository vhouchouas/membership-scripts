<?php

if(!defined('ZWP_TOOLS')){  die(); }
register_shutdown_function( "fatal_handler" );
require_once ZWP_TOOLS . 'logging.php';

function do_curl_query($curl){
  global $loggerInstance;
  $ret = new CurlResult();
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  $ret->response = curl_exec($curl);

  if ( $ret->response === false ){
    $err_msg = "Failed curl query: [" . curl_errno($curl) . "]: " . curl_error($curl);
    $loggerInstance->log_error($err_msg);
    die($err_msg);
  }
  $ret->httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  curl_close($curl);
  return $ret;
}

class CurlResult {
  public $response;
  public $httpCode;
}


function dateToStr(DateTime $d) : string {
  return $d->format('Y-m-d\TH:i:s');
}

function fatal_handler() {
  global $loggerInstance;
  $errfile = "unknown file";
  $errstr  = "shutdown";
  $errno   = E_CORE_ERROR;
  $errline = 0;

  $error = error_get_last();

  if( $error !== NULL) {
    $errno   = $error["type"];
    $errfile = $error["file"];
    $errline = $error["line"];
    $errstr  = $error["message"];

    $loggerInstance->log_error("A PHP fatal error occured: $errstr (type=$errno, at $errfile:$errline)");
    die();
  }
}


class RegistrationEvent {
  public $helloasso_event_id;
  public $event_date;
  public $amount;
  public $first_name;
  public $last_name;
  public $email;
  public $phone;
  public $address;
  public $postal_code;
  public $birth_date; // String with format dd/mm/yyyy
  public $city;
  public $want_to_be_volunteer; // Beware, this is a string with value either "Oui" or "Non"
  public $is_zw_professional;   // Beware, this is a string with value either "Oui" or "Non"
  public $is_zwf_adherent;      // Beware, this is a string with value either "Oui" or "Non"
  public $how_did_you_know_zwp;
  public $want_to_do;
}

class SimplifiedRegistrationEvent {
  public $first_name;
  public $last_name;
  public $event_date;
  public $email;

  public function __construct($first_name, $last_name, $email, $event_date){
    $this->first_name = $first_name;
    $this->last_name = $last_name;
    $this->email = $email;
    $this->event_date = $event_date;
  }
}

class OutdatedMemberManager {
  private $now;
  private $groups;
  private $timeZone = 1;
  private $thisYear;
  private $februaryFirstThisYear;

  public function __construct(DateTime $now, array $groupsWithDeletableUsers){
    $this->now = $now;
    $this->groups = $groupsWithDeletableUsers;
    $this->thisYear = $this->now->format("Y");
    $this->timeZone = new DateTimeZone("Europe/Paris");
    $this->februaryFirstThisYear = new DateTime($this->thisYear . "-02-01", $this->timeZone);
  }

  /**
   * When someone joins during year N, her membership is valid until 31 December of year N.
   * But we want to keep members in the mailing list only on 1st February N+1 (to let time for members
   * to re-new their membership, otherwise we would have 0 members on 1st January at midnight)
   */
  public function getDateAfterWhichMembershipIsConsideredValid() :DateTime {
    if ( $this->now >= $this->februaryFirstThisYear ){
      return new DateTime($this->thisYear . "-01-01", $this->timeZone);
    } else {
      return new DateTime(($this->thisYear-1) . "-01-01", $this->timeZone);
    }
  }

  /**
   * To be compliant with GDPR we delete data about registration which expired a year ago.
   * (The duration of "1 year" is defined on our privacy page).
   * Since registrations expire on 31st December it means we have to delete registrations
   * which occured before 1st January of the previous year.
   *
   * For instance: if someone registers on 2018-06-01, then this registration expire on 2018-12-31 so
   * this data can be kept all of 2019. But when we delete data in 2020 we have to delete it.
   * So when we call this method in 2020 it should tell us to delete registrations older than 2019-01-01
   */
  public function getMaxDateBeforeWhichRegistrationsInfoShouldBeDiscarded() :DateTime {
    return new DateTime(($this->thisYear-1) . "-01-01", $this->timeZone);
  }

  public function needToDeleteOutdatedMembers(DateTime $lastSuccessfulRun) : bool {
    return $this->now >= $this->februaryFirstThisYear && $lastSuccessfulRun < $this->februaryFirstThisYear;
  }

  public function deleteOutdatedMembersIfNeeded(DateTime $lastSuccessfulRun, MysqlConnector $mysql) : void {
    global $loggerInstance;
    if ( ! $this->needToDeleteOutdatedMembers($lastSuccessfulRun) ){
      $loggerInstance->log_info("No need to delete outdated members");
      return;
    }

    $loggerInstance->log_info("We're going to delete outdated registration events");
    $mysql->deleteRegistrationsOlderThan($this->getMaxDateBeforeWhichRegistrationsInfoShouldBeDiscarded());

    $loggerInstance->log_info("We're going to delete outdated members");
    $mailsToKeep = $mysql->getOrderedListOfLastRegistrations($this->getDateAfterWhichMembershipIsConsideredValid());

    foreach($this->groups as $group){
      $currentUsers = $group->getUsers();
      $usersToDelete = array_diff($currentUsers, $mailsToKeep);
      $group->deleteUsers($usersToDelete);
    }
  }

  /**
   * This method is supposed to be called with the emails of the last members who registered, and it
   * returns information about those of them who were members in the past and who have been deactivated.
   * Currently it's used to send a notification to admins, because the accounts of returning members need
   * to be manually reactivated on some of our tools.
   * @param RegistrationEvent[] $events The list of members
   * @return SimplifiedRegistrationEvent[]
   */
  public function findThoseWhoHaveAlreadyBeenMembersButWhoArentRegisteredCurrently(array $events, MysqlConnector $mysql) : array {
    $emails = array_map(function($event){return $event->email;}, $events);
    return $mysql->findMembersInArrayWhoDoNotRegisteredAfterGivenDate($emails, $this->getDateAfterWhichMembershipIsConsideredValid());
  }

  /**
   * @param RegistrationEvent[] $subscriptions
   * @param EmailSender $emailSender
   */
  function tellAdminsAboutOldMembersWhoRegisteredAgainAfterBeingOutOfDate(array $subscriptions, MysqlConnector $mysql, EmailSender $emailSender) : void {
  global $loggerInstance;
    $returningMembers = $this->findThoseWhoHaveAlreadyBeenMembersButWhoArentRegisteredCurrently($subscriptions, $mysql);
    if (count($returningMembers) > 0){
      $loggerInstance->log_info("Got " . count($returningMembers) . ". Going to send a notification");
      $emailSender->sendMailToWarnAboutReturningMembers($returningMembers);
    } else {
      $loggerInstance->log_info("No returning members. We don't send a notification.");
    }
  }
}

class EmailSender {
  /**
   * @param SimplifiedRegistrationEvent[] $returningMembers
   */
  function sendMailToWarnAboutReturningMembers(array $returningMembers) : void {
    if (count($returningMembers) == 0){
      // Throw instead of returning because if we expect the check to be done by the client,
      // it makes it easier to check in a unit test that the check has been performed.
      throw new Exception("Called sendMailToWarnAboutReturningMembers with an empty array");
    }
    mail(ADMIN_EMAIL, EMAIL_SUBJECT, $this->buildReturningMembersEmailBody($returningMembers));
  }

  function buildReturningMembersEmailBody(array $returningMembers) {
    $endl = "\r\n";
    $body = EMAIL_BODY_INTRODUCTION . $endl;
    $body .= "Name; Email; Last registration date" . $endl;
    foreach($returningMembers as $returningMember) {
      $body .= $returningMember->first_name . " " . $returningMember->last_name . "; "
        . $returningMember->email . "; "
        . $returningMember->event_date
        . $endl;
    }
    return $body;
  }
}

interface GroupWithDeletableUsers {
  public function getUsers(): array;
  public function deleteUsers(array $emails): void;
}
