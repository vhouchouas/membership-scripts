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

use App\Models\RegistrationEvent;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Models\GroupWithDeletableUsers;

class MailchimpConnector implements GroupWithDeletableUsers {

	public function __construct(
		private LoggerInterface $logger,
		private ContainerBagInterface $params,
		private HttpClientInterface $client,
		) {}

	function groupName(): string {
		return "MailChimp";
	}

	public function registerEvent(RegistrationEvent $event, bool $debug): void {
		$payload_str = $this->registrationEventToJsonPayload($event);

		if ($debug) {
			$this->logger->info("Debug mode: we skip mailchimp registration");
		} else {
			$this->logger->info("Going to register on MailChimp user " . $event->first_name . " " . $event->last_name);
			// Going to run the equivalent of: curl -XPOST -d '$payload_str' --user '" . MC_USERPWD ."' '". MC_LIST_URL . "'";
			$response = $this->client->request('POST', $this->params->get('mailchimp.listUrl'), [
				'auth_basic' => $this->params->get('mailchimp.userPassword'),
				'body' => $payload_str
			])->getContent(false);

			if (str_contains($response, "is already a list member")) {
				$this->logger->info("This user was already registered. Moving on");
			} else if (!str_contains($response,  '"status":"subscribed"') // When a user is correctly registered we should get this
			  || str_contains($response, '"status":4')) { // status 4 is ok when it's because member was already registered. Otherwise it's weird
				$this->logger->error("Unexpected answer from mailchimp: got: " . $response);
			}
		}

		$this->logger->info("Done with this registration");
	}

	private function registrationEventToJsonPayload(RegistrationEvent $event): string {
		$merge_fields = array();
		$merge_fields["FNAME"]   = $event->first_name;
		$merge_fields["LNAME"]   = $event->last_name;
		$merge_fields["MMERGE6"] = "placeholder"; // To remove here when we removed it from mailchimp side
	
		$payload = array();
		$payload["email_address"] = $event->email;
		$payload["status"]        = "subscribed";
		$payload["merge_fields"]  = $merge_fields;
	
		return json_encode($payload);
	}

	public function deleteUsers(array $emails, bool $debug): void {
		foreach($emails as $email) {
			$this->deleteUser($email, $debug);
		}
	}

	public function deleteUser(string $email, bool $debug): void {
		if ($debug) {
			$this->logger->info("Debug mode: skipping deletion of $email from mailchimp");
		} else {
			$this->logger->info("Going to archive from mailchimp user $email");
			$response = $this->client->request('DELETE', $this->params->get('mailchimp.listUrl') . md5(strtolower($email)), [
				'auth_basic' => $this->params->get('mailchimp.userPassword'),
			]);

			if (str_contains($response->getContent(false), "Resource Not Found")) {
				$this->logger->info("Couldn't archive: this email has probably never been in the list");
			} else if (str_contains($response->getContent(false),  "This list member cannot be removed")) {
				$this->logger->info("Couldn't archive: this email was probably already deleted");
			} else if ($response->getStatusCode() == 204) {
				$this->logger->info("The user has been successfully archived");
			} else {
				$error = "Unexpected response when trying to delete $email form mailchimp: http code: " . $response->getStatusCode() . ", response: " . $response->getContent();
				$this->logger->error($error);
				throw new Exception($error);
			}
		}
	}

	public function getUsers(): array {
		$users = array();
		$nb_items_to_retrieve = 1; // whatever, it will be initialized after the 1st query. Just make sure it's greater than 0
		$page = 0;
		while(count($users) < $nb_items_to_retrieve){
			$response = $this->getPageOfUsers($page);
			$page += 1;
			$nb_items_to_retrieve = $response->total_items;
			foreach($response->members as $member){
				$users[] = $member->email_address;
			}
		}
		return $users;
	}

	private function getPageOfUsers($page){
		$result_per_page = 500;

		$this->logger->info("Going to get page $page of users registered in mailchimp");
		$response = $this->client->request('GET', $this->params->get('mailchimp.listUrl') . '?offset=' . $result_per_page*$page . "&count=$result_per_page", [
			'auth_basic' => $this->params->get('mailchimp.userPassword'),
		]);
		return json_decode($response->getContent());
	}
}
