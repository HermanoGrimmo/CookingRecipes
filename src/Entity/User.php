<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Repräsentiert einen registrierten Benutzer der Anwendung.
 *
 * Tabellenname 'app_user' statt 'user', da 'user' in PostgreSQL ein reserviertes Schlüsselwort ist.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['email'], message: 'Diese E-Mail-Adresse ist bereits registriert.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** E-Mail-Adresse dient als Login-Bezeichner */
    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    /** Vorname des Benutzers */
    #[ORM\Column(length: 100)]
    private string $firstName = '';

    /** Nachname des Benutzers */
    #[ORM\Column(length: 100)]
    private string $lastName = '';

    /**
     * Rollen des Benutzers (z. B. ROLE_USER, ROLE_ADMIN).
     * ROLE_USER wird immer automatisch hinzugefügt.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    /** Gehashtes Passwort – niemals Klartext speichern */
    #[ORM\Column]
    private string $password = '';

    /** Erstellungsdatum des Kontos */
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Recipe> */
    #[ORM\OneToMany(targetEntity: Recipe::class, mappedBy: 'owner')]
    private Collection $recipes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->recipes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Gibt den eindeutigen Bezeichner zurück, der für die Authentifizierung verwendet wird.
     * Symfony nutzt diesen Wert intern für die Session-Verwaltung.
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email);

        return $this->email;
    }

    /**
     * Gibt alle Rollen des Benutzers zurück.
     * ROLE_USER wird immer erzwungen, auch wenn das roles-Array leer ist.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * Setzt die Rollen des Benutzers.
     *
     * @param array<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values($roles);

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Entfernt sensible Daten aus dem Benutzerobjekt.
     * Nicht benötigt, da kein Klartext-Passwort gespeichert wird.
     */
    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    /** Gibt den vollständigen Namen (Vorname + Nachname) zurück. */
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Recipe> */
    public function getRecipes(): Collection
    {
        return $this->recipes;
    }
}
