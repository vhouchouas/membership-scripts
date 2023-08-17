<?php
declare(strict_types=1);

use App\Models\RegistrationEvent;

trait TestHelperTrait {

	private $lastHelloAssoEventId = 0;

	private function buildHelloassoEvent(string $event_date, string $first_name, string $last_name, string $email): RegistrationEvent {
		$ret = new RegistrationEvent();
		$ret->event_date = $event_date;
		$ret->first_name = $first_name;
		$ret->last_name = $last_name;
		$ret->email = $email;
		$ret->postal_code = "75000";
		$ret->city = "Paris";
		$ret->how_did_you_know_zwp = "";
		$ret->want_to_do = "";
		$ret->is_zw_professional = "Non";

		$ret->helloasso_event_id = (string) $this->lastHelloAssoEventId;
		$this->lastHelloAssoEventId++;

		return $ret;
	}
}
