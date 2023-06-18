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

$debug = !isset($_REQUEST["debug"]) || $_REQUEST["debug"] !== "false";

require_once(ZWP_TOOLS . 'lib/logging.php');
require_once(ZWP_TOOLS . 'config.php');
$loggerInstance = new ProdLogger($debug, SLACK_LOG_BOT_TOKEN, SLACK_CHANNEL_ID);

require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'lib/helloasso.php');
require_once(ZWP_TOOLS . 'lib/mailchimp.php');
require_once(ZWP_TOOLS . 'lib/mysql.php');
require_once(ZWP_TOOLS . 'lib/doctrine/DoctrineConnector.php');
require_once(ZWP_TOOLS . 'lib/outdatedMemberManager.php');
require_once(ZWP_TOOLS . 'google/GoogleGroupConnector.php');
require_once ZWP_TOOLS . 'lib/emailSender.php';

$loggerInstance->log_info("*** Starting run ***");
$loggerInstance->log_info("Run triggered by " . getRunRequester());

// derive dates to use
$now            = new DateTime();
$mysqlConnector = new MysqlConnector($debug);
$lastSuccessfulRunDate = $mysqlConnector->readLastSuccessfulRunStartDate();
$dateBeforeWhichAllRegistrationsHaveBeenHandled = RegistrationDateUtil::getDateBeforeWhichAllRegistrationsHaveBeenHandled($lastSuccessfulRunDate);
$loggerInstance->log_info("Last successful run was at " . dateToStr($lastSuccessfulRunDate) . ". Starting now at " . dateToStr($now) . ". We handle registrations that occur after " . dateToStr($dateBeforeWhichAllRegistrationsHaveBeenHandled));

// retrieve data from HelloAsso
$helloAssoConnector = new HelloAssoConnector();
$subscriptions = $helloAssoConnector->getAllHelloAssoSubscriptions($dateBeforeWhichAllRegistrationsHaveBeenHandled, $now);
$loggerInstance->log_info("retrieved data from HelloAsso. Got " . count($subscriptions) . " action(s)");

// Look for old members who weren't registered anymore, and tell admins so that can re-enable their Slack account.
// This has to be done before we actually register members, otherwise we'll consider they are still registered
$mailchimpConnector = new MailChimpConnector($debug);
$googleGroupConnector = new GoogleGroupConnector($debug);
$doctrineConnector = new DoctrineConnector($debug);
$emailSender = new EmailSender($debug);
$outdatedManager = new OutdatedMemberManager($now, array($mailchimpConnector, $googleGroupConnector));
$outdatedManager->tellAdminsAboutOldMembersWhoRegisteredAgainAfterBeingOutOfDate($subscriptions, $doctrineConnector, $emailSender);

// Register new members
foreach($subscriptions as $subscription){
  $mysqlConnector->registerEvent($subscription);
  $mailchimpConnector->registerEvent($subscription);
  $googleGroupConnector->registerEvent($subscription);
  $doctrineConnector->addOrUpdateMember($subscription);
}

// Send weekly notification about new members if needed
sendEmailNotificationForAdminsAboutNewcomersIfneeded($emailSender, $doctrineConnector, $lastSuccessfulRunDate, $now);

// Remove outdated members if needed
$outdatedManager->deleteOutdatedMembersIfNeeded($lastSuccessfulRunDate, $doctrineConnector);

// cleanup maintenance
$mysqlConnector->writeLastSuccessfulRunStartDate($now);
$loggerInstance->log_info("Completed successfully");
