<?php

if(!defined('ZWP_TOOLS')){  die(); }
register_shutdown_function( "fatal_handler" );
require_once ZWP_TOOLS . 'lib/logging.php';

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
  public $postal_code;

  public function __construct($first_name, $last_name, $email, $postal_code, $event_date){
    $this->first_name = $first_name;
    $this->last_name = $last_name;
    $this->email = $email;
    $this->event_date = $event_date;
    $this->postal_code = $postal_code;
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
