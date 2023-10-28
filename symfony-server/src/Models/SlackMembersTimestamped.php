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

namespace App\Models;

use App\Services\NowProvider;
use Psr\Log\LoggerInterface;

/**
 * Represents a list of slack users, and the time when it was retrieved.
 * Note that:
 * - an instance of this class does not necessarily represents all Slack members,
 *   it could for instance represent the list of deactivated slack members.
 * - the timestamp represents when the slack API was queried in the first place. Since
 *   this data comes from an endpoint with a low throttling threshold, it may occur that
 *   we manipulate data retrieved previously and cached. The timestamp makes it possible
 *   to know how old this data is.
 */
class SlackMembersTimestamped {
	function __construct(private int $timestamp, private array $members, private bool $isFresh) {}

	public function isFresh(): bool {
		return $this->isFresh;
	}

	public static function create(\DateTime $now, array $members) {
		return new SlackMembersTimestamped($now->getTimestamp(), $members, true);
	}

	public function serializeToFile(string $file) {
		if (!file_exists(dirname($file))) {
			mkdir(dirname($file), 0700, true);
		}
		file_put_contents($file, serialize($this));
	}

	public static function fromFile(string $file, LoggerInterface $logger, \DateTime $now,  int $maxAllowedAgeInSecond): SlackMembersTimestamped {
		if (!file_exists($file)){
			$logger->info("The file " . $file . " doesn't exist");
			throw new \Exception("Cannot deserialize SlackMembersTimestamped from file " . $file . " because the file does not exist");
		}

		$res = unserialize(file_get_contents($file));
		$res->isFresh = false;

		$age = $res->ageInSeconds($now);
		if ($age > $maxAllowedAgeInSecond) {
			throw new \Exception("Deserialized object is too old (timestamp: " . $res->timestamp . ", age: $age)");
		}
		return $res;
	}

	public function getMembers(): array {
		return $this->members;
	}

	public function getTimestamp(): int {
		return $this->timestamp;
	}

	public function ageInSeconds(\DateTime $now): int {
		return $now->getTimestamp() - $this->timestamp;
	}
}

