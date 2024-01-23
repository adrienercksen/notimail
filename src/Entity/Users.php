<?php

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UsersRepository::class)]
class Users
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25)]
    private ?string $firm_name = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $first_name = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $last_name = null;

    #[ORM\Column(length: 50)]
    private ?string $email = null;

    #[ORM\Column(length: 25)]
    private ?string $phone_number = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $last_received_mail = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $last_picked_up = null;

    #[ORM\Column(nullable: true)]
    private ?bool $has_mail = null;

    #[ORM\Column(nullable: true)]
    private ?bool $is_admin = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirmName(): ?string
    {
        return $this->firm_name;
    }

    public function setFirmName(string $firm_name): static
    {
        $this->firm_name = $firm_name;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(?string $first_name): static
    {
        $this->first_name = $first_name;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(?string $last_name): static
    {
        $this->last_name = $last_name;

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

    public function getPhoneNumber(): ?string
    {
        return $this->phone_number;
    }

    public function setPhoneNumber(string $phone_number): static
    {
        $this->phone_number = $phone_number;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getLastReceivedMail(): ?\DateTimeInterface
    {
        return $this->last_received_mail;
    }

    public function setLastReceivedMail(?\DateTimeInterface $last_received_mail): static
    {
        $this->last_received_mail = $last_received_mail;

        return $this;
    }

    public function getLastPickedUp(): ?\DateTimeInterface
    {
        return $this->last_picked_up;
    }

    public function setLastPickedUp(\DateTimeInterface $last_picked_up): static
    {
        $this->last_picked_up = $last_picked_up;

        return $this;
    }

    public function isHasMail(): ?bool
    {
        return $this->has_mail;
    }

    public function setHasMail(?bool $has_mail): static
    {
        $this->has_mail = $has_mail;

        return $this;
    }

    public function isIsAdmin(): ?bool
    {
        return $this->is_admin;
    }

    public function setIsAdmin(?bool $is_admin): static
    {
        $this->is_admin = $is_admin;

        return $this;
    }
}
