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
define('ZWP_TOOLS', dirname(__FILE__).'/');

// It turns out we can miss some helloasso registrations
// (It seems that it's when our script runs only a few seconds after the registration occurs. I guess it takes
// some more time before the event is available).
// This endpoint makes it possible to monitore those issues

require_once(ZWP_TOOLS . 'lib/logging.php');

require_once(ZWP_TOOLS . 'lib/helloasso.php');
require_once(ZWP_TOOLS . 'lib/mysql.php');
require_once(ZWP_TOOLS . 'lib/registrationDateUtil.php');
require_once(ZWP_TOOLS . 'lib/util.php');

$dateUtil = new RegistrationDateUtil(new DateTime());
$from = isset($_REQUEST["from"]) ? new DateTime($_REQUEST["from"]) : $dateUtil->getDateAfterWhichMembershipIsConsideredValid();
$to   = isset($_REQUEST["to"])   ? new DateTime($_REQUEST["to"])   : new DateTime();
$loggerInstance->log_info("from=" . dateToStr($from) . " -> to=" . dateToStr($to));

// Retrieve subscriptions from HelloAsso
$helloAssoConnector = new HelloAssoConnector();
$subscriptions = $helloAssoConnector->getAllHelloAssoSubscriptions($from, $to);

// Find out the ones absent from mysql
$mysqlConnector = new MysqlConnector();
$missingSubscriptions = array();
foreach($subscriptions as $subscription){
  if (!$mysqlConnector->existsRegistrationWithId($subscription->helloasso_event_id)){
    $missingSubscriptions[] = $subscription;
  }
}

$loggerInstance->log_info("missing " . count($missingSubscriptions) . " subscriptions");
foreach($missingSubscriptions as $s){
  $loggerInstance->log_info("- [" . $s->event_date . "]: ". $s->helloasso_event_id . " (" . $s->email . ")");
}
