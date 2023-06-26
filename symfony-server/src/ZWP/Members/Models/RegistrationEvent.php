<?php

namespace ZWP\Members\Models;

class RegistrationEvent {
  public string $helloasso_event_id;
  public string $event_date;
  public string $first_name;
  public string $last_name;
  public string $email;
  public string $phone;
  public string $postal_code;
  public string $city;
  public string $is_zw_professional;   // Beware, this is a string with value either "Oui" or "Non"
  public string $how_did_you_know_zwp;
  public string $want_to_do;
}
