<?php

namespace App\Services;

use App\Entity\Option;
use App\Repository\OptionRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use App\Repository\MemberRepository;
use App\Services\HelloAssoConnector;
use App\Services\MailchimpConnector;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Mime\Email;


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
			private GoogleGroupService $googleConnector,
			private ContainerBagInterface $params,
			private MailerInterface $mailer,
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

		$this->sendEmailNotificationForAdminsAboutNewcomersIfneeded($lastSuccessfulRunDate, $now, $debug);

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

	private function sendEmailNotificationForAdminsAboutNewcomersIfneeded(\DateTime $lastSuccessfulRunDate, \DateTime $now, bool $debug) {
		$dateUtil = new RegistrationDateUtil($now); // we do a new rather than leveraging DI because we need to inject our "now";
		if ($dateUtil->needToSendNotificationAboutLatestRegistrations($lastSuccessfulRunDate)) {
			$this->logger->info("Going to send weekly email about newcomers");

			$newcomers = $this->memberRepository->getMembersForWhichNoNotificationHasBeenSentToAdmins();
			$this->sendNotificationForAdminsAboutNewcomers($newcomers, $debug);
			$this->memberRepository->updateMembersForWhichNotificationHasBeenSentoToAdmins($newcomers, $debug);
		} else {
			$this->logger->info("No need to send weekly email about newcomers");
		}
	}

	private function sendNotificationForAdminsAboutNewcomers(array $newcomers, bool $debug) {
		$body = "";
		if (empty($newcomers)) {
			$body = "Oh non, il n'y a pas eu de nouveaux membres cette semaine ! :(";
		} else {
			$body = "Voici les " . count($newcomers) . " membres qui ont rejoint l'asso cette semaine.\r\n";
			$body .= "(Attention : ce mail contient des données personnelles, ne le transférez pas, et pensez à le supprimer à terme.)\r\n";
			foreach($newcomers as $newcomer) {
				$body .= "\r\n";
				$body .= $newcomer->getFirstName() . " " . $newcomer->getLastName() . " (" . $newcomer->getEmail() . ")\r\n";
				$body .= "Adhésion le " . $this->dateToStr($newcomer->getLastRegistrationDate()) . "\r\n";
				$body .= "Réside à : " . $newcomer->getcity() . " (" . $newcomer->getPostalCode() . ")\r\n";
				$body .= "A connu l'asso : " . $newcomer->getHowDidYouKnowZwp() . "\r\n";
				$body .= "Iel est motivé par : " . $newcomer->getWantToDo() . "\r\n";
			}

			$body .= "\r\n";
			$body .= "Il y a un projet en cours qui leur correspond ? Un GT qui recherche de nouveaux membres ? C’est le moment de leur dire et/ou d’en parler à un.e référent.e ! ";
		}

		$email = (new Email())
			->from($this->params->get('admin.fromEmailForNewcomers'))
			->to($this->params->get('admin.toEmailForNewcomers'))
			->subject($this->params->get('admin.subjectEmailForNewcomers'))
			->text($body);

		$this->mailer->send($email);
		$this->logger->info("email sent");
	}
}
