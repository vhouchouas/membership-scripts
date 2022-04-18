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
require_once(ZWP_TOOLS . 'config.php');

$ch = curl_init("https://slack.com/api/users.list?include_locale=0&pretty=0");
$headers = array();
$headers[] = 'Authorization: Bearer ' . SLACK_BOT_TOKEN;
curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
$response = curl_exec($ch);
curl_close($ch);
echo $response;
?>