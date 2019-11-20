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

$mailchimpConnector = new MailChimpConnector();
$googleGroupConnector = new GoogleGroupConnector();
foreach($subscriptions as $subscription){
  $mysqlConnector->registerEvent($subscription);
  $mailchimpConnector->registerEvent($subscription);
  $googleGroupConnector->registerEvent($subscription);
}

// Remove outdated members if needed
$deleter = new OutdatedMemberDeleter($now, array($mailchimpConnector, $googleGroupConnector));
$deleter->deleteOutdatedMembersIfNeeded($lastSuccessfulRunDate, $mysqlConnector);

// cleanup maintenance
$mysqlConnector->writeLastSuccessfulRunStartDate($now);
$loggerInstance->log_info("Completed successfully");
