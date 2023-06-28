<?php

namespace App\Services;

use App\Entity\Option;
use App\Repository\OptionRepository;
use Psr\Log\LoggerInterface;
use App\Services\HelloAssoConnector;


// TODO:
// - configure lock to ensure we don't run concurrently?
// - configure logger to have those logs in a separated file?
class MemberImporter {
  const OPTION_LASTSUCCESSFULRUN_NAME = "last_successful_run_date";

	public function __construct(
			private LoggerInterface $logger,
			private OptionRepository $optionRepository,
			private HelloAssoConnector $helloassoConnector,
			) {}

	public function run(bool $debug) {
		$now = new \DateTime();
		$lastSuccessfulRunDateOption = $this->optionRepository->findOneBy(['name' => self::OPTION_LASTSUCCESSFULRUN_NAME]);
		$dateBeforeWhichAllRegistrationsHaveBeenHandled = $this->computeDateBeforeWhichAllRegistrationsHaveBeenHandled($lastSuccessfulRunDateOption);

		$subscriptions = $this->helloassoConnector->getAllHelloAssoSubscriptions($dateBeforeWhichAllRegistrationsHaveBeenHandled, $now);
		$this->logger->info("retrieved data from HelloAsso. Got " . count($subscriptions) . " action(s)");

		// TODO

		$this->updateLastSuccessfulRunDate($now, $lastSuccessfulRunDateOption, $debug);
		$this->logger->debug("Completed successfully");
	}

	private function computeDateBeforeWhichAllRegistrationsHaveBeenHandled(?Option $lastSuccessfulRunDateOption): \DateTime {
		if ($lastSuccessfulRunDateOption === null) {
			$error = "Can't retrieve the last succesful run start date, we abort";
			$this->logger->critical($error);
			throw new Exception($error);
		}
		$lastSuccessfulRunDate = unserialize($lastSuccessfulRunDateOption->getValue());
		$dateBeforeWhichAllRegistrationsHaveBeenHandled = RegistrationDateUtil::getDateBeforeWhichAllRegistrationsHaveBeenHandled($lastSuccessfulRunDate);
		$this->logger->debug("Last successful run was at " . $this->dateToStr($lastSuccessfulRunDate) . ". We handle registrations that occur after " . $this->dateToStr($dateBeforeWhichAllRegistrationsHaveBeenHandled));

		return $dateBeforeWhichAllRegistrationsHaveBeenHandled;
	}

	private function updateLastSuccessfulRunDate(\DateTime $startDate, Option $lastSuccessfulRunDateOption, bool $debug) {
		$lastSuccessfulRunDateOption->setValue(serialize($startDate));
		if ($debug) {
			$this->logger->debug("Not updating start date in db because we're in debug mode");
		} else {
			$this->logger->debug("Start date successfully persisted in db");
			$this->optionRepository->save($lastSuccessfulRunDateOption, true);
		}
	}

	private function dateToStr(\DateTime $d) : string {
		return $d->format('Y-m-d\TH:i:s');
	}
}
