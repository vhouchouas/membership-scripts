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

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use App\Repository\UserRepository;

#[AsCommand(name: 'user:add')]
class AddUserCommand extends Command {
	public function __construct(private UserRepository $userRepository, private UserPasswordHasherInterface $passwordHasher) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->addArgument('email', InputArgument::REQUIRED, 'User email (will be the login)')
			->addArgument('password', InputArgument::REQUIRED, 'User password');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$email = $input->getArgument('email');
		$password = $input->getArgument('password');

		$user = $this->userRepository->findOneBy(['email' => $email]);
		if ($user) {
			$output->writeln('User already exists');
			return Command::FAILURE;
		}

		$user = new User();
		$user->setEmail($email);
		$user->setPassword($this->passwordHasher->hashPassword($user, $password));

		$this->userRepository->saveAndFlush($user);

		$output->writeln('User created');
		return Command::SUCCESS;
	}
}
