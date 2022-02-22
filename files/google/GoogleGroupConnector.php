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
require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'config.php');

require_once 'vendor/autoload.php';
include "google_client.php";

class GoogleGroupConnector implements GroupWithDeletableUsers {
    var $service;
    private $debug;

    function __construct(bool $debug)
    {
        $client = getClient();
        $this->service = new Google_Service_Directory($client);
        $this->debug = $debug;
    }

    function registerEvent(RegistrationEvent $event){
        global $loggerInstance;
        if ($event->email === "" || $event->email === NULL){
            // Something is probably wrong with the registration form (already seen when a form is badly configured).
            // this ensures we don't block all upcoming registrations.
            $loggerInstance->log_error("No email for " . print_r($event, true));
        } else {
            $this->registerEmailToGroup($event->email);
        }
    }

    function registerEmailToGroup($email){
        global $loggerInstance;
        $loggerInstance->log_info("Going to register in Google group " . G_GROUP_NAME . " the email " . $email);
        $member = new Google_Service_Directory_Member();
        $member->setEmail($email);
        $member->setRole("MEMBER");
        if ($this->debug) {
          $loggerInstance->log_info("Debug mode: skipping Google registration");
        } else {
          try {
            $this->service->members->insert(G_GROUP_NAME, $member);
            $loggerInstance->log_info("Done with this registration in the Google group");
          } catch(Google_Service_Exception $e){
            $reason = $e->getErrors()[0]["reason"];
            if($reason === "duplicate"){
              $loggerInstance->log_info("This member already exists");
            } else if ($reason === "notFound"){
              $loggerInstance->log_error("Error 'not found'. Perhaps the email adress $email is invalid?");
            } else if ($reason === "invalid") {
              $loggerInstance->log_error("Error 'invalid input': email $email seems invalid");
            } else {
              $loggerInstance->log_error("Unknown error for email $email:" . $e);
              die();
            }
          }
        }
    }

    public function deleteUsers(array $emails): void{
      foreach($emails as $email){
        $this->deleteUser($email);
      }
    }

    function deleteUser(string $email): void{
        global $loggerInstance;
        $loggerInstance->log_info("Going to delete from " . G_GROUP_NAME . " the email " . $email);
        if ($this->debug) {
          $loggerInstance->log_info("Debug mode: skipping deletion from Google");
        } else {
          try {
            $this->service->members->delete(G_GROUP_NAME, $email);
            $loggerInstance->log_info("Done with this deletion");
          } catch(Google_Service_Exception $e){
            if($e->getErrors()[0]["message"] === "Resource Not Found: memberKey"){
              $loggerInstance->log_info("This email wasn't in the group already");
            } else {
              $loggerInstance->log_error("Unknown error for email $email: " . $e);
              die();
            }
          }
        }
    }

    function getUsers(): array {
      global $loggerInstance;
      $users = array();
      $didAtLeastOneQuery = false;
      $nextPageToken = NULL;

      while(!$didAtLeastOneQuery || !is_null($nextPageToken)){
        $optParams = $this->buildOptParamsForGetUsers($nextPageToken);
        try {
          $loggerInstance->log_info("Going to get a page of users from google group. Page token: $nextPageToken");
          $result = $this->service->members->listMembers(G_GROUP_NAME, array('pageToken' => $nextPageToken));
        } catch(Exception $e){
          $loggerInstance->log_error("Unknown error: " . $e);
          die();
        }

        $users = array_merge($users, $this->membersToArrayOfEmails($result->members));
        $nextPageToken = $result->nextPageToken;
        $didAtLeastOneQuery = true;
      }

      return $users;
    }

    private function buildOptParamsForGetUsers($nextPageToken){
        $optParams = array();
        if ( !is_null($nextPageToken) ){
          $optParams["pageToken"] = $nextPageToken;
        }
        return $optParams;
    }

    private function membersToArrayOfEmails($members){
      $emails = array();
      foreach($members as $member){
        $emails[] = $member->email;
      }
      return $emails;
    }
}
