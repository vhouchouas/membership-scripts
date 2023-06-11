<?php
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/../../config.php";

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
