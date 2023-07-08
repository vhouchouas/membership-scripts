<?php

namespace App\Services;

use App\Models\RegistrationEvent;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class MailchimpConnector {

	public function __construct(
		private LoggerInterface $logger,
		private ContainerBagInterface $params,
		private HttpClientInterface $client,
		) {}

	public function registerEvent(RegistrationEvent $event, bool $debug): void {
		$payload_str = $this->registrationEventToJsonPayload($event);

		if ($debug) {
			$this->info("Debug mode: we skip mailchimp registration");
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
			$this->deleteUser($email);
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
}
