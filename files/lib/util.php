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
register_shutdown_function( "fatal_handler" );
require_once ZWP_TOOLS . 'lib/emailSender.php';
require_once ZWP_TOOLS . 'lib/logging.php';
require_once ZWP_TOOLS . 'lib/registrationDateUtil.php';
require_once ZWP_TOOLS . 'lib/doctrine/DoctrineConnector.php';

function do_curl_query($curl, $nbMaxRetryOn500=5, $microSecondSleepBetweenRetry=3 * 1000000){
  global $loggerInstance;
  $ret = new CurlResult();
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

  $succeded = false;
  while (!$succeded){
    $ret->response = curl_exec($curl);
    if ($ret->response === false){
      $err_msg = "Failed curl query: [" . curl_errno($curl) . "]: " . curl_error($curl);
    } else {
      $ret->httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      if ($ret->httpCode === 500){
        $err_msg = "Failed curl query: got 500 from the server";
      } else {
        $succeded = true;
      }
    }
    if ($succeded){
      break;
    } else {
      $loggerInstance->log_info($err_msg . ". Still $nbMaxRetryOn500 attempts left");
      if ($nbMaxRetryOn500 > 0){
        $nbMaxRetryOn500--;
        usleep($microSecondSleepBetweenRetry);
      } else {
        $final_err_msg = $err_msg .  ". (failed all allowed attempts)";
        $loggerInstance->log_error($final_err_msg);
        die($final_err_msg);
      }
    }
  }

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
  public $is_already_member_since;
  public $how_did_you_know_zwp;
  public $want_to_do;
}

function sendEmailNotificationForAdminsAboutNewcomersIfneeded(EmailSender $sender, DoctrineConnector $doctrine, DateTime $lastSuccessfulRun, DateTime $now) {
  global $loggerInstance;
  $dateUtil = new RegistrationDateUtil($now);
  if ($dateUtil->needToSendNotificationAboutLatestRegistrations($lastSuccessfulRun)){
    $loggerInstance->log_info("Going to send weekly email about newcomers");
    $newcomers = $doctrine->getMembersForWhichNoNotificationHasBeenSentToAdmins();
    $sender->sendEmailNotificationForAdminsAboutNewcomers($newcomers);
    $doctrine->updateMembersForWhichNotificationHasBeenSentoToAdmins($newcomers);
  } else {
    $loggerInstance->log_info("Now isn't the time to send the email about newcomers");
  }
}

function keepOnlyActualMembers(array $members) : array {
  return array_filter($members, "isActualMember");
}

/**
 * @returns TRUE if the member is a real one, FALSE if it is a test one (made
            on HelloAsso to test those scripts)
 */
function isActualMember(MemberDTO|RegistrationEvent $member) : bool {
  // This is currently the only kind of test registration we do
  return strpos($member->email, "guillaume.turri+test") === FALSE;
}

interface GroupWithDeletableUsers {
  public function getUsers(): array;
  public function deleteUsers(array $emails): void;
}
