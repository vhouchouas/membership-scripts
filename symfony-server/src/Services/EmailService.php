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

use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class EmailService {
	public function __construct(
		private SlackService $slack,
		private MailerInterface $mailer,
		private ContainerBagInterface $params,
		private LoggerInterface $logger,
		private UrlGeneratorInterface $router,
		private RequestStack $requestStack,
	) {}

	public function sendNotificationForAdminsAboutNewcomers(array $newcomers, bool $debug): void {
		$body = "";
		if (empty($newcomers)) {
			$body = "Oh non, il n'y a pas eu de nouveaux membres cette semaine ! :(";
		} else {
			$body = "Voici les " . count($newcomers) . " membres qui ont rejoint l'asso cette semaine.\r\n";
			foreach($newcomers as $newcomer) {
				$body .= "\r\n";
				$body .= "Un ou une membre \r\n";
				$body .= "Adhésion le " . $this->dateToStr($newcomer->getLastRegistrationDate()) . "\r\n";
				$body .= "Réside à : " . $newcomer->getcity() . " (" . $newcomer->getPostalCode() . ")\r\n";
				$body .= "A connu l'asso : " . $newcomer->getHowDidYouKnowZwp() . "\r\n";
				$body .= "Iel est motivé par : " . $newcomer->getWantToDo() . "\r\n";
			}

			$body .= "\r\n";
			$currentRequest = $this->requestStack->getCurrentRequest();
			if ($currentRequest !== null) { // TODO: setup a mocked request in test (instead of checking for null here) once https://github.com/symfony/symfony/issues/51595 is fixed
				$body .= "Plus d'infos sur " . $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() . "\r\n";
				$body .= "\r\n";
			}
			$body .= "Il y a un projet en cours qui leur correspond ? Un GT qui recherche de nouveaux membres ? C’est le moment de leur dire et/ou d’en parler à un.e référent.e ! ";
		}

		$email = (new Email())
			->from($this->params->get('notification.fromEmail'))
			->to($this->params->get('notification.newcomersEmail.to'))
			->subject($this->params->get('notification.newcomersEmail.subject'))
			->text($body);

		if (!$debug) {
			$this->mailer->send($email);
			$this->logger->info("going to send the email");
		} else {
			$this->logger->info("email about newcomers not sent because we're in debug mode");
		}
	}

	public function sendEmailAboutSlackMembersToReactivate($debug): void {
		$membersToReactivate = $this->slack->findDeactivatedMembers();
		if (empty($membersToReactivate)) {
			$this->logger->info("no member to reactivate on Slack");
		} else {
			$this->logger->info("there are " . count($membersToReactivate) . " members to reactivate on Slack");
			$body = "Il y a " . count($membersToReactivate) . " membres à réactiver sur Slack : \r\n"
				. "La liste est disponible via: " . $this->router->generate('open_api_server_default_apislackaccountstoreactivateget', [], UrlGeneratorInterface::ABSOLUTE_URL) . "\r\n";

			$email = (new Email())
				->from($this->params->get('notification.fromEmail'))
				->to($this->params->get('notification.memberToReactivate.to'))
				->subject($this->params->get('notification.memberToReactivate.subject'))
				->text($body);

			if (!$debug) {
				$this->logger->info("going to send the email");
				$this->mailer->send($email);
				$this->logger->info("email sent");
			} else {
				$this->logger->info("email about members to reactivate not sent because we're in debug mode");
			}
		}
	}

	private function dateToStr(\DateTime $d) : string {
		return $d->format('Y-m-d\TH:i:s');
	}
}
