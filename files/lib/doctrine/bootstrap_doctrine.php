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
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

require_once ZWP_TOOLS . "vendor/autoload.php";
require_once ZWP_TOOLS . "config.php";

$config = ORMSetup::createAttributeMetadataConfiguration(
		paths: array(__DIR__),
		isDevMode: true,
		);

// configuring the database connection
$useSqliteConnection = true;
$connectionParams = $useSqliteConnection ? [
		'driver' => 'pdo_sqlite',
		'path' => __DIR__ . '/db.sqlite',
	] : [
		'driver' => 'pdo_mysql',
		'dbname' => 'DB_NAME',
		'user' => 'DB_USER',
		'password' => 'DB_PASSWORD',
		'host' => 'DB_HOST',
	];

$conn = DriverManager::getConnection($connectionParams);

// obtaining the entity manager
$entityManager = new EntityManager($conn, $config);
