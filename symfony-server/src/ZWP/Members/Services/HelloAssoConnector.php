<?php

namespace ZWP\Members\Services;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;
use ZWP\Members\Models\RegistrationEvent;
use ZWP\Members\Services\HelloAssoAuthenticator;

class HelloAssoConnector {
	public function __construct(
			private LoggerInterface $logger,
			private HttpClientInterface $client,
			private ContainerBagInterface $params,
			private HelloAssoAuthenticator $authenticator,
			) {}

	public function getAllHelloAssoSubscriptions(\DateTime $from, \DateTime $to){
		$this->logger->info("Going to get HelloAsso registrations");
		$formSlug = $this->params->get("helloasso.registrationFormSlug");
		$actions1 = $this->getAllHelloAssoSubscriptionsForOneCampaign($from, $to, $formSlug);
		$this->logger->info("Got " . count($actions1) . " registrations from " . $formSlug);

		$actions2 = array();
		$formSlug2 = $this->params->get("helloasso.registrationFormSlug2");
		if ($formSlug2){
			$this->logger->info("going to fetch 2nd form");
			$actions2 = $this->getAllHelloAssoSubscriptionsForOneCampaign($from, $to, $formSlug2);
			$this->logger->info("Got " . count($actions2) . " registrations from " . $formSlug2);
		}

		return array_merge($actions1, $actions2);
	}

	private function getAllHelloAssoSubscriptionsForOneCampaign(\DateTime $from, \DateTime $to, string $formSlug){
		$this->logger->info("Going to fetch data from $formSlug");
		$result = array();
		$json = $this->getHelloAssoJsonSubscriptionsForOneCampaign($from, $to, $formSlug);
		$dataKey = "data";
		if (!array_key_exists($dataKey, $json)){
			$error = "No $dataKey in the json. Got: " .print_r($json, TRUE);
			$this->logger->error($error);
			throw new Exception($error);
		}
		$data = $json[$dataKey];
		foreach($data as $jsonRegistration){
			if ( $this->ismembership($jsonRegistration)){
				$result[] = $this->parseJsonRegistration($jsonRegistration);
			} else {
				$this->logger->info("skipping a line which isn't a membership");
			}
		}

		return $result;
	}

	private function isMembership($jsonRegistration){
		return $jsonRegistration["type"] === "Membership"; // we filter out for instance "Donation"
	}

	private function getHelloAssoJsonSubscriptionsForOneCampaign(\DateTime $from, \DateTime $to, $formSlug){
		$accessToken = $this->authenticator->getAccessToken();
		$fromStr = $from->format('Y-m-d\TH:i:s');
		$toStr = $to->format('Y-m-d\TH:i:s');
		$url = "https://api.helloasso.com/v5/organizations/"
			. $this->params->get('helloasso.organizationSlug')
			. "/forms/Membership/$formSlug/items"
			. "?from=$fromStr"
			. "&to=$toStr"
			. "&withDetails=true" // to get custom fields
			. "&retrieveAll=true"; // so we don't have to bother with pagination

		$response = $this->client->request('GET', $url, [
				'headers' => ["Authorization" => "Bearer $accessToken"],
		]);

		$json = json_decode($response->getContent(), true);

		if ( $json === NULL ){
			$error = "failed to parse: " . $raw_content;
			$this->logger->error($error);
			throw new Exception($error);
		}

		return $json;
	}

	private function parseJsonRegistration($jsonRegistration){
		$result = new RegistrationEvent();
		$result->helloasso_event_id = $jsonRegistration["id"];
		$result->event_date = $jsonRegistration["order"]["date"];
		$result->first_name = $jsonRegistration["user"]["firstName"];
		$result->last_name = $jsonRegistration["user"]["lastName"];

		if (! array_key_exists("customFields", $jsonRegistration)){
			$error = "no 'customFields' in the registration " . print_r($jsonRegistration, true);
			$this->logger->error($error);
			throw new Exception($error);
		}
		foreach($jsonRegistration["customFields"] as $customField){
			switch($customField["name"]){
				case "Email":
					$result->email = $customField["answer"];
					break;
				case "Ville":
					$result->city = $customField["answer"];
					break;
				case "Code Postal":
					$result->postal_code = $customField["answer"];
					break;
				case "Numéro de téléphone":
					$result->phone = $customField["answer"];
					break;
				case "Comment as-tu connu Zero Waste Paris ?":
					$result->how_did_you_know_zwp = $customField["answer"];
					break;
				case "Sur quel projet souhaites-tu t'investir en priorité ?":
					$result->want_to_do = $customField["answer"];
					break;
				case "Portes-tu un projet professionnel autour du zéro déchet ?":
					$result->is_zw_professional = $customField["answer"];
					break;
			}
		}

		return $result;
	}
}
