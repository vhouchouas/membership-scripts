<?php

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class UserCommandsTest extends KernelTestCase
{
	public function testExecute(): void {
		$kernel = self::bootKernel();
		$application = new Application($kernel);

		// Precondition: no user should exist
		$listUsersCommand = new CommandTester($application->find('user:list'));
		$listUsersCommand->execute([]);
		$listUsersCommand->assertCommandIsSuccessful();
		$output = $listUsersCommand->getDisplay();
		$this->assertEmpty($output, "Precondition: we expect no user at that poin");

		// Create a user
		$addUserCommand = new CommandTester($application->find('user:add'));
		$addUserCommand->execute(["email" => "some@name.fr", "password" => "mypassword"]);
		$addUserCommand->assertCommandIsSuccessful();

		// // Assert user is created
		$listUsersCommand->execute([]);
		$listUsersCommand->assertCommandIsSuccessful();
		$output = $listUsersCommand->getDisplay();
		$this->assertStringContainsString("some@name.fr", $output, "user should have been created");

		// Test password update command (just ensure it does not crash, no assertions here)
		$updateUserPasswordCommand = new CommandTester($application->find('user:update-password'));
		$updateUserPasswordCommand->execute(["email" => "some@name.fr", "password" => "my-new-password"]);
		$updateUserPasswordCommand->assertCommandIsSuccessful();

		// Delete user
		$deleteUserCommand = new CommandTester($application->find('user:delete'));
		$deleteUserCommand->execute(["email" => "some@name.fr"]);
		$deleteUserCommand->assertCommandIsSuccessful();

		// // Assert user is deleted
		$listUsersCommand->execute([]);
		$listUsersCommand->assertCommandIsSuccessful();
		$output = $listUsersCommand->getDisplay();
		$this->assertEmpty($output, "No users should be left");
	}
}
