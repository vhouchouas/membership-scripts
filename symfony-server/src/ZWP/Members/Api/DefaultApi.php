<?php

namespace ZWP\Members\Api;

use OpenAPI\Server\Api\DefaultApiInterface;
use OpenAPI\Server\Model\ApiMembersGet200ResponseInner;
use OpenAPI\Server\Model\ApiMembersPerPostalCodeGet200ResponseInner;

use App\Repository\MemberRepository;
use App\Entity\Member;
use Psr\Log\LoggerInterface;

class DefaultApi implements DefaultApiInterface {

	public function __construct(LoggerInterface $logger, MemberRepository $memberRepository) {
		$this->logger = $logger;
		$this->memberRepository = $memberRepository;
	}

	public function apiMembersSortedByLastRegistrationDateGet(?\DateTime $since, int &$responseCode, array &$responseHeaders): array|object|null {
		if ($since == null) {
			$since = new \DateTime("2017-01-01"); // TODO: should be RegistrationDateUtil->getDateAfterWhichMembershipIsConsideredValid()
			$this->logger->info("getting member without specifying a start date. We use " . $since->format('Y-m-d\TH:i:s'));
		}
		return $this->memberRepository->getOrderedListOfLastRegistrations($since);
	}

	public function apiMembersPerPostalCodeGet(int &$responseCode, array &$responseHeaders): array|object|null {
		$since = new \DateTime("2017-01-01"); // TODO: should be RegistrationDateUtil->getDateAfterWhichMembershipIsConsideredValid()
		$result = array();
		foreach($this->memberRepository->getMembersPerPostalCode($since) as $row) {
			$result []= new ApiMembersPerPostalCodeGet200ResponseInner($row);
		}
		return $result;
	}

	public function apiTriggerImportRunGet(?bool $debug, int &$responseCode, array &$responseHeaders): void {
		// TODO
	}
}
