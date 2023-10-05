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

#[AsCommand(name: 'member:add')]
class AddMember extends Command {
	public function __construct(private MemberRepository $memberRepository) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->addArgument('email', InputArgument::REQUIRED, 'The member primary email');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$email = $input->getArgument('email');

		$member = $this->memberRepository->findOneBy(['email' => $email]);
		if ($member) {
			$output->writeln('Member already exists');
			return Command::FAILURE;
		}

		$member = $this->createMember($email);

		$this->memberRepository->save($member, true);
		$output->writeln("Member created");

		return Command::SUCCESS;
	}

	private function createMember(string $email): Member {
		$member = new Member();

		$member->setEmail($email);
		$member->setFirstName($this->generateRandomString(4));
		$member->setLastName($this->generateRandomString(5));
		$member->setPostalCode("75000");
		$member->setHelloAssoLastRegistrationEventId(rand());
		$member->setCity("Paris");
		$member->setHowDidYouKnowZwp("xxx");
		$member->setWantToDo("yyy");
		$member->setFirstRegistrationDate(new \DateTime());
		$member->setLastRegistrationDate(new \DateTime());
		$member->setIsZWProfessional(false);
		$member->setNotificationSentToAdmin(false);

		return $member;
	}

	private function generateRandomString(int $length): string {
		return substr(md5(rand()), 0, $length); 
	}
}
