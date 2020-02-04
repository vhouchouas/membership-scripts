<?php

if(!defined('ZWP_TOOLS')){  die(); }
require_once ZWP_TOOLS . 'lib/registrationDateUtil.php';
require_once ZWP_TOOLS . 'lib/logging.php';
require_once ZWP_TOOLS . 'lib/util.php';

class OutdatedMemberManager {
  private $dateUtil;
  private $groups;

  public function __construct(DateTime $now, array $groupsWithDeletableUsers){
    $this->dateUtil = new RegistrationDateUtil($now);
    $this->groups = $groupsWithDeletableUsers;
  }

  public function deleteOutdatedMembersIfNeeded(DateTime $lastSuccessfulRun, MysqlConnector $mysql) : void {
    global $loggerInstance;
    if ( ! $this->dateUtil->needToDeleteOutdatedMembers($lastSuccessfulRun) ){
      $loggerInstance->log_info("No need to delete outdated members");
      return;
    }

    $loggerInstance->log_info("We're going to delete outdated registration events");
    $mysql->deleteRegistrationsOlderThan($this->dateUtil->getMaxDateBeforeWhichRegistrationsInfoShouldBeDiscarded());

    $loggerInstance->log_info("We're going to delete outdated members");
    $usersToKeep = $mysql->getOrderedListOfLastRegistrations($this->dateUtil->getDateAfterWhichMembershipIsConsideredValid());
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
    return $mysql->findMembersInArrayWhoDoNotRegisteredAfterGivenDate($emails, $this->dateUtil->getDateAfterWhichMembershipIsConsideredValid());
  }

  /**
   * @param RegistrationEvent[] $subscriptions
   * @param EmailSender $emailSender
   */
  function tellAdminsAboutOldMembersWhoRegisteredAgainAfterBeingOutOfDate(array $subscriptions, MysqlConnector $mysql, EmailSender $emailSender) : void {
  global $loggerInstance;
    $returningMembers = $this->findThoseWhoHaveAlreadyBeenMembersButWhoArentRegisteredCurrently($subscriptions, $mysql);
    if (count($returningMembers) > 0){
      $loggerInstance->log_info("Got " . count($returningMembers) . " returning members. Going to send a notification");
      $emailSender->sendMailToWarnAboutReturningMembers($returningMembers);
    } else {
      $loggerInstance->log_info("No returning members. We don't send a notification.");
    }
  }
}
