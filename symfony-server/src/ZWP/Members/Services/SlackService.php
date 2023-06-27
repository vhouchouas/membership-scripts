<?php

namespace ZWP\Members\Services;

require_once "../../../../vendor/autoload.php";

use JoliCode\Slack\ClientFactory;
use Psr\Log\LoggerInterface;

class SlackService {

	public function __construct(
			private LoggerInterface $logger,
			private ContainerBagInterface $params,
	) {
		$this->client = ClientFactory::create($params->get('slack.botToken'));
	}

	/**
	 * Beware: rate limiting for this endpoint is Tier 2, which mean we can afford
	 * roughly 20 call per minute.
	 */
	public function usersList() {
		return $this->client->usersList();
	}

	/**
	 * Meant to take in input the list of the email of all members.
	 * Will return the subset who have deactivated Slack account.
	 * Those are likely old members that renew there susbscription recently
	 * and for which we need to manually reactivate the slack account
	 */
	public function findDeactivatedMembers(array $membersEmail): array {
		$allSlackUsers = $this->usersList()->getMembers(); // array of ObjsUser
		$this->logger->info("Got " . count($allSlackUsers) . " slack users");
		$emailsOfDeactivatedSlackUsers = array();
		foreach($allSlackUsers as $slackUser) {
			if ($slackUser->getDeleted()) {
				$profile = $slackUser->getProfile(); // ObjsUserProfile
				if ($profile != null ) { // Not sure if it's possible that it's null but better safe than sorry
					$email = $profile->getEmail();
					if ( $email != null ) { // May be null for some app
						$emailsOfDeactivatedSlackUsers []= $email;
					}
				}
			}
		}
		$this->logger->info("Got " . count($emailsOfDeactivatedSlackUsers) . " deactivated users");

		return array_intersect($membersEmail, $emailsOfDeactivatedSlackUsers);
	}
}
