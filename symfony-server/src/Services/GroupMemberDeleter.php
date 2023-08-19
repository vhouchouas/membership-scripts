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

use App\Repository\MemberRepository;
use App\Models\GroupWithDeletableUsers;
use Psr\Log\LoggerInterface;
use App\Entity\Member;

class GroupMemberDeleter {
	public function __construct(private LoggerInterface $logger) {}

	/**
	 * $membersToKeep - Array of emails (string)
	 * $groups - Array of GroupWithDeletableUsers
	 */
	public function deleteOutdatedMembersFromGroups(array $membersToKeep, array $groups, bool $debug): void {
		// Lower case because we observed that some emails end up register with another case...
		// ... not sure why, but this makes it possible to take it into account
		$lowercasedEmailsToKeep = array_map(fn(string $email): string => strtolower($email), $membersToKeep);

		foreach($groups as $group) {
			$this->deleteOutdatedMembersFromGroup($lowercasedEmailsToKeep, $group, $debug);
		}
	}

	private function deleteOutdatedMembersFromGroup(array $lowercasedEmailsToKeep, GroupWithDeletableUsers $group, bool $debug) {
		foreach($group->getUsers() as $existingUser) {
			if (!in_array(strtolower($existingUser), $lowercasedEmailsToKeep)) {
				$this->logger->info("Going to delete $existingUser from " . $group->groupName());
				$group->deleteUser($existingUser, $debug);
			}
		}
	}
}
