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

use App\Entity\Options;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<Options>
 *
 * @method Options|null find($id, $lockMode = null, $lockVersion = null)
 * @method Options|null findOneBy(array $criteria, array $orderBy = null)
 * @method Options[]    findAll()
 * @method Options[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OptionsRepository extends ServiceEntityRepository
{
	const OPTION_LASTSUCCESSFULRUN_NAME = "last_successful_run_date";

	public function __construct(ManagerRegistry $registry, private LoggerInterface $logger)
	{
		parent::__construct($registry, Options::class);
	}

	public function save(Options $entity, bool $flush = false): void
	{
		$this->getEntityManager()->persist($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function getLastSuccessfulRunDate(): \DateTime {
		$option = $this->getLastSuccessfulRunDateOption();
		if ($option == null) {
			$error = "Can't retrieve the last succesful run start date, we abort";
			$this->logger->critical($error);
			throw new \Exception($error);
		}
		return unserialize($option->getValue());
	}

	private function getLastSuccessfulRunDateOption(): ?Options {
		return $this->findOneBy(['name' => self::OPTION_LASTSUCCESSFULRUN_NAME]);
	}

	public function hasLastSuccessfulRunDate(): bool {
		return $this->getLastSuccessfulRunDateOption() !== null;
	}

	public function writeLastSuccessfulRunDate(\DateTime $startDate, bool $debug) {
		$option = $this->getLastSuccessfulRunDateOption();
		if ($option == null) {
			$option = new Options();
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
}
