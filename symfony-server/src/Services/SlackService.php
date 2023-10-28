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
use App\Models\SlackMembersTimestamped;
use App\Services\NowProvider;
use App\Repository\MemberRepository;
use Psr\Log\LoggerInterface;
use JoliCode\Slack\Api\Model\ObjsUser;

class SlackService {

	public function __construct(
		private LoggerInterface $logger,
		private ContainerBagInterface $params,
		private MemberRepository $memberRepository,
		private NowProvider $nowProvider,
	) {
		$this->client = ClientFactory::create($params->get('slack.botToken'));
		$this->allowListedDomain = $params->get('slack.allowListedDomain');
		$this->usersListLocalCache = $params->get('localcache') . '/slackUserList.dat';
	}

	/**
	 * Beware: rate limiting for this endpoint is Tier 2, which mean we can afford
	 * roughly 20 call per minute.
	 *
	 * @return array<ObjsUser>
	 */
	public function usersList(): SlackMembersTimestamped {
		try {
			$usersList = $this->client->usersList();
			$res = SlackMembersTimestamped::create($this->nowProvider->getNow(), $usersList->getMembers());
			$res->serializeToFile($this->usersListLocalCache);
			return $res;
		} catch (\Throwable $t) {
			$slackErrorMessage = "Failed to query slack because: " . $t->getMessage();
			$this->logger->info($slackErrorMessage);
			$maxAcceptableAgeInSeconds = 300; // We should be able to make at least a call per minute. If data is 5 minutes old, then something is wrong
			try {
				return SlackMembersTimestamped::fromFile($this->usersListLocalCache, $this->logger, $this->nowProvider->getNow(), $maxAcceptableAgeInSeconds);
			} catch(Exception $e) {
				$this->logger->error($slackErrorMessage . "; and failed to read data from cache because: " . $e->getMessage());
				throw $t;
			}
		}
	}

	/**
	 * @return the members who have deactivated Slack account.
	 *         Those are likely old members that renew there susbscription recently
	 *         and for which we need to manually reactivate the slack account
	 */
	public function findDeactivatedMembers(): SlackMembersTimestamped {
		$membersEmail = $this->getEmailOfAllUpToDateMembers();
		$allSlackUsers = $this->usersList();
		$this->logger->info("Got " . count($allSlackUsers->getMembers()) . " slack users");
		$emailsOfDeactivatedSlackUsers = array();
		foreach($allSlackUsers->getMembers()  as $slackUser) {
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
		return new SlackMembersTimestamped($allSlackUsers->getTimestamp(), $result, $allSlackUsers->isFresh());
	}

	public function findUsersToDeactivate(): SlackMembersTimestamped {
		$membersEmail = array_map(function($email) {return strtolower($email);}, $this->getEmailOfAllUpToDateMembers());
		$allSlackUsers = $this->usersList();
		$allActiveSlackUsers = array_filter($allSlackUsers->getMembers(), function($objUser) {return !$objUser->getDeleted();});
		$allSlackUsersEmail = array_filter(array_map(function($objUser) {return $this->extractUserEmail($objUser);}, $allActiveSlackUsers));
		$allLowerCasedSlackUsersEmail = array_map(function($email) {return strtolower($email);}, $allSlackUsersEmail);

		$emailOfNonMembers = array_diff($allLowerCasedSlackUsersEmail, $membersEmail);
		return new SlackMembersTimestamped(
			$allSlackUsers->getTimestamp(),
			array_filter($emailOfNonMembers, function($email) {return explode('@', $email)[1] !== $this->allowListedDomain;}),
			$allSlackUsers->isFresh()
		);
	}

	private function extractUserEmail(ObjsUser $user): ?string {
		$profile = $user->getProfile(); // ObjsUserProfile
		if ($profile != null) { // Not sure if it's possible that it's null but better safe than sorry
			return $profile->getEmail(); // Note that it may be null for some app
		}
		return null;
	}

	private function getEmailOfAllUpToDateMembers(): array {
		$ret = array();
		foreach($this->memberRepository->getAllUpToDateMembers() as $member) {
			$ret = array_merge($ret, $member->getAllEmails());
		}
		return $ret;
	}
}
