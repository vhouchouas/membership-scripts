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
use JoliCode\Slack\ClientFactory;
use App\Repository\MemberRepository;
use Psr\Log\LoggerInterface;
use JoliCode\Slack\Api\Model\ObjsUser;

class SlackService {

	public function __construct(
		private LoggerInterface $logger,
		private ContainerBagInterface $params,
		private MemberRepository $memberRepository,
	) {
		$this->client = ClientFactory::create($params->get('slack.botToken'));
		$this->allowListedDomain = $params->get('slack.allowListedDomain');
	}

	/**
	 * Beware: rate limiting for this endpoint is Tier 2, which mean we can afford
	 * roughly 20 call per minute.
	 *
	 * @return array<ObjsUser>
	 */
	public function usersList(): array {
		try {
			return $this->client->usersList()->getMembers();
		} catch (\Throwable $t) {
			$this->logger->error("Failed to query slack because: " . $t->getMessage());
			throw $t;
		}
	}

	/**
	 * @return the members who have deactivated Slack account.
	 *         Those are likely old members that renew there susbscription recently
	 *         and for which we need to manually reactivate the slack account
	 */
	public function findDeactivatedMembers(): array {
		$membersEmail = $this->getEmailOfAllUpToDateMembers();
		$allSlackUsers = $this->usersList();
		$this->logger->info("Got " . count($allSlackUsers) . " slack users");
		$emailsOfDeactivatedSlackUsers = array();
		foreach($allSlackUsers as $slackUser) {
			if ($slackUser->getDeleted()) {
				$slackEmail = $this->extractUserEmail($slackUser);
				if ($slackEmail !== null) {
					$emailsOfDeactivatedSlackUsers []= $slackEmail;
				}
			}
		}
		$this->logger->info("Got " . count($emailsOfDeactivatedSlackUsers) . " deactivated users");

		$intersection = array_intersect($membersEmail, $emailsOfDeactivatedSlackUsers);

		$result = array();
		foreach ($intersection as $index => $email) {
			$result []= $email;
		}
		return $result;
	}

	public function findUsersToDeactivate(): array {
		$membersEmail = array_map(function($email) {return strtolower($email);}, $this->getEmailOfAllUpToDateMembers());
		$allSlackUsers = $this->usersList();
		$allActiveSlackUsers = array_filter($allSlackUsers, function($objUser) {return !$objUser->getDeleted();});
		$allSlackUsersEmail = array_filter(array_map(function($objUser) {return $this->extractUserEmail($objUser);}, $allActiveSlackUsers));
		$allLowerCasedSlackUsersEmail = array_map(function($email) {return strtolower($email);}, $allSlackUsersEmail);

		$emailOfNonMembers = array_diff($allLowerCasedSlackUsersEmail, $membersEmail);
		return array_filter($emailOfNonMembers, function($email) {return explode('@', $email)[1] !== $this->allowListedDomain;});
	}

	private function extractUserEmail(ObjsUser $user): ?string {
		$profile = $user->getProfile(); // ObjsUserProfile
		if ($profile != null) { // Not sure if it's possible that it's null but better safe than sorry
			return $profile->getEmail(); // Note that it may be null for some app
		}
		return null;
	}

	private function getEmailOfAllUpToDateMembers(): array {
		return array_map(function ($member) {return $member->getEmail();}, $this->memberRepository->findAll());
	}
}
