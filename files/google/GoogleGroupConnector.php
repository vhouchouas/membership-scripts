<?php

if(!defined('ZWP_TOOLS')){  die(); }
require_once(ZWP_TOOLS . 'util.php');
require_once(ZWP_TOOLS . 'config.php');

require_once 'vendor/autoload.php';
include "google_client.php";

class GoogleGroupConnector implements GroupWithDeletableUsers {
    var $service;

    function __construct()
    {
        $client = getClient();
        $this->service = new Google_Service_Directory($client);
    }

    function registerEvent(RegistrationEvent $event){
        global $loggerInstance;
        if($event->want_to_be_volunteer !== "Oui"){
            $loggerInstance->log_info($event->email . " doesn't want to be a volunteer so we skip ggroup insertion");
            return;
        }
        $this->registerEmailToGroup($event->email);
    }

    function registerEmailToGroup($email){
        global $loggerInstance;
        $loggerInstance->log_info("Going to register in Google group " . G_GROUP_NAME . " the email " . $email);
        $member = new Google_Service_Directory_Member();
        $member->setEmail($email);
        $member->setRole("MEMBER");
        try {
            $this->service->members->insert(G_GROUP_NAME, $member);
            $loggerInstance->log_info("Done with this registration in the Gogle group");
        } catch(Google_Service_Exception $e){
            $reason = $e->getErrors()[0]["reason"];
            if($reason === "duplicate"){
                $loggerInstance->log_info("This member already exists");
            } else if ($reason === "notFound"){
                $loggerInstance->log_error("Error 'not found'. Perhaps the email adress $email is invalid?");
            } else {
                $loggerInstance->log_error("Unknow error: " . $e);
                die();
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
        try {
            $this->service->members->delete(G_GROUP_NAME, $email);
            $loggerInstance->log_info("Done with this deletion");
        } catch(Google_Service_Exception $e){
            if($e->getErrors()[0]["message"] === "Resource Not Found: memberKey"){
                $loggerInstance->log_info("This email wasn't in the group already");
            } else {
                $loggerInstance->log_error("Unknown error: " . $e);
                die();
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
