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

This repo also contains as a [git submodule](https://git-scm.com/book/en/v2/Git-Tools-Submodules) the [slack-agenda-app](https://github.com/Zero-Waste-Paris/slack-agenda-app/)

How to use this repo if you are from Zero Waste Paris
=====================================================
Setup
-----
Those steps must be done by everyone who wants to be able to release those scripts. It is tedious and error-prone, but since not a lot of people at Zero Waste Paris is using it and since it has to be done only once, we're living with it for now.

1. Clone this repo: `git clone https://github.com/Zero-Waste-Paris/membership-scripts`
1. Copy the template config file and edit them to put the correct config value
   1. `cp scripts/prod-config/config.template.sh scripts/prod-config/config.sh`
   1. `vim scripts/prod-config/config.sh`
   1. `cp scripts/prod-config/config.template.php scripts/prod-config/config.php`
   1. `vim scripts/prod-config/config.php`
1. Setup the credentials for Google. See `files/google/README.md` for more information
1. Retrieve the `.htaccess` file (put it in the `files` directory). Its main purpose is to add http authentication to prevent that anyone could use those scripts

Then, to release, run:

    ./scripts/release.sh --env prod

Nb: you can use `scripts/preprod-config/` to set up a preprod environment, and then deploy to it with

    ./scripts/release.sh --env preprod

Setup of the database
---------------------

If we ever want to recreate the database, here is the schema:

and also run this to create the table which stores the members

    ./scripts/doctrine/ orm:schema-tool:create

and then insert some data the scripts will need for bootstrapping:

    INSERT INTO `options` (`key`, `value`) VALUES
    ('last_successful_run_date', 'O:8:"DateTime":3:{s:4:"date";s:26:"2023-06-19 21:45:07.675446";s:13:"timezone_type";i:3;s:8:"timezone";s:13:"Europe/Zurich";} ');


What the repo looks like
------------------------

* The `files` directory contains the files which will end up on the server
* The `tests` directory contains the non regression tests.
* The `scripts` directory contains the scripts to perform a release
* The `slack-agenda-app` directory is a git submodule. See its [README](https://github.com/Zero-Waste-Paris/slack-agenda-app/blob/main/README.md) for more info

How to update the slack-agenda-app
==================================

From the root of this repo:

    pushd slack-agenda-app
    git pull
    popd
    git add slack-agenda-app # no trailing '/' !
    git commit -m "Update slack-agenda-app"

