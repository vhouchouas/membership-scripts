<?php

namespace ZWP\Members\Services;

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
}
