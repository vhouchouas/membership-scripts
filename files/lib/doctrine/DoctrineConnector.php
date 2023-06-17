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

if(!defined('ZWP_TOOLS')){  die(); }
require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'config.php');
require_once(__DIR__ . '/MemberDTO.php');

require_once ZWP_TOOLS . "vendor/autoload.php";
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

class DoctrineConnector {
	private bool $debug;
	private EntityManager $entityManager;

	public function __construct(bool $debug=true) {
		$this->debug = $debug;
		$this->entityManager = $this->getEntityManager();
	}

	public static function getEntityManager(): EntityManager {
		$config = ORMSetup::createAttributeMetadataConfiguration(
				paths: array(__DIR__),
				isDevMode: true,
				);

		$connectionParams = DOCTRINE_USE_MYSQL ? [
			'driver' => 'pdo_mysql',
			'dbname' => 'DB_NAME',
			'user' => 'DB_USER',
			'password' => 'DB_PASSWORD',
			'host' => 'DB_HOST',
		] : [
			'driver' => 'pdo_sqlite',
		'path' => __DIR__ . '/db.sqlite',
		];

		global $loggerInstance;
		$loggerInstance->log_info("Using driver " . $connectionParams['driver'] . " for doctrine");

		$conn = DriverManager::getConnection($connectionParams);

		return new EntityManager($conn, $config);
	}

	public function addOrUpdateMember(RegistrationEvent $event) {
		global $loggerInstance;
		$loggerInstance->log_info("Going to register in db user " . $event->first_name . " " . $event->last_name);

		$member = $this->getMemberMatchingRegistration($event);

		if ($member != null) {
			$loggerInstance->log_info("Member already known. We update it");
		} else {
			$loggerInstance->log_info("Member unknown, we create it");
			$member = new MemberDTO();
			$member->firstName = $event->first_name;
			$member->lastName = $event->last_name;
			$member->firstRegistrationDate = new DateTime($event->event_date);
		}
		$this->fillMemberWithFieldsCommonForCreateAndUpdate($member, $event);

		if ($this->debug) {
			$loggerInstance->log_info("Non persisting this member in db because we're in debug mode");
		} else {
			$this->entityManager->persist($member);
			$this->entityManager->flush();
			$loggerInstance->log_info("Member successfully persisted in db");
		}
	}

	private function fillMemberWithFieldsCommonForCreateAndUpdate(MemberDTO $member, RegistrationEvent $event) {
		$member->email = $event->email;
		$member->postalCode = $event->postal_code;
		$member->helloAssoLastRegistrationEventId = $event->helloasso_event_id;
		$member->city = $event->city;
		$member->howDidYouKnowZwp = $event->how_did_you_know_zwp;
		$member->wantToDo = $event->want_to_do;
		$member->lastRegistrationDate = new DateTime($event->event_date);
		$member->isZWProfessional = $event->is_zw_professional == "Oui";

	}

	public function getOrderedListOfLastRegistrations(DateTime $since) : array {
		$query = $this->entityManager->createQuery(
			'SELECT m
			FROM MemberDTO m
			WHERE m.lastRegistrationDate > :since
			ORDER BY m.lastRegistrationDate ASC'
		)->setParameter('since', $since);

		return $query->getResult();
	}

	public function getListOfRegistrationsOlderThan(DateTime $upTo) : array {
		$query = $this->entityManager->createQuery(
			'SELECT m
			FROM MemberDTO m
			WHERE m.lastRegistrationDate < :upTo
			ORDER BY m.lastRegistrationDate ASC'
		)->setParameter('upTo', $upTo);

		return $query->getResult();
	}

	public function deleteRegistrationsOlderThan(DateTime $upTo) {
		foreach ($this->getListOfRegistrationsOlderThan($upTo) as $member) {
			if (!$this->debug) {
				$this->entityManager->remove($member);
			}
		}
		$this->entityManager->flush();
	}

	public function getMemberMatchingRegistration(RegistrationEvent $event) : ?MemberDTO {
		return $this->entityManager->getRepository('MemberDTO')->findOneBy(['firstName' => $event->first_name, 'lastName' => $event->last_name]);
	}

  /**
   * This method is supposed to be called with the emails of the last members who registered, and it
   * returns information about those of them who were members in the past and who have been deactivated.
   * Currently it's used to send a notification to admins, because the accounts of returning members need
   * to be manually reactivated on some of our tools.
   * @param string[] $membersEmail A list of mail of people who just registered
   * @param DateTime $registeredBefore The date after which we expect users haven't registered
   * @return SimplifiedRegistrationEvent[] data about members in $membersEmail who already registered
   *                                       but who never registered after $registeredBefore
   */
	public function findMembersInArrayWhoDoNotRegisteredAfterGivenDate(array $membersEmail, DateTime $registeredBefore) : array {
    if ( count($membersEmail) == 0 ){
      return array();
    }

		$query = $this->entityManager->createQuery(
			'SELECT m
			FROM MemberDTO m
			WHERE m.lastRegistrationDate < :upTo
			 AND m.email IN (:emails)'
		)->setParameter('upTo', $registeredBefore)
		->setParameter('emails', $membersEmail);

		return $query->getResult();
	}

	public function getMembersForWhichNoNotificationHasBeenSentToAdmins() : array {
		return $this->entityManager->getRepository('MemberDTO')->findBy(['notificationSentToAdmin' => false]);
	}

	public function updateMembersForWhichNotificationHasBeenSentoToAdmins(array $members) : void {
		foreach($members as $member) {
			$member->notificationSentToAdmin = true;
			if (!$this->debug) {
				$this->entityManager->persist($member);
			}
		}
		$this->entityManager->flush();
	}
}
