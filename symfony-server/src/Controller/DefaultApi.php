<?php

namespace App\Controller;

use OpenAPI\Server\Api\DefaultApiInterface;
use OpenAPI\Server\Model\ApiMembersSortedByLastRegistrationDateGet200ResponseInner;
use OpenAPI\Server\Model\ApiMembersPerPostalCodeGet200ResponseInner;
use App\Services\RegistrationDateUtil;
use App\Services\MemberImporter;


use App\Repository\MemberRepository;
use App\Entity\Member;
use Psr\Log\LoggerInterface;

class DefaultApi implements DefaultApiInterface {

	public function __construct(LoggerInterface $logger, MemberRepository $memberRepository, RegistrationDateUtil $registrationDateUtil, MemberImporter $memberImporter) {
		$this->logger = $logger;
		$this->memberRepository = $memberRepository;
		$this->registrationDateUtil = $registrationDateUtil;
		$this->memberImporter = $memberImporter;
	}

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
}
