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
				$error = "Can't get fresh Google tokens";
				$this->logger->error($error);
				throw new \Exception($error);
			}

			if (!file_exists(dirname($tokenFile))) {
				mkdir(dirname($tokenFile), 0700, true);
			}
			file_put_contents($tokenFile, json_encode($client->getAccessToken()));
		}
		return $client;
	}
}
