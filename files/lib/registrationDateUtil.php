<?php

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
}

