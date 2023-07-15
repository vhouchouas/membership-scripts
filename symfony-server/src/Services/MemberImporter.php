<?php

namespace App\Services;

use App\Entity\Option;
use App\Repository\OptionRepository;
use Psr\Log\LoggerInterface;
use App\Repository\MemberRepository;
use App\Services\HelloAssoConnector;
use App\Services\MailchimpConnector;


// TODO:
// - configure lock to ensure we don't run concurrently?
// - configure logger to have those logs in a separated file?
class MemberImporter {

	public function __construct(
			private LoggerInterface $logger,
			private OptionRepository $optionRepository,
			private HelloAssoConnector $helloassoConnector,
			private MemberRepository $memberRepository,
			private MailchimpConnector $mailchimpConnector,
			private GoogleGroupService $googleConnector
			) {}

	public function run(bool $debug) {
		$now = new \DateTime();
		$lastSuccessfulRunDate = $this->optionRepository->getLastSuccessfulRunDate();
		$dateBeforeWhichAllRegistrationsHaveBeenHandled = $this->computeDateBeforeWhichAllRegistrationsHaveBeenHandled($lastSuccessfulRunDate);

		$subscriptions = $this->helloassoConnector->getAllHelloAssoSubscriptions($dateBeforeWhichAllRegistrationsHaveBeenHandled, $now);
		$this->logger->info("retrieved data from HelloAsso. Got " . count($subscriptions) . " action(s)");

		foreach($subscriptions as $subscription) {
			$this->memberRepository->addOrUpdateMember($subscription, $debug);
			$this->mailchimpConnector->registerEvent($subscription, $debug);
			$this->googleConnector->registerEvent($subscription, $debug);
		}

		// TODO: send weekly notification about newcomers
    // TODO: send a mail if there are Slack accounts to reactivate
    // TODO: delete outdated members if needed

		$this->optionRepository->writeLastSuccessfulRunDate($now, $debug);
		$this->logger->debug("Completed successfully");
	}

	private function computeDateBeforeWhichAllRegistrationsHaveBeenHandled(\DateTime $lastSuccessfulRunDate): \DateTime {
		$dateBeforeWhichAllRegistrationsHaveBeenHandled = RegistrationDateUtil::getDateBeforeWhichAllRegistrationsHaveBeenHandled($lastSuccessfulRunDate);
		$this->logger->debug("Last successful run was at " . $this->dateToStr($lastSuccessfulRunDate) . ". We handle registrations that occur after " . $this->dateToStr($dateBeforeWhichAllRegistrationsHaveBeenHandled));

		return $dateBeforeWhichAllRegistrationsHaveBeenHandled;
	}

	private function dateToStr(\DateTime $d) : string {
		return $d->format('Y-m-d\TH:i:s');
	}
}
