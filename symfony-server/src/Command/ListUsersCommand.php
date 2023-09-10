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
use App\Entity\User;
use App\Repository\UserRepository;

#[AsCommand(name: 'user:list')]
class ListUsersCommand extends Command {
	public function __construct(private UserRepository $userRepository) {
		parent::__construct();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$users = $this->userRepository->findAll();
		foreach ($users as $user) {
			$output->writeln($user->getId() . " " . $user->getEmail());
		}
		return Command::SUCCESS;
	}
}
