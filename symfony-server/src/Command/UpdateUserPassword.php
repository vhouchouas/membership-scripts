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
use Symfony\Component\Console\Question\Question;
use App\Entity\User;
use App\Repository\UserRepository;

#[AsCommand(name: 'user:update-password', description: 'Update user password')]
class UpdateUserPassword extends Command {
	public function __construct(private UserRepository $userRepository, private UserPasswordHasherInterface $passwordHasher) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->addArgument('email', InputArgument::REQUIRED, 'User email (ie: his or her login)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$email = $input->getArgument('email');

		$helper = $this->getHelper('question');

		$question = new Question('password:');
		$question->setHidden(true);
		$question->setHiddenFallback(false);

		$password = $helper->ask($input, $output, $question);

		$user = $this->userRepository->findOneBy(['email' => $email]);
		if (!$user) {
			$output->writeln('User does not exist');
			return Command::FAILURE;
		}

		$this->userRepository->upgradePassword($user, $this->passwordHasher->hashPassword($user, $password));

		$output->writeln('Password updated');
		return Command::SUCCESS;
	}
}
