<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use App\Services\GroupMemberDeleter;
use App\Repository\MemberRepository;
use App\Entity\Member;
use App\Models\GroupWithDeletableUsers;

final class GroupMemberDeleterTest extends KernelTestCase {
	public function test_deleteExpectedMembers(): void {
		// Setup
		self::bootKernel();

		$currentMembersEmails = [
			"someuserNotInTheGroup@mail.com", // Should be kept: it does not matter he is not in the group
			"someoneWithSomeCase@mail.com", // Should be kept: we should make case insensitive comparison
		];

		$group = $this->createMock(GroupWithDeletableUsers::class);
		$group->expects(self::once())->method('getUsers')->willReturn([
			'someUserToDelete@mail.com',
			'SOMEONEwithSOMEcase@mail.com', // should be kept since we should make case insensitive comparisons
		]);

		// Setup the main assertion
		$group->expects(self::once())->method('deleteUser')->with($this->equalTo('someUserToDelete@mail.com'));

		// Act
		$sut = self::getContainer()->get(GroupMemberDeleter::class);
		$sut->deleteOutdatedMembersFromGroups($currentMembersEmails, [$group], false);
	}

	private function buildMember($email): Member {
		$member = new Member();
		$member->setEmail($email);
		return $member;
	}
}
