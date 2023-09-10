<?php

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class UserCommandsTest extends KernelTestCase
{
	public function testExecute(): void {
		$kernel = self::bootKernel();
		$application = new Application($kernel);
		$userEmail = "some@name.fr";

		// Precondition: no user should exist
		$listUsersCommand = new CommandTester($application->find('user:list'));
		$listUsersCommand->execute([]);
		$listUsersCommand->assertCommandIsSuccessful();
		$output = $listUsersCommand->getDisplay();
		$this->assertStringNotContainsString($userEmail, $output, "Precondition: the user that this test will create, should not exist yet");

		// Create a user
		$addUserCommand = new CommandTester($application->find('user:add'));
		$addUserCommand->execute(["email" => $userEmail, "password" => "mypassword"]);
		$addUserCommand->assertCommandIsSuccessful();

		// // Assert user is created
		$listUsersCommand->execute([]);
		$listUsersCommand->assertCommandIsSuccessful();
		$output = $listUsersCommand->getDisplay();
		$this->assertStringContainsString($userEmail, $output, "user should have been created");

		// Test password update command (just ensure it does not crash, no assertions here)
		$updateUserPasswordCommand = new CommandTester($application->find('user:update-password'));
		$updateUserPasswordCommand->execute(["email" => $userEmail, "password" => "my-new-password"]);
		$updateUserPasswordCommand->assertCommandIsSuccessful();

		// Delete user
		$deleteUserCommand = new CommandTester($application->find('user:delete'));
		$deleteUserCommand->execute(["email" => $userEmail]);
		$deleteUserCommand->assertCommandIsSuccessful();

		// // Assert user is deleted
		$listUsersCommand->execute([]);
		$listUsersCommand->assertCommandIsSuccessful();
		$output = $listUsersCommand->getDisplay();
		$this->assertStringNotContainsString($userEmail, $output, "The user created by this test should have been deleted");
	}
}
