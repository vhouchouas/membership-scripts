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

  private static $endl = "\r\n";

  /**
   * @param SimplifiedRegistrationEvent[] $returningMembers
   */
  function sendMailToWarnAboutReturningMembers(array $returningMembers) : void {
    if (count($returningMembers) == 0){
      // Throw instead of returning because if we expect the check to be done by the client,
      // it makes it easier to check in a unit test that the check has been performed.
      throw new Exception("Called sendMailToWarnAboutReturningMembers with an empty array");
    }
    mail(ADMIN_EMAIL_FOR_RETURNING_MEMBERS, EMAIL_SUBJECT_FOR_RETURNING_MEMBERS, $this->buildReturningMembersEmailBody($returningMembers));
  }

  function buildReturningMembersEmailBody(array $returningMembers) {
    $body = EMAIL_BODY_INTRODUCTION_FOR_RETURNING_MEMBERS . self::$endl;
    $body .= "Name; Email; Last registration date" . self::$endl;
    foreach($returningMembers as $returningMember) {
      $body .= $returningMember->first_name . " " . $returningMember->last_name . "; "
        . $returningMember->email . "; "
        . $returningMember->event_date
        . self::$endl;
    }
    return $body;
  }

  function sendEmailNotificationForAdminsAboutNewcomers(array $newMembers) {
    $body = "";
    if (empty($newMembers)) {
      $body = "Oh non, il n'y a pas eu de nouveaux membres cette semaine ! :(";
    } else {
      $body = "Voici les " . count($newMembers) . " membres qui ont rejoint l'asso cette semaine." . self::$endl;
      $body .= "(Attention : ce mail contient des données personnelles, ne le transférez pas, et pensez à le supprimer à terme.) " . self::$endl;
      foreach($newMembers as $newMember) {
        $body .= self::$endl;
        $body .= $newMember->first_name . " " . $newMember->last_name . " (" . $newMember->email . ")" . self::$endl;
        $body .= "Adhésion le " . $newMember->event_date . self::$endl;
        $body .= "Réside à : " . $newMember->city . " (" . $newMember->postal_code . ")" . self::$endl;
        $body .= "A connu l'asso : " . $newMember->how_did_you_know_zwp . self::$endl;
        $body .= "Il/Elle est motivé par : " . $newMember->want_to_do . self::$endl;
      }

      $body .= self::endl;
      $body .= "Il y a un projet en cours qui leur correspond ? Un GT qui recherche de nouveaux membres ? C’est le moment de leur dire et/ou d’en parler à un.e référent.e ! ";
    }

    mail(ADMIN_EMAIL_FOR_ALL_NEW_MEMBERS, EMAIL_SUBJECT_FOR_ALL_NEW_MEMBERS, $body);
  }
}

interface GroupWithDeletableUsers {
  public function getUsers(): array;
  public function deleteUsers(array $emails): void;
}
