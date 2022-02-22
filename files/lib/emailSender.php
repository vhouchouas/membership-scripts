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

require_once ZWP_TOOLS . 'lib/logging.php';
require_once ZWP_TOOLS . 'config.php';

class EmailSender {

  private static $endl = "\r\n";
  private $debug;

  function __construct(bool $debug){
    $this->debug = $debug;
  }

  /**
   * @param SimplifiedRegistrationEvent[] $returningMembers
   */
  function sendMailToWarnAboutReturningMembers(array $returningMembers) : void {
    if (count($returningMembers) == 0){
      // Throw instead of returning because if we expect the check to be done by the client,
      // it makes it easier to check in a unit test that the check has been performed.
      throw new Exception("Called sendMailToWarnAboutReturningMembers with an empty array");
    }
    $this->sendMail(ADMIN_EMAIL_FOR_RETURNING_MEMBERS, EMAIL_SUBJECT_FOR_RETURNING_MEMBERS, $this->buildReturningMembersEmailBody($returningMembers));
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
        $body .= "Iel est motivé par : " . $newMember->want_to_do . self::$endl;
      }

      $body .= self::$endl;
      $body .= "Il y a un projet en cours qui leur correspond ? Un GT qui recherche de nouveaux membres ? C’est le moment de leur dire et/ou d’en parler à un.e référent.e ! ";
    }

    $this->sendMail(ADMIN_EMAIL_FOR_ALL_NEW_MEMBERS, EMAIL_SUBJECT_FOR_ALL_NEW_MEMBERS, $body);
  }

  private function sendMail($to, $subject, $message) {
    global $loggerInstance;
    $headers = 'From: ' . FROM . "\r\n" .
      "Content-Type: text/plain; charset=UTF-8 \r\n" .
      "MIME-Version: 1.0 \r\n" .
      "Content-Transfer-Encoding: base64";
    $subject = "=?UTF-8?B?".base64_encode($subject)."?=";
    if($this->debug){
      $loggerInstance->log_info("debug mode: we don't send an email");
    } else {
      mail($to, $subject, base64_encode($message), $headers);
    }
  }
}
