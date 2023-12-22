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
                                          +------>|  sql db   |
                                                  +-----------+


Those scripts are written in PHP in order to be used from our web hosting.

This repo also contains as a [git submodule](https://git-scm.com/book/en/v2/Git-Tools-Submodules) the [slack-agenda-app](https://github.com/Zero-Waste-Paris/slack-agenda-app/)

How to run locally
=================

Prerequisite:

- java 11 (or later)
- curl
- composer (see https://getcomposer.org/ )
- symfony CLI (see https://symfony.com/download )
- npm
- ng (the angular CLI; see https://angular.io/guide/setup-local or run `npm install -g @angular/cli`)

After cloning this repo and from the top of it:

    # Generate code from OpenApi specification
    ./scripts/generate.sh
    
    # Setup local conf
    cd symfony-server
		cp .env .env.local
		vim .env.local # update the values you want to override
    # to have a complete setup you also need some google tokens, but for local debug run we can skip it

    # install the PHP packages and run the tests
    composer install
    ../scripts/runSymfonyTests.sh # This setups a test local sqlite db and runs the tests against it

    # Setup the database
    ## If you want to create a brand new database, run this.
    ## (what it does exactly depends on your local configuration. By default it creates a sqlite db (in var/data.db)
    php bin/console doctrine:database:create

    ## if you want to use an existing database, then just create the tables with this instead:
    # php bin/console doctrine:schema:create

    ## Initialize a value for the 'last succesful run date'
    php bin/console doctrine:database:initialize-last-successful-run-date 2022-01-01

    ## For debug runs we may also insert some test data
    ## /!\ Do not do this in prod obviously /!\
		php bin/console member:add someone@mail.fr
		php bin/console member:add someonelse@mail.com
		
    ## And now let's create your user account (to access the API)
		php bin/console user:add me@mail.eu my_password

    ## Launch the local server
    symfony server:start --no-tls # add --port=value to listen on a specific port

We now have a server which listens by default on port 8000.
For it to be useful we now need to setup the UI

    cd ../angular-front/
		npm install
		ng build -c development # For a prod build just run `ng build`
		cp -r dist/angular-front/* ../symfony-server/public

That's it, now you can open http://localhost:8000 in your browser, and log in with the credentials created previously (if you followed those instructions, the login is `me@mail.fr` and the password is `my_password`).

How to deploy in prod
=====================

Prerequisites:

- rsync 

from the root of the repo:

    cd scripts/prod-config

		cp deploysymfony-config.template.sh  deploysymfony-config.sh 
		vim deploysymfony-config.sh  # put the rsync destination where to deploy. Nb: it is the `public` directory which should be the root of your web server

		cp symfony.template.conf symfony.conf
		vim symfony.conf # same kind of content as .env.local (nb: change at least the `APP_SECRET` to ay random string)

		cp slack-config/config.json.sample slack-config/config.json
		vim slack-config/config.json # conf for the agenda submodule

    # you may also put in "prod-config" a favicon.webp
    # When all is done, run `./release.sh --env prod`
    # Nb: you may put some conf in `scripts/preprod-config`, and then run `./release.sh` to take those confs into account


What the repo looks like
========================

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

