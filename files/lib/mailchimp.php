<?php

if(!defined('ZWP_TOOLS')){  die(); }
require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'config.php');

class MailChimpConnector implements GroupWithDeletableUsers {
  private $debug;

  function __construct(bool $debug){
    $this->debug = $debug;
  }

  private function registrationEventToJsonPayload(RegistrationEvent $event){
    $merge_fields = array();
    $merge_fields["FNAME"]   = $event->first_name;
    $merge_fields["LNAME"]   = $event->last_name;
    $merge_fields["MMERGE6"] = $event->city;
    $merge_fields["MMERGE5"] = $event->postal_code;
    $merge_fields["MMERGE9"] = $event->is_zwf_adherent;
    if ( ! is_null($event->phone) ){
      $merge_fields["PHONE"] = $event->phone;
    }
    $merge_fields["MMERGE8"] = $event->is_zw_professional;

    // TODO This address field is actually not taken into account by mailchimp, and we don't know why yet
    $merge_fields["ADDRESS"] = $event->address;

    $payload = array();
    $payload["email_address"] = $event->email;
    $payload["status"]        = "subscribed";
    $payload["merge_fields"]  = $merge_fields;

    return json_encode($payload);
  }

  public function registerEvent(RegistrationEvent $event){
    global $loggerInstance;
    $payload_str = $this->registrationEventToJsonPayload($event);

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, MC_LIST_URL);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload_str);
    curl_setopt($curl, CURLOPT_USERPWD, MC_USERPWD);

    if ($this->debug){
      $loggerInstance->log_info("Debug mode: we skip mailchimp registration");
    } else {
      // Just for debug.
      // /!\ Beware: uncommenting it would leak our secret token /!\
      // echo "Going to run: curl -XPOST -d '$payload_str' --user '" . MC_USERPWD ."' '". MC_LIST_URL . "'";

      $loggerInstance->log_info("Going to register on MailChimp user " . $event->first_name . " " . $event->last_name);
      $response = do_curl_query($curl)->response;

      if ( strpos($response, "is already a list member") !== FALSE ){
        $loggerInstance->log_info("This user was already registered. Moving on");
      } else if ( strpos($response, '"status":"subscribed"') === FALSE // when a user is correctly registered we should get this
          || strpos($response, '"status":4') !== FALSE ){ // status 4 is ok when it's because member was already registered. Otherwise it's weird
        $loggerInstance->log_error("Unexpexted answer from mailchimp: got: " . $response);
      }
    }
    $loggerInstance->log_info("Done with this registration");
  }

  public function deleteUsers(array $emails): void{
    foreach($emails as $email){
      $this->deleteUser($email);
    }
  }

  public function deleteUser(string $email): void{
    global $loggerInstance;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, MC_LIST_URL . md5($email));
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($curl, CURLOPT_USERPWD, MC_USERPWD);

    if ($this->debug){
      $loggerInstance->log_info("Debug mode: skipping deleting user from mailchimp");
    } else {
      $loggerInstance->log_info("Going to archive from Mailchimp user " . $email);
      $curl_result = do_curl_query($curl);
      if ( strpos($curl_result->response, "Resource Not Found") !== FALSE ){
        $loggerInstance->log_info("Couldn't archive: this email has probably never been in the list");
      } else if ( strpos($curl_result->response, "This list member cannot be removed") !== FALSE ){
        $loggerInstance->log_info("Couldn't archive: this email was probably already deleted");
      } else if ($curl_result->httpCode === 204){
        $loggerInstance->log_info("The user has been successful archived");
      } else {
        $loggerInstance->log_error("Unexpected return when trying to delete $email from mailchimp: http code: " . $curl_result->httpCode . ", response: " . $curl_result->response);
        die();
      }
    }
  }

  public function getUsers(): array {
    $users = array();
    $nb_items_to_retrieve = 1; // whatever, it will be initialized after the 1st query. Just make sure it's greater than 0
    $page = 0;
    while(count($users) < $nb_items_to_retrieve){
      $response = $this->getPageOfUsers($page);
      $page += 1;
      $nb_items_to_retrieve = $response->total_items;
      foreach($response->members as $member){
        $users[] = $member->email_address;
      }
    }
    return $users;
  }

  private function getPageOfUsers($page){
    global $loggerInstance;
    $curl = curl_init();
    $result_per_page = 500;
    curl_setopt($curl, CURLOPT_URL, MC_LIST_URL . '?offset=' . $result_per_page*$page . "&count=$result_per_page");
    curl_setopt($curl, CURLOPT_USERPWD, MC_USERPWD);

    $loggerInstance->log_info("Going to get page $page of users registered in mailchimp");
    $curl_result = do_curl_query($curl);
    if($curl_result->httpCode != 200){
      $loggerInstance->log_error("Failed to get members from mailchimp: httpcode: " . $curl_result->httpCode . ", response: " . $curl_result->response);
      die();
    }
    return json_decode($curl_result->response);
  }
}
