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

namespace App\Entity;

use App\Repository\MemberRepository;
use App\Repository\MemberAdditionalEmailRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
class Member
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255)]
	private ?string $firstName = null;

	#[ORM\Column(length: 255)]
	private ?string $lastName = null;

	#[ORM\Column(length: 255, unique: true)]
	private ?string $email = null;

	#[ORM\Column(length: 10, nullable: true)]
	private ?string $postalCode = null;

	#[ORM\Column]
	private ?int $helloAssoLastRegistrationEventId = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $city = null;

	#[ORM\Column(length: 1000, nullable: true)]
	private ?string $howDidYouKnowZwp = null;

	#[ORM\Column(length: 1000, nullable: true)]
	private ?string $wantToDo = null;

	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	private ?\DateTimeInterface $firstRegistrationDate = null;

	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	private ?\DateTimeInterface $lastRegistrationDate = null;

	#[ORM\Column]
	private ?bool $isZWProfessional = null;

	#[ORM\Column]
	private ?bool $notificationSentToAdmin = null;

	/** @var ArrayCollection<int, MemberAdditionalEmail> */
	#[ORM\OneToMany(targetEntity: MemberAdditionalEmail::class, mappedBy: 'member', cascade: ['ALL'])]
	private ?Collection $additionalEmails = null;

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getFirstName(): ?string
	{
		return $this->firstName;
	}

	public function setFirstName(string $firstName): static
	{
		$this->firstName = $firstName;

		return $this;
	}

	public function getLastName(): ?string
	{
		return $this->lastName;
	}

	public function setLastName(string $lastName): static
	{
		$this->lastName = $lastName;

		return $this;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function setEmail(string $email): static
	{
		$this->email = $email;

		return $this;
	}

	public function getPostalCode(): ?string
	{
		return $this->postalCode;
	}

	public function setPostalCode(string $postalCode): static
	{
		$this->postalCode = $postalCode;

		return $this;
	}

	public function getHelloAssoLastRegistrationEventId(): ?int
	{
		return $this->helloAssoLastRegistrationEventId;
	}

	public function setHelloAssoLastRegistrationEventId(int $helloAssoLastRegistrationEventId): static
	{
		$this->helloAssoLastRegistrationEventId = $helloAssoLastRegistrationEventId;

		return $this;
	}

	public function getCity(): ?string
	{
		return $this->city;
	}

	public function setCity(?string $city): static
	{
		$this->city = $city;

		return $this;
	}

	public function getHowDidYouKnowZwp(): ?string
	{
		return $this->howDidYouKnowZwp;
	}

	public function setHowDidYouKnowZwp(?string $howDidYouKnowZwp): static
	{
		$this->howDidYouKnowZwp = $howDidYouKnowZwp;

		return $this;
	}

	public function getWantToDo(): ?string
	{
		return $this->wantToDo;
	}

	public function setWantToDo(?string $wantToDo): static
	{
		$this->wantToDo = $wantToDo;

		return $this;
	}

	public function getFirstRegistrationDate(): ?\DateTimeInterface
	{
		return $this->firstRegistrationDate;
	}

	public function setFirstRegistrationDate(\DateTimeInterface $firstRegistrationDate): static
	{
		$this->firstRegistrationDate = $firstRegistrationDate;

		return $this;
	}

	public function getLastRegistrationDate(): ?\DateTimeInterface
	{
		return $this->lastRegistrationDate;
	}

	public function setLastRegistrationDate(\DateTimeInterface $lastRegistrationDate): static
	{
		$this->lastRegistrationDate = $lastRegistrationDate;

		return $this;
	}

	public function isIsZWProfessional(): ?bool
	{
		return $this->isZWProfessional;
	}

	public function setIsZWProfessional(bool $isZWProfessional): static
	{
		$this->isZWProfessional = $isZWProfessional;

		return $this;
	}

	public function isNotificationSentToAdmin(): ?bool
	{
		return $this->notificationSentToAdmin;
	}

	public function setNotificationSentToAdmin(bool $notificationSentToAdmin): static
	{
		$this->notificationSentToAdmin = $notificationSentToAdmin;

		return $this;
	}

		/**
		 * @return Collection<MemberAdditionalEmail>
		 */
	private function getRawAdditionalEmails(): Collection
	{
		if ($this->additionalEmails === null) {
			$this->additionalEmails = new ArrayCollection();
		}
		return $this->additionalEmails;
	}

		/**
		 * @return array<string>
		 */
		public function getAdditionalEmails(): array {
			$ret = array();
			foreach($this->getRawAdditionalEmails() as $additionalEmail) {
				$ret []= $additionalEmail->getEmail();
			}
			return $ret;
		}

	/**
	 * @return TRUE if the email could be successfully added, FALSE if it already existed
	 */
	public function addAdditionalEmail(string $additionalEmail): bool
	{
	  if ($this->hasAdditionalEmail($additionalEmail)) {
		return false;
	  }

	  $this->getRawAdditionalEmails()->add(new MemberAdditionalEmail($this, $additionalEmail));
	  return true;
	}

	/**
	 * @return TRUE if the email could be successfully removed, FALSE if it was already not associated to this member
	 */
	public function rmAdditionalEmail(string $additionalEmail, MemberAdditionalEmailRepository $repository): bool {
			if ($this->additionalEmails === null) {
				return false;
			}
			for ($idx=0 ; $idx < count($this->additionalEmails) ; $idx++) {
				if ($this->additionalEmails->get($idx)->getEmail() === $additionalEmail) {
					$repository->remove($this->additionalEmails->get($idx));
					$this->additionalEmails->remove($idx);
					return true;
				}
			}
			return false;
		}

	public function hasAdditionalEmail(string $additionalEmail, bool $count=false): bool
	{
			foreach($this->getRawAdditionalEmails() as $rawAdditionalEmail) {
				if ($rawAdditionalEmail->getEmail() === $additionalEmail) {
					return true;
				}
			}
			return false;
	}

	/**
	 * @returns the primary email + the additional emails (if any)
	 */
	public function getAllEmails(): array {
		return array_merge(array($this->email), $this->getAdditionalEmails());
	}
}
