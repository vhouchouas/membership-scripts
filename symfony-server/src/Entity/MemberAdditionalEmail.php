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

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MemberAdditionalEmail {
	#[ORM\ManyToOne(targetEntity: Member::class)]
	private ?Member $member = null;

	#[ORM\Id]
	#[ORM\Column(length: 255, unique: true)]
	private ?string $email = null;

	public function __construct(Member $member, string $email) {
		$this->member = $member;
		$this->email = $email;
	}

	public function getEmail(): string {
		return $this->email;
	}
}
