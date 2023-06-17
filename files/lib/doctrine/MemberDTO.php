<?php

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'members')]
class MemberDTO {
	#[ORM\Id]
	#[ORM\Column]
	#[ORM\GeneratedValue]
	private int|null $id = null;

	public function getId(): int|null {
		return $this->id;
	}

	#[ORM\Column]
	public string $firstName;

	#[ORM\Column]
	public string $lastName;

	#[ORM\Column(unique: true)]
	public string $email;

	// Nothing enforces a correct format for postal code on the registration and we sometimes have unexpected strings
	// eg: P75016. So we give a length a bit bigger than the 5 which should be enough, just in case
	#[ORM\Column(nullable: true, length: 10)]
	public ?string $postalCode;

	#[ORM\Column(unique: true)]
	public int $helloAssoLastRegistrationEventId;

	#[ORM\Column(nullable: true)]
	public ?string $city;

	#[ORM\Column(length: 1000, nullable: true)]
	public ?string $howDidYouKnowZwp;

	#[ORM\Column(length: 1000, nullable: true)]
	public ?string $wantToDo;

	#[ORM\Column]
	public DateTime $firstRegistrationDate;

	#[ORM\Column]
	public DateTime $lastRegistrationDate;

	#[ORM\Column]
	public bool $isZWProfessional;

	#[ORM\Column]
	public bool $notificationSentToAdmin = false;
}
