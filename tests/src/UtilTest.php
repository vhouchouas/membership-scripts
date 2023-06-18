<?php
declare(strict_types=1);
if (!defined('ZWP_TOOLS')){
  define('ZWP_TOOLS', __DIR__ . '/../temp-src-copy/');
}
require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'lib/doctrine/MemberDTO.php');
use InterNations\Component\HttpMock\PHPUnit\HttpMockTrait;

use PHPUnit\Framework\TestCase;

final class UtilTest extends TestCase {
	public function test_keepOnlyActualMembers(){
		// Setup
		$actualMember1 = $this->buildMember("pouet@gmail.com");
		$testMember2   = $this->buildMember("guillaume.turri+test20210214@gmail.com");
		$actualMember3 = $this->buildMember("toto@yahoo.com");
		$members = array($actualMember1,  $testMember2, $actualMember3);

		// Perform the test
		$filteredMembers = keepOnlyActualMembers($members);

		// Assertion
		$this->assertEquals(2, count($filteredMembers));
		$this->assertContains($actualMember1, $filteredMembers);
		$this->assertNotContains($testMember2, $filteredMembers);
		$this->assertContains($actualMember3, $filteredMembers);

	}

	private function buildMember($email="toto@toto.fr"): MemberDTO {
		$result = new MemberDTO();
		$result->email = $email;
		// the other fields don't matters for the tests
		return $result;
	}
}
