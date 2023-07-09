<?php

namespace App\Services;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;

class GoogleGroupService {
	public function __construct(
		private LoggerInterface $logger,
		private ContainerBagInterface $params,
		GoogleClientBuilder $clientBuilder,
	) {
		$this->service = new \Google_Service_Directory($clientBuilder->getClient());
	}

	function getUsers(): array {
		$users = array();
		$didAtLeastOneQuery = false;
		$nextPageToken = NULL;

		while(!$didAtLeastOneQuery || !is_null($nextPageToken)){
			try {
				$this->logger->info("Going to get a page of users from google group. Page token: $nextPageToken");
				$result = $this->service->members->listMembers($this->params->get('google.groupName'), array('pageToken' => $nextPageToken));
			} catch(Exception $e){
				$error = "Unknown error: " . $e;
				$this->logger->error($error);
				throw new Exception($error);
			}

			$users = array_merge($users, array_map(function($member) { return $member->email;}, $result->members));
			$nextPageToken = $result->nextPageToken;
			$didAtLeastOneQuery = true;
		}

		return $users;
	}
}
