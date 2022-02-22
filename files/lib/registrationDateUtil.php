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

class RegistrationDateUtil {
  private $now;
  private $februaryFirstThisYear;
  private $timeZone = 1;
  private $thisYear;

  public function __construct(DateTime $now){
    $this->now = $now;
    $this->thisYear = $this->now->format("Y");
    $this->timeZone = new DateTimeZone("Europe/Paris");
    $this->februaryFirstThisYear = new DateTime($this->thisYear . "-02-01", $this->timeZone);
  }

  /**
   * When someone joins during year N, her membership is valid until 31 December of year N.
   * But we want to keep members in the mailing list only on 1st February N+1 (to let time for members
   * to re-new their membership, otherwise we would have 0 members on 1st January at midnight)
   */
  public function getDateAfterWhichMembershipIsConsideredValid() :DateTime {
    if ( $this->now >= $this->februaryFirstThisYear ){
      return new DateTime($this->thisYear . "-01-01", $this->timeZone);
    } else {
      return new DateTime(($this->thisYear-1) . "-01-01", $this->timeZone);
    }
  }

  /**
   * To be compliant with GDPR we delete data about registration which expired a year ago.
   * (The duration of "1 year" is defined on our privacy page).
   * Since registrations expire on 31st December it means we have to delete registrations
   * which occured before 1st January of the previous year.
   *
   * For instance: if someone registers on 2018-06-01, then this registration expire on 2018-12-31 so
   * this data can be kept all of 2019. But when we delete data in 2020 we have to delete it.
   * So when we call this method in 2020 it should tell us to delete registrations older than 2019-01-01
   */
  public function getMaxDateBeforeWhichRegistrationsInfoShouldBeDiscarded() :DateTime {
    return new DateTime(($this->thisYear-1) . "-01-01", $this->timeZone);
  }

  public function needToDeleteOutdatedMembers(DateTime $lastSuccessfulRun) : bool {
    return $this->now >= $this->februaryFirstThisYear && $lastSuccessfulRun < $this->februaryFirstThisYear;
  }

  /**
   * We want to send a weekly mail to draw the attention of admins on the latest registrations.
   * This mail should be received on Thursday morning in order to be received and handled
   * before the week-end.
   * Since we can't be sure of the hour at which this script will run, we consider that if it's
   * later than Wednesday 18h it's ok
   */
  public function needToSendNotificationAboutLatestRegistrations(DateTime $lastSuccessfulRun) : bool {
    $deadlineHour = 18;
    if (self::isAWednesday($lastSuccessfulRun) && self::getHour($lastSuccessfulRun) < $deadlineHour) {
      // We handle this case particularly because "next wednesday" would be next week but in this case it should be today at 18:00
      $nextDeadline = $lastSuccessfulRun;
    } else  {
      $nextDeadline = new DateTime();
      $nextDeadline->setTimeZone($this->timeZone);
      $nextDeadline->setTimestamp(strtotime('next Wednesday', $lastSuccessfulRun->getTimestamp()));
    }
    $nextDeadline->setTime($deadlineHour, 0);
    return $this->now >= $nextDeadline;
  }

  /**
   * We noticed that when
   * - a registration R1 is done on Helloasso at "t0"
   * - the scripts run at "t0 + 10 seconds"
   * then the registration may not be available yet through mailchimp API (ie: the call to their API will return
   * all the registrations except R1)
   * To ensure we don't miss for good some registration, we query Helloass with a start date equal to the date
   * of the previous run minus some delay (so if R1 is missed by the run just after the registration has been performed,
   * it will be handled by a subsequent run).
   * Note that it works because the rest of the script is idempotent (ie: if a given registration is handled by several runs,
   * it doesn't matter, because we assume the rest of the code won't do double-registration anywhere (assumption which is
   * correct at the time of writing this comment)
   */
  public static function getDateBeforeWhichAllRegistrationsHaveBeenHandled(DateTime $lastSuccessfulRun) : DateTime {
    $deepCopy = clone $lastSuccessfulRun;
    return $deepCopy->sub(new DateInterval("PT1H"));
  }

  private static function isAWednesday(DateTime $date) : bool {
    return date('w', $date->getTimestamp()) === "3";
  }

  private static function getHour(DateTime $date) : int {
    return date('H', $date->getTimestamp());
  }
}

