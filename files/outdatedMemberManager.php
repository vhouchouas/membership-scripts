<?php

if(!defined('ZWP_TOOLS')){  die(); }
require_once ZWP_TOOLS . 'logging.php';
require_once ZWP_TOOLS . 'util.php';

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
    $usersToKeep = $mysql->getOrderedListOfLastRegistrations($this->getDateAfterWhichMembershipIsConsideredValid());
    $mailsToKeep = array_map(function($event){return $event->email;}, $usersToKeep);

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
