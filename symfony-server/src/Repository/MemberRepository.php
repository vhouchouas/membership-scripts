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

namespace App\Repository;

use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Models\RegistrationEvent;
use Psr\Log\LoggerInterface;
use App\Services\RegistrationDateUtil;

/**
 * @extends ServiceEntityRepository<Member>
 *
 * @method Member|null find($id, $lockMode = null, $lockVersion = null)
 * @method Member|null findOneBy(array $criteria, array $orderBy = null)
 * @method Member[]    findAll()
 * @method Member[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MemberRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry, private LoggerInterface $logger, private RegistrationDateUtil $dateUtil)
	{
		parent::__construct($registry, Member::class);
	}

	public function addOrUpdateMember(RegistrationEvent $event, bool $debug): void {
		$this->logger->info("Going to register in db user " . $event->first_name . " " . $event->last_name . ": " . $event->email);

		$member = $this->findOneBy(['email' => $event->email]);
		$eventDateTime = new \DateTime($event->event_date);

		if ($member != null) {
			if ($member->getLastRegistrationDate() < $eventDateTime) {
				$this->logger->info("Member already known, from a previous registration. We update it.");
				$this->fillMemberWithFieldsCommonForCreateAndUpdate($member, $event);
			} else {
				if ($member->getFirstRegistrationDate() > $eventDateTime) {
					$this->logger->info("Member already known from a more recent registration. We update date of first registration");
					$member->setFirstRegistrationDate($eventDateTime);
				} else {
					$this->logger->info("Member already known from both a more recent and an older registration. Nothing to do");
				}
			}
		} else {
			$this->logger->info("Member unknown, we create it");
			$member = new Member();
			$member->setFirstName($event->first_name);
			$member->setLastName($event->last_name);
			$member->setFirstRegistrationDate($eventDateTime);
			$member->setNotificationSentToAdmin(false);
			$this->fillMemberWithFieldsCommonForCreateAndUpdate($member, $event);
		}

		if ($debug) {
			$this->logger->info("Not persisting this member in db because we're in debug mode");
		} else {
			$this->save($member, true);
			$this->logger->info("Member successfully persisted in db");
		}
	}

	private function fillMemberWithFieldsCommonForCreateAndUpdate(Member $member, RegistrationEvent $event) {
		$member->setEmail($event->email);
		$member->setPostalCode($event->postal_code);
		$member->setHelloAssoLastRegistrationEventId($event->helloasso_event_id);
		$member->setCity($event->city);
		$member->setHowDidYouKnowZwp($event->how_did_you_know_zwp);
		$member->setWantToDo($event->want_to_do);
		$member->setLastRegistrationDate(new \DateTime($event->event_date));
		$member->setIsZWProfessional($event->is_zw_professional == "Oui");
	}

	public function getMembersForWhichNoNotificationHasBeenSentToAdmins(): array {
		return $this->createQueryBuilder('m')
			->andWhere('m.notificationSentToAdmin = false')
			->getQuery()
			->getResult();
	}

	public function updateMembersForWhichNotificationHasBeenSentoToAdmins(array $members, bool $debug) : void {
		foreach($members as $member) {
			$member->setNotificationSentToAdmin(true);
		}

		if ($debug) {
			$this->logger->info("We're in debug mode so we don't update anything");
		} else {
			$this->getEntityManager()->flush();
		}
	}

	public function save(Member $entity, bool $flush = false): void
	{
		$this->getEntityManager()->persist($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function getOrderedListOfLastRegistrations(\DateTime $since) : array {
		return $this->createQueryBuilder('m')
			->andWhere('m.lastRegistrationDate > :since')
			->setParameter('since', $since)
			->orderBy('m.lastRegistrationDate', 'ASC')
			->getQuery()
			->getResult();
	}

	// TODO: remove code duplicated with getOrderedListOfLastRegistrations
	public function getListOfLastRegistrations(\DateTime $since): array {
		return $this->createQueryBuilder('m')
			->andWhere('m.lastRegistrationDate > :since')
			->setParameter('since', $since)
			->getQuery()
			->getResult();
	}

	public function getAllUpToDateMembers(): array {
		return $this->getListOfLastRegistrations($this->dateUtil->getDateAfterWhichMembershipIsConsideredValid());
	}

	public function getMembersPerPostalCode(\DateTime $since) : array {
		return $this->getEntityManager()->createQuery(
				'SELECT m.postalCode, COUNT(m.postalCode) AS count
				 FROM \App\Entity\Member m
				 WHERE m.lastRegistrationDate > :since
				 GROUP BY m.postalCode
				 ORDER BY count DESC')
			->setParameter('since', $since)
			->getResult();
	}

	public function getListOfRegistrationsOlderThan(\DateTime $upTo) : array {
		return $this->createQueryBuilder('m')
			->andWhere('m.lastRegistrationDate < :upTo')
			->setParameter('upTo', $upTo)
			->getQuery()
			->getResult();
	}

	public function deleteMembersOlderThan(\DateTime $upTo, bool $debug) : void {
		foreach ($this->getListOfRegistrationsOlderThan($upTo) as $member) {
			if ($debug) {
				$this->logger->info("Would delete {$member->getEmail()} bu we're in debug mode.");
			} else {
				$this->getEntityManager()->remove($member);
				$this->logger->info("About to delete old member from db: {$member->getEmail()}");
			}
		}
		$this->getEntityManager()->flush();
	}

	/**
	 * @return TRUE if we could delete the member, FALSE if the member did not exist already
	 */
	public function deleteMember(string $primaryEmail): bool {
		$member = $this->findOneBy(['email' => $primaryEmail]);
		if ($member) {
			$this->getEntityManager()->remove($member);
			$this->getEntityManager()->flush();
			return true;
		} else {
			return false;
		}
	}
}
