<?php
if(!defined('ZWP_TOOLS')){  die(); }

//Copy this file to config.php and remplace the values

//Conf for helloasso
define("HA_CLIENT_ID", "TO_FILL");
define("HA_CLIENT_SECRET", "TO_FILL");
define("HA_REGISTRATION_FORM_SLUG", "TO_FILL");
define("HA_REGISTRATION_FORM_SLUG_2", "TO_FILL"); // Optional, set it to null if you have a single registration form
define("HA_ORGANIZATION_SLUG", "TO_FILL");

//Conf for mailchimp
define("MC_LIST_URL", "TO_FILL");
define("MC_USERPWD", "TO_FILL");

//Conf for mysql
define("DB_NAME", 'TO_FILL');
define("DB_USER", 'TO_FILL');
define("DB_PASSWORD", 'TO_FILL');
define("DB_HOST", 'TO_FILL');

//Conf for google
define("G_GROUP_NAME",  "TO_FILL");

//Generic conf for emails
define("FROM", "webmaster@yourdomain.com");

//Conf to tell about returning members
define("ADMIN_EMAIL_FOR_RETURNING_MEMBERS", "TO_FILL");
define("EMAIL_SUBJECT_FOR_RETURNING_MEMBERS", "TO_FILL");
define("EMAIL_BODY_INTRODUCTION_FOR_RETURNING_MEMBERS", "TO_FILL");

//Conf to tell about all new registrations
define("ADMIN_EMAIL_FOR_ALL_NEW_MEMBERS", "TO_FILL");
define("EMAIL_SUBJECT_FOR_ALL_NEW_MEMBERS", "TO_FILL");

//To who mails for error logs should be sent
define("ADMIN_EMAIL_FOR_ERRORS", "TO_FILL");
