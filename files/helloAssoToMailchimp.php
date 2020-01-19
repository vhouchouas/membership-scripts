<?php
define('ZWP_TOOLS', dirname(__FILE__).'/');
require_once(ZWP_TOOLS . 'logging.php');
$loggerInstance = new ProdLogger();

require_once(ZWP_TOOLS . 'util.php');
require_once(ZWP_TOOLS . 'helloasso.php');
require_once(ZWP_TOOLS . 'mailchimp.php');
require_once(ZWP_TOOLS . 'mysql.php');
require_once(ZWP_TOOLS . 'google/GoogleGroupConnector.php');

$loggerInstance->log_info("Starting run");

// derive dates to use
$now            = new DateTime();
$mysqlConnector = new MysqlConnector();
$lastSuccessfulRunDate = $mysqlConnector->readLastSuccessfulRunStartDate();
$loggerInstance->log_info("Last successful run was at " . dateToStr($lastSuccessfulRunDate) . ". Starting now at " . dateToStr($now) . ".");

// retrieve data from HelloAsso
$helloAssoConnector = new HelloAssoConnector();
$subscriptions = $helloAssoConnector->getAllHelloAssoSubscriptions($lastSuccessfulRunDate, $now);
$loggerInstance->log_info("retrieved data from HelloAsso. Got " . count($subscriptions) . " action(s)");

// Look for old members who weren't registered anymore, and tell admins so that can re-enable their Slack account.
// This has to be done before we actually register members, otherwise we'll consider they are still registered
$mailchimpConnector = new MailChimpConnector();
$googleGroupConnector = new GoogleGroupConnector();
$outdatedManager = new OutdatedMemberManager($now, array($mailchimpConnector, $googleGroupConnector));
$outdatedManager->tellAdminsAboutOldMembersWhoRegisteredAgainAfterBeingOutOfDate($subscriptions, $mysqlConnector, new EmailSender());

// Register new members
foreach($subscriptions as $subscription){
  $mysqlConnector->registerEvent($subscription);
  $mailchimpConnector->registerEvent($subscription);
  $googleGroupConnector->registerEvent($subscription);
}

// Remove outdated members if needed
$outdatedManager->deleteOutdatedMembersIfNeeded($lastSuccessfulRunDate, $mysqlConnector);

// cleanup maintenance
$mysqlConnector->writeLastSuccessfulRunStartDate($now);
$loggerInstance->log_info("Completed successfully");
