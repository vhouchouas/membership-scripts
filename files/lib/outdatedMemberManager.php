<?php
/*
Copyright (C) 2020-2022  Zero Waste Paris

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

if(!defined('ZWP_TOOLS')){  die(); }
require_once ZWP_TOOLS . 'lib/registrationDateUtil.php';
require_once ZWP_TOOLS . 'lib/logging.php';
require_once ZWP_TOOLS . 'lib/util.php';
require_once ZWP_TOOLS . 'lib/doctrine/DoctrineConnector.php';

class OutdatedMemberManager {
  private $dateUtil;
  private $groups;

  public function __construct(DateTime $now, array $groupsWithDeletableUsers = array()){
    $this->dateUtil = new RegistrationDateUtil($now);
    $this->groups = $groupsWithDeletableUsers;
  }

  public function deleteOutdatedMembersIfNeeded(DateTime $lastSuccessfulRun, DoctrineConnector $doctrine) : void {
    global $loggerInstance;
    if ( ! $this->dateUtil->needToDeleteOutdatedMembers($lastSuccessfulRun) ){
      $loggerInstance->log_info("No need to delete outdated members");
      return;
    }

    $loggerInstance->log_info("We're going to delete outdated members");
    $doctrine->deleteRegistrationsOlderThan($this->dateUtil->getMaxDateBeforeWhichRegistrationsInfoShouldBeDiscarded());

    $usersToKeep = $doctrine->getOrderedListOfLastRegistrations($this->dateUtil->getDateAfterWhichMembershipIsConsideredValid());
    $mailsToKeep = array_map(function(MemberDTO $member){return $member->email;}, $usersToKeep);

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
   * @return MemberDTO[]
   */
  public function findThoseWhoHaveAlreadyBeenMembersButWhoArentRegisteredCurrently(array $events, DoctrineConnector $doctrine) : array {
    $emails = array_map(function($event){return $event->email;}, $events);
    return $doctrine->findMembersInArrayWhoDoNotRegisteredAfterGivenDate($emails, $this->dateUtil->getDateAfterWhichMembershipIsConsideredValid());
  }

  /**
   * @param RegistrationEvent[] $subscriptions
   * @param EmailSender $emailSender
   */
  function tellAdminsAboutOldMembersWhoRegisteredAgainAfterBeingOutOfDate(array $subscriptions, DoctrineConnector $doctrine, EmailSender $emailSender) : void {
  global $loggerInstance;
    $returningMembers = $this->findThoseWhoHaveAlreadyBeenMembersButWhoArentRegisteredCurrently($subscriptions, $doctrine);
    if (count($returningMembers) > 0){
      $loggerInstance->log_info("Got " . count($returningMembers) . " returning members. Going to send a notification");
      $emailSender->sendMailToWarnAboutReturningMembers($returningMembers);
    } else {
      $loggerInstance->log_info("No returning members. We don't send a notification.");
    }
  }
}
