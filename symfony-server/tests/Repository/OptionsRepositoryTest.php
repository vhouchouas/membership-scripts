<?php
declare(strict_types=1);

use App\Repository\OptionsRepository;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OptionsRepositoryTest extends KernelTestCase {
	private OptionsRepository $optionRepository;


	protected function setUp(): void {
		self::bootKernel();
		$container = static::getContainer();
		$this->optionRepository = $container->get(OptionsRepository::class);
	}

	public function test_writeAndReadStartDate() {
		// Test date creation
		$date1 = new DateTime("1985-04-03T11:53:17");
		$this->optionRepository->writeLastSuccessfulRunDate($date1, false);

		$this->assertEquals($date1, $this->optionRepository->getLastSuccessfulRunDate());

		// Test updating existing option
		$date2 = new DateTime("1987-11-08T12:34:56");
		$this->optionRepository->writeLastSuccessfulRunDate($date2, false);

		$this->assertEquals($date2, $this->optionRepository->getLastSuccessfulRunDate());

		// Test debug mode
		$date3 = new DateTime("2020-09-08T06:55:47");
		$this->optionRepository->writeLastSuccessfulRunDate($date3, true);
		$this->assertEquals($date2, $this->optionRepository->getLastSuccessfulRunDate(), "We should retrieve the previous value since the last write was in debug mode");
	}
}
