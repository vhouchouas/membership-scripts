<?php

namespace App\Repository;

use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, Member::class);
	}

	public function save(Member $entity, bool $flush = false): void
	{
		$this->getEntityManager()->persist($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function remove(Member $entity, bool $flush = false): void
	{
		$this->getEntityManager()->remove($entity);

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

	//    public function findOneBySomeField($value): ?Member
	//    {
	//        return $this->createQueryBuilder('m')
	//            ->andWhere('m.exampleField = :val')
	//            ->setParameter('val', $value)
	//            ->getQuery()
	//            ->getOneOrNullResult()
	//        ;
	//    }
}
