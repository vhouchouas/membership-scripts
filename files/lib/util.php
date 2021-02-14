<?php

if(!defined('ZWP_TOOLS')){  die(); }
register_shutdown_function( "fatal_handler" );
require_once ZWP_TOOLS . 'lib/emailSender.php';
require_once ZWP_TOOLS . 'lib/logging.php';
require_once ZWP_TOOLS . 'lib/registrationDateUtil.php';

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

function getRunRequester() : string {
  if (PHP_SAPI === 'cli') {
    return "CLI";
  } else if (isset($_SERVER["PHP_AUTH_USER"])){
    return "user: " . $_SERVER["PHP_AUTH_USER"];
  } else {
    return "Unknown user";
  }
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
  public $is_zw_professional;   // Beware, this is a string with value either "Oui" or "Non"
  public $is_zwf_adherent;      // Beware, this is a string with value either "Oui" or "Non"
  public $is_mzd_volunteer;     // Beware, this is a string with value either "Oui" or "Non"
  public $is_already_member_since;
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

function sendEmailNotificationForAdminsAboutNewcomersIfneeded(EmailSender $sender, MysqlConnector $mysql, DateTime $lastSuccessfulRun, DateTime $now) {
  global $loggerInstance;
  $dateUtil = new RegistrationDateUtil($now);
  if ($dateUtil->needToSendNotificationAboutLatestRegistrations($lastSuccessfulRun)){
    $loggerInstance->log_info("Going to send weekly email about newcomers");
    $newcomers = $mysql->getRegistrationsForWhichNoNotificationHasBeenSentToAdmins();
    $sender->sendEmailNotificationForAdminsAboutNewcomers($newcomers);
    $mysql->updateRegistrationsForWhichNotificationHasBeenSentoToAdmins($newcomers);
  } else {
    $loggerInstance->log_info("Now isn't the time to send the email about newcomers");
  }
}

function keepOnlyActualRegistrations(array $registrations) : array {
  return array_filter($registrations, "isActualRegistrationEvent");
}

/**
 * @returns TRUE if the registration is a real one, FALSE if it is a test one (made
            on HelloAsso to test those scripts)
 */
function isActualRegistrationEvent($registrationEvent) : bool {
  // This is currently the only kind of test registration we do
  return strpos($registrationEvent->email, "guillaume.turri+test") === FALSE;
}

interface GroupWithDeletableUsers {
  public function getUsers(): array;
  public function deleteUsers(array $emails): void;
}
