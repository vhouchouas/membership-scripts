<?php

namespace App\Services;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;

class GoogleClientBuilder {
	public function __construct(
		private ContainerBagInterface $params,
		private LoggerInterface $logger,
	) {}

	public function getClient() {
		$client = new \Google_Client();
		$client->setApplicationName('G Suite Directory API PHP Quickstart');
		$client->setScopes(array(
			\Google_Service_Directory::ADMIN_DIRECTORY_USER_READONLY,
			\Google_Service_Directory::ADMIN_DIRECTORY_GROUP
		));

		$client->setAuthConfig(json_decode($this->params->get('google.jsonCredentials'), true));
		$client->setAccessType('offline');
		$client->setPrompt('select_account consent');

		$tokenFile = $this->params->get('google.tokenFile');
		if (file_exists($tokenFile)) {
			$accessToken = json_decode(file_get_contents($tokenFile), true);
			$client->setAccessToken($accessToken);
		}

		if ($client->isAccessTokenExpired()) {
			if ($client->getRefreshToken()) {
				$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
			} else {
				$authUrl = $client->createAuthUrl();
				$this->logger->info("Open the following link in your browser: $authUrl");
				$this->logger->info('Enter verification code: ');
				$authCode = trim(fgets(\STDIN));
	
				$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
				$client->setAccessToken($accessToken);
	
				// Check to see if there was an error.
				if (array_key_exists('error', $accessToken)) {
					throw new Exception(join(', ', $accessToken));
				}
			}

			if (!file_exists(dirname($tokenFile))) {
				mkdir(dirname($tokenFile), 0700, true);
			}
			file_put_contents($tokenFile, json_encode($client->getAccessToken()));
		}
		return $client;
	}
}
