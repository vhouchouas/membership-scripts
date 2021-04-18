Scripts used by the NGO [Zero Waste Paris](https://zerowasteparis.fr/) to automate new memberships.

You probably don't want to use this code as-is because it is specific to our workflow, but you might be able to re-use part of it.

What this code does
===================

We have a registration form on [HelloAsso](https://www.helloasso.com/) on which new members can register. When those scripts run they:

* Look for new registrations on HelloAsso
* Insert them in
  * a [MailChimp](https://mailchimp.com/) mailing list (which is used in particular to send automatic tutorial emails to newcomers)
  * a Google Group (which we use for bidirectional communications)
  * a mysql database (so we don't rely only on 3rd party for data about our members)
* Once a year it deletes outdated memberships

An image is worth a thousand words, here is what is done:

                                      +------+
                                      | cron |
                                      +------+
                                         |
                                         | perform an HTTP query to launch the scripts
                                         v
    +----------+   Get new data   +---------------+
    | HelloAsso| <--------------- | Those scripts |
    +----------+                  +---------------+
                                          |
                                          | Insert data here
                                          |       +-----------+
                                          +------>| mailchimp |
                                          |       +-----------+
                                          |       +-----------+
                                          +------>|  G-Group  |
                                          |       +-----------+
                                          |       +-----------+
                                          +------>| Mysql db  |
                                                  +-----------+


Those scripts are written in PHP in order to be used from our web hosting. 

How to use this repo if you are from Zero Waste Paris
=====================================================
Setup
-----
Those steps must be done by everyone who wants to be able to release those scripts. It is tedious and error-prone, but since not a lot of people at Zero Waste Paris is using it and since it has to be done only once, we're living with it for now.

1. Clone this repo: `git clone https://github.com/Zero-Waste-Paris/membership-scripts`
1. Copy the template config file and edit them to put the correct config value
   1. `cp scripts/config.template.sh scripts/config.sh`
   1. `vim scripts/config.sh`
   1. `cp files/config.template.php files/config.php`
   1. `vim files/config.php`
1. Install the phar archive: `cd files/google && ./composer.phar install`
1. Setup the credentials for Google. See `files/google/README.md` for more information
1. Retrieve the `.htaccess` file (put it in the `files` directory). Its main purpose is to add http authentication to prevent that anyone could use those scripts

Setup of the database
---------------------

If we ever want to recreate the database, here is the schema:

    -- The table in which the script stores technical information
    CREATE TABLE `script_options` (
      `key` varchar(255) NOT NULL,
      `value` varchar(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      -- insert some data the scripts will need for bootstrapping
    INSERT INTO `script_options` (`key`, `value`) VALUES
    ('last_successful_run_date', 'O:8:\"DateTime\":3:{s:4:\"date\";s:26:\"2019-11-19 12:00:00.000000\";s:13:\"timezone_type\";i:3;s:8:\"timezone\";s:13:\"Europe/Zurich\";}');

    -- The table in which we user data about memberships   
    CREATE TABLE `registration_events` (
      `id_HelloAsso` varchar(12) NOT NULL,
      `date` datetime NOT NULL,
      `amount` int(11) NOT NULL,
      `first_name` varchar(30) NOT NULL,
      `last_name` varchar(30) NOT NULL,
      `email` varchar(100) NOT NULL,
      `phone` varchar(30) DEFAULT NULL,
      `birth_date` date DEFAULT NULL,
      `address` varchar(100) DEFAULT NULL,
      `postal_code` varchar(10) DEFAULT NULL,
      `city` varchar(30) DEFAULT NULL,
      `is_zwf_adherent` tinyint(1) DEFAULT NULL,
      `is_zw_professional` tinyint(1) NOT NULL,
      `is_mzd_volunteer` tinyint(1) NOT NULL,
      `is_already_member_since` smallint DEFAULT NULL,
      `want_to_do` varchar(1000) DEFAULT NULL,
      `how_did_you_know_zwp` varchar(1000) DEFAULT NULL,
      `notification_sent_to_admins` tinyint(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8; 
    ADD UNIQUE KEY `helloasso_index` (`id_HelloAsso`);



What the repo looks like
------------------------

* The `files` directory contains the files which will end up on the server
* The `tests` directory contains the non regression tests.
* The `scripts` directory contains the scripts to perform a release

How to adapt this code if you are from another NGO
==================================================

We've configured the registration form to get the data we need from new members. Since you won't have the same you'll have to change:

* the class `RegistrationEvent` (defined in `files/util.php`) which described the data we retrieve
* the method in `files/helloasso.php` which parses the response from helloasso and builds a `RegistrationEvent`
* the mysql schema in `files/mysql.php`
* the maichimp schema from `files/mailchimp.php`

You also probably want to adapt the workflow by editing the main entry point (which is `files/helloAssoToMailchimp.php`)

TODO
====
- enhance use of composer:
-- don't require a manual step: let the deploy script do that
-- retrieve phpunit from composer archives
-- replace some of code with 3rd parties (eg: logging (potentially with logrotate-ish),  sending email)
- use the field "since when are you a member" added on helloasso to send mails about returning members
