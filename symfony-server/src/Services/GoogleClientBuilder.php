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

		return $client;
	}
}
