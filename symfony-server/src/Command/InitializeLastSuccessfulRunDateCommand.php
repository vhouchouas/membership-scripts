<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Repository\OptionRepository;

#[AsCommand(name: 'doctrine:database:initialize-last-successful-run-date')]
class InitializeLastSuccessfulRunDateCommand extends Command {
	public function __construct(private OptionRepository $optionRepository) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->addArgument('date', InputArgument::REQUIRED, 'The date to insert in db');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($this->optionRepository->hasLastSuccessfulRunDate() ){
			$output->writeln(["A date is already set. For safety reason we don't update it."]);
			return Command::INVALID;
		}

		$date = new \DateTime($input->getArgument('date'));
		$this->optionRepository->writeLastSuccessfulRunDate($date, false);

		$output->writeln(["Inserted date in db"]);

		return Command::SUCCESS;
	}
}
