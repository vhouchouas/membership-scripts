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

use Psr\Log\LoggerInterface;

class HelloAssoTokens {
	/**
	 * To make those instances easier to manipulate, this constructor assumes that the tokens validity
	 * was already checked. That's why this constructor is private and can only be called from factory
	 * methods provided by this class.
	 */
	private function __construct(private array $tokens) {}

	/**
	 * return either a valid instance, or null if it's not possible to get valid
	 * token from the specified file
	 */
	public static function fromFile(string $tokensFile, LoggerInterface $logger): ?HelloAssoTokens {
		if (!file_exists($tokensFile)){
			$logger->info("The tokens file " . $tokensFile . " doesn't exist");
			return null;
		}

		return self::fromContentInRam(file_get_contents($tokensFile), $logger);
	}

	/**
	 * return either a valid instance, or null if it's not possible to get valid
	 * token from the data
	 */
	public static function fromContentInRam(string $data, LoggerInterface $logger): ?HelloAssoTokens {
		$tokens = json_decode($data, true);
		return self::isValidTokensJson($tokens, $logger) ? new HelloAssoTokens($tokens) : null;
	}

	public function getAccessToken(): string {
		return $this->tokens["access_token"];
	}

	public function getRefreshToken(): string {
		return $this->tokens["refresh_token"];
	}

	private static function isValidTokensJson(?array $tokens, LoggerInterface $logger){
		if (is_null($tokens)){
			$logger->error("Invalid tokens: it doesn't look like valid json");
			return false;
		} else if (!array_key_exists("access_token", $tokens)){
			$logger->error("Invalid tokens: it is missing access_token");
			return false;
		} else if (!array_key_exists("refresh_token", $tokens)){
			$logger->error("Invalid tokens: it is missing refresh_token");
			return false;
		}
		return true;
	}
}
