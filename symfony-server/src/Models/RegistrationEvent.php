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

namespace App\Models;

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
