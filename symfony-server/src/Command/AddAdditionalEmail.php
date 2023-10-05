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
use App\Entity\Member;
use App\Repository\MemberRepository;

#[AsCommand(name: 'member:additional-email:add')]
class AddAdditionalEmail extends Command {
	public function __construct(private MemberRepository $memberRepository) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->addArgument('member-primary-email', InputArgument::REQUIRED, 'The email by which the member is known')
			->addArgument('additional-email', InputArgument::REQUIRED, 'The new additional email');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$primaryEmail = $input->getArgument('member-primary-email');
		$additionalEmail = $input->getArgument('additional-email');
		$member = $this->memberRepository->findOneBy(['email' => $primaryEmail]);

		if (!$member) {
			$output->writeln('Member does not exist');
			return Command::FAILURE;
		}

		if ($member->addAdditionalEmail($additionalEmail)) {
			$this->memberRepository->save($member, true);
			$output->writeln('Additional email added');
		} else {
			$output->writeln('Additional email was already set');
		}

		return Command::SUCCESS;
	}
}
