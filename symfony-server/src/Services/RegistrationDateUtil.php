<?php

namespace App\Services;

class RegistrationDateUtil {
  private \DateTime $now;
  private \DateTime $februaryFirstThisYear;
  private \DateTimeZone $timeZone;
  private string $thisYear;

  public function __construct(\DateTime $now = new \DateTime()){
    $this->now = $now;
    $this->thisYear = $this->now->format("Y");
    $this->timeZone = new \DateTimeZone("Europe/Paris");
    $this->februaryFirstThisYear = new \DateTime($this->thisYear . "-02-01", $this->timeZone);
  }

  /**
   * When someone joins during year N, her membership is valid until 31 December of year N.
   * But we want to keep members in the mailing list only on 1st February N+1 (to let time for members
   * to re-new their membership, otherwise we would have 0 members on 1st January at midnight)
   */
  public function getDateAfterWhichMembershipIsConsideredValid() :\DateTime {
    if ( $this->now >= $this->februaryFirstThisYear ){
      return new \DateTime($this->thisYear . "-01-01", $this->timeZone);
    } else {
      return new \DateTime(($this->thisYear-1) . "-01-01", $this->timeZone);
    }
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
  public static function getDateBeforeWhichAllRegistrationsHaveBeenHandled(\DateTime $lastSuccessfulRun) : \DateTime {
    $deepCopy = clone $lastSuccessfulRun;
    return $deepCopy->sub(new \DateInterval("PT1H"));
  }
}
