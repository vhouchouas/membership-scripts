<?php

namespace ZWP\Members\Api;

use OpenAPI\Server\Api\DefaultApiInterface;
use OpenAPI\Server\Model\ApiMembersGet200ResponseInner;
use OpenAPI\Server\Model\ApiMembersPerPostalCodeGet200ResponseInner;

class DefaultApi implements DefaultApiInterface {

	public function apiMembersGet(?\DateTime $since, int &$responseCode, array &$responseHeaders): array|object|null {
		// TODO
		$member = new ApiMembersGet200ResponseInner();
		$member->setFirstName("me");
		$member->setLastName("myself");
		return [$member];
	}
	public function apiMembersPerPostalCodeGet(int &$responseCode, array &$responseHeaders): array|object|null {
		// TODO
		return [
			new ApiMembersPerPostalCodeGet200ResponseInner(["postalCode" => "92100", "count" => "9"])
		];
	}
	public function apiTriggerImportRunGet(?bool $debug, int &$responseCode, array &$responseHeaders): void {
		// TODO
	}
}
