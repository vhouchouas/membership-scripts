<?php

namespace App\Services;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Models\RegistrationEvent;
use Psr\Log\LoggerInterface;

class GoogleGroupService {
	public function __construct(
		private LoggerInterface $logger,
		private ContainerBagInterface $params,
		GoogleClientBuilder $clientBuilder,
	) {
		$this->service = new \Google_Service_Directory($clientBuilder->getClient());
		$this->groupName = $this->params->get('google.groupName');
	}

	function registerEvent(RegistrationEvent $event, bool $debug): void{
		if ($event->email === "" || $event->email === NULL){
			// Something is probably wrong with the registration form (already seen when a form is badly configured).
			// this ensures we don't block all upcoming registrations.
			$this->logger->error("No email for " . print_r($event, true));
		} else {
			$this->registerEmailToGroup($event->email, $debug);
		}
	}

	private function registerEmailToGroup(string $email, bool $debug): void{
		$this->logger->info("Going to register in Google group " . $this->groupName . " the email " . $email);
		$member = new \Google_Service_Directory_Member();
		$member->setEmail($email);
		$member->setRole("MEMBER");
		if ($debug) {
			$this->logger->info("Debug mode: skipping Google registration");
		} else {
			try {
				$this->service->members->insert($this->groupName, $member);
				$this->logger->info("Done with this registration in the Google group");
			} catch(\Google_Service_Exception $e){
				$reason = $e->getErrors()[0]["reason"];
				if($reason === "duplicate"){
					$this->logger->info("This member already exists");
				} else if ($reason === "notFound"){
					$this->logger->error("Error 'not found'. Perhaps the email adress $email is invalid?");
				} else if ($reason === "invalid") {
					$this->logger->error("Error 'invalid input': email $email seems invalid");
				} else {
					$this->logger->error("Unknown error for email $email:" . $e);
					throw $e;
				}
			}
		}
	}

	public function deleteUsers(array $emails, bool $debug): void{
		foreach($emails as $email){
			$this->deleteUser($email, $debug);
		}
	}

	function deleteUser(string $email, $debug): void{
		$this->logger->info("Going to delete from " . $this->groupName . " the email " . $email);
		if ($debug) {
			$this->logger->info("Debug mode: skipping deletion from Google");
		} else {
			try {
				$this->service->members->delete($this->groupName, $email);
				$this->logger->info("Done with this deletion");
			} catch(\Google_Service_Exception $e){
				if($e->getErrors()[0]["message"] === "Resource Not Found: memberKey"){
					$this->logger->info("This email wasn't in the group already");
				} else {
					$this->logger->error("Unknown error for email $email: " . $e);
					throw $e;
				}
			}
		}
	}

	function getUsers(): array {
		$users = array();
		$didAtLeastOneQuery = false;
		$nextPageToken = NULL;

		while(!$didAtLeastOneQuery || !is_null($nextPageToken)){
			try {
				$this->logger->info("Going to get a page of users from google group. Page token: $nextPageToken");
				$result = $this->service->members->listMembers($this->groupName, array('pageToken' => $nextPageToken));
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
