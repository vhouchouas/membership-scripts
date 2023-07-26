<?php

namespace App\Controller;

use OpenAPI\Server\Api\DefaultApiInterface;
use OpenAPI\Server\Model\ApiMembersSortedByLastRegistrationDateGet200ResponseInner;
use OpenAPI\Server\Model\ApiMembersPerPostalCodeGet200ResponseInner;
use App\Services\RegistrationDateUtil;
use App\Services\MemberImporter;
use App\Services\SlackService;

use App\Repository\MemberRepository;
use App\Entity\Member;
use Psr\Log\LoggerInterface;

class DefaultApi implements DefaultApiInterface {

	public function __construct(
		private LoggerInterface $logger,
		private MemberRepository $memberRepository,
		private RegistrationDateUtil $registrationDateUtil,
		private MemberImporter $memberImporter,
		private SlackService $slackService,
	) { }

	public function apiMembersSortedByLastRegistrationDateGet(?\DateTime $since, int &$responseCode, array &$responseHeaders): array|object|null {
		if ($since == null) {
			$since = $this->registrationDateUtil->getDateAfterWhichMembershipIsConsideredValid();
			$this->logger->info("getting member without specifying a start date. We use " . $since->format('Y-m-d\TH:i:s'));
		}

		$result = array();
		foreach($this->memberRepository->getOrderedListOfLastRegistrations($since) as $entity) {
			$result []= new ApiMembersSortedByLastRegistrationDateGet200ResponseInner($entity);
		}

		return $result;
	}

	public function apiMembersPerPostalCodeGet(int &$responseCode, array &$responseHeaders): array|object|null {
		$since = $this->registrationDateUtil->getDateAfterWhichMembershipIsConsideredValid();
		$result = array();
		foreach($this->memberRepository->getMembersPerPostalCode($since) as $row) {
			$result []= new ApiMembersPerPostalCodeGet200ResponseInner($row);
		}
		return $result;
	}

	public function apiTriggerImportRunGet(?bool $debug, int &$responseCode, array &$responseHeaders): void {
		$this->memberImporter->run($debug ?? true);
	}

	public function apiSlackAccountsToReactivateGet(int &$responseCode, array &$responseHeaders): array|object|null {
		return $this->slackService->findDeactivatedMembers();
	}

	public function apiSlackAccountsToDeactivateGet(int &$responseCode, array &$responseHeaders): array|object|null {
		return $this->slackService->findUsersToDeactivate();
	}
}
