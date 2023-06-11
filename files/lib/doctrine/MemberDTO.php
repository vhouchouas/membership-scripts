<?php

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'members')]
class MemberDTO {
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private int|null $id = null;

	public function getId(): int|null {
		return $this->id;
	}

	#[ORM\Column(type: 'string')]
	private string $firstName;
	public function setFirstName(string $firstName) {
		$this->firstName = $firstName;
	}
	public function getFirstName() : string {
		return $this->firstName;
	}

	#[ORM\Column(type: 'string')]
	private string $lastName;
	public function setlastName(string $lstName) {
		$this->lastName = $lastName;
	}
	public function getlastName() : string {
		return $this->lastName;
	}

	#[ORM\Column(type: 'string')]
	private string $email;
	public function setemail(string $email) {
		$this->email = $email;
	}
	public function getEmail() : string {
		return $this->email;
	}

	#[ORM\Column(type: 'string')]
	private string $postalCode;
	public function setpostalCode(string $postalCode) {
		$this->postalCode = $postalCode;
	}
	public function getPostalCode() : string {
		return $this->postalCode;
	}

	// TODO: helloAssoLastRegistreationEventId
	// TODO: firstRegistrtionDate
	// TODO: lastRegistrationDate
	// TODO: city
	// TODO: isZWProfessional
	// TODO: howDidYouKnowZwp
	// TODO: wantToDo
}
