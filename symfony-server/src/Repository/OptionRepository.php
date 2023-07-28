<?php

namespace App\Repository;

use App\Entity\Option;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<Option>
 *
 * @method Option|null find($id, $lockMode = null, $lockVersion = null)
 * @method Option|null findOneBy(array $criteria, array $orderBy = null)
 * @method Option[]    findAll()
 * @method Option[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OptionRepository extends ServiceEntityRepository
{
	const OPTION_LASTSUCCESSFULRUN_NAME = "last_successful_run_date";

	public function __construct(ManagerRegistry $registry, private LoggerInterface $logger)
	{
		parent::__construct($registry, Option::class);
	}

	public function save(Option $entity, bool $flush = false): void
	{
		$this->getEntityManager()->persist($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function remove(Option $entity, bool $flush = false): void
	{
		$this->getEntityManager()->remove($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function getLastSuccessfulRunDate(): \DateTime {
		$option = $this->getLastSuccessfulRunDateOption();
		if ($option == null) {
			$error = "Can't retrieve the last succesful run start date, we abort";
			$this->logger->critical($error);
			throw new Exception($error);
		}
		return unserialize($option->getValue());
	}

	private function getLastSuccessfulRunDateOption(): ?Option {
		return $this->findOneBy(['name' => self::OPTION_LASTSUCCESSFULRUN_NAME]);
	}

	public function hasLastSuccessfulRunDate(): bool {
		return $this->getLastSuccessfulRunDateOption() !== null;
	}

	public function writeLastSuccessfulRunDate(\DateTime $startDate, bool $debug) {
		$option = $this->getLastSuccessfulRunDateOption();
		if ($option == null) {
			$option = new Option();
            $option->setName(self::OPTION_LASTSUCCESSFULRUN_NAME);
		}

		if ($debug) {
			$this->logger->info("Not updating start date in db because we're in debug mode");
		} else {
			$option->setValue(serialize($startDate));
			$this->save($option, true);
			$this->logger->info("Start date successfully persisted in db");
		}
	}


//    /**
//     * @return Option[] Returns an array of Option objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('o.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Option
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
