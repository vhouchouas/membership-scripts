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

namespace App\Services;

use App\Entity\Option;
use App\Repository\OptionRepository;
use App\Models\GroupWithDeletableUsers;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use App\Repository\MemberRepository;
use App\Services\HelloAssoConnector;
use App\Services\MailchimpConnector;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Entity\Member;

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
		private SlackService $slack,
		private UrlGeneratorInterface $router,
		) {}

	public function run(bool $debug) {
		$now = new \DateTime();
		$dateUtil = new RegistrationDateUtil($now); // we do a "new" rather than leveraging DI because we need to inject our "now";
		$lastSuccessfulRunDate = $this->optionRepository->getLastSuccessfulRunDate();
		$dateBeforeWhichAllRegistrationsHaveBeenHandled = $this->computeDateBeforeWhichAllRegistrationsHaveBeenHandled($lastSuccessfulRunDate);

		$subscriptions = $this->helloassoConnector->getAllHelloAssoSubscriptions($dateBeforeWhichAllRegistrationsHaveBeenHandled, $now);
		$this->logger->info("retrieved data from HelloAsso. Got " . count($subscriptions) . " action(s)");

		foreach($subscriptions as $subscription) {
			$this->memberRepository->addOrUpdateMember($subscription, $debug);
			$this->mailchimpConnector->registerEvent($subscription, $debug);
			$this->googleConnector->registerEvent($subscription, $debug);
		}
		$this->deleteOutdatedMembersIfNeeded($dateUtil, $lastSuccessfulRunDate, $debug);

		$this->sendEmailNotificationForAdminsAboutNewcomersIfneeded($dateUtil, $lastSuccessfulRunDate, $now, $debug);
		$this->sendEmailAboutSlackmembersToReactivate($debug);

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

	private function sendEmailNotificationForAdminsAboutNewcomersIfneeded(RegistrationDateUtil $dateUtil, \DateTime $lastSuccessfulRunDate, \DateTime $now, bool $debug) {
		if ($dateUtil->needToSendNotificationAboutLatestRegistrations($lastSuccessfulRunDate)) {
			$this->logger->info("Going to send weekly email about newcomers");

			$newcomers = $this->memberRepository->getMembersForWhichNoNotificationHasBeenSentToAdmins();
			$this->sendNotificationForAdminsAboutNewcomers($newcomers, $debug);
			$this->memberRepository->updateMembersForWhichNotificationHasBeenSentoToAdmins($newcomers, $debug);
		} else {
			$this->logger->info("No need to send weekly email about newcomers");
		}
	}

	private function sendNotificationForAdminsAboutNewcomers(array $newcomers, bool $debug): void {
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
			->from($this->params->get('notification.fromEmail'))
			->to($this->params->get('notification.newcomersEmail.to'))
			->subject($this->params->get('notification.newcomersEmail.subject'))
			->text($body);

		if (!$debug) {
			$this->mailer->send($email);
			$this->logger->info("email sent");
		} else {
			$this->logger->info("email about newcomers not sent because we're in debug mode");
		}
	}

	private function sendEmailAboutSlackMembersToReactivate($debug): void {
		$membersToReactivate = $this->slack->findDeactivatedMembers();
		if (empty($membersToReactivate)) {
			$this->logger->info("no member to reactivate on Slack");
		} else {
			$this->logger->info("there are " . count($membersToReactivate) . " members to reactivate on Slack");
			$body = "Il y a " . count($membersToReactivate) . " membres à réactiver sur Slack : \r\n"
				. "La liste est disponible via: " . $this->router->generate('open_api_server_default_apislackaccountstoreactivateget', [], UrlGeneratorInterface::ABSOLUTE_URL) . " (use curl)\r\n";

			$email = (new Email())
				->from($this->params->get('notification.fromEmail'))
				->to($this->params->get('notification.memberToReactivate.to'))
				->subject($this->params->get('notification.memberToReactivate.subject'))
				->text($body);

			if (!$debug) {
				$this->mailer->send($email);
				$this->logger->info("email sent");
			} else {
				$this->logger->info("email about members to reactivate not sent because we're in debug mode");
			}
		}
	}

	private function deleteOutdatedMembersIfNeeded(RegistrationDateUtil $dateUtil, \DateTime $lastSuccessfulRunStartDate, bool $debug): void {
		if ( !$dateUtil->needToDeleteOutdatedMembers($lastSuccessfulRunStartDate) ) {
			$this->logger->info("not the time to delete outdated members");
			return;
		}

		$upTo = $dateUtil->getMaxDateBeforeWhichRegistrationsInfoShouldBeDiscarded();
		$this->logger->info("We're going to delete outdated members with no registration after " . $upTo->format("Y-m-d"));
		$this->memberRepository->deleteMembersOlderThan($upTo, $debug);

		$membersToKeep = $this->memberRepository->findAll();
		// TODO: understand if we could get rid of this strtolower, and its impact if we keep it
		$emailsToKeep = array_map(function(Member $member) { return strtolower($member->getEmail()); }, $membersToKeep);
		$this->deleteOutdatedMembersFromGroup($this->googleConnector, $emailsToKeep, $debug);
		$this->deleteOutdatedMembersFromGroup($this->mailchimpConnector, $emailsToKeep, $debug);
	}

	private function deleteOutdatedMembersFromGroup(GroupWithDeletableUsers $group, array $emailsToKeep, bool $debug): void {
		$currentUsers = array_map(function(string $s) { return strtolower($s); }, $group->getUsers());
		$usersToDelete = array_diff($currentUsers, $emailsToKeep);
		$usersToDelete = array("toto1@tata.fr", "zozo@hotmail.fr");
		$this->logger->info("Going to delete ". count($usersToDelete) . " users from group " . $group->groupName());
		$group->deleteUsers($usersToDelete, $debug);
	}
}
