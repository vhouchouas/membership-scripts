<?php

namespace App\Models;

interface GroupWithDeletableUsers
{
		public function getUsers(): array;
		public function deleteUsers(array $emails, bool $debug): void;
		public function groupName(): string;
}
