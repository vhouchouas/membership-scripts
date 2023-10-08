<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Entity\Member;

class MemberTest extends TestCase {

	public function test_getAllEmails(): void {
		// Setup
		$sut = new Member();
		$sut->setEmail("primary@mail.com");
		$sut->addAdditionalEmail("secondary@mail.com");
		$sut->addAdditionalEmail("other_secondary@mail.com");

		// Act
		$allEmails = $sut->getAllEmails();

		// Assert
		$this->assertEquals(array("primary@mail.com", "secondary@mail.com", "other_secondary@mail.com"), $allEmails);

	}
}
