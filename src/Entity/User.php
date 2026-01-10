<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Constantes pour les rôles
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_RESPONSABLE = 'ROLE_RESPONSABLE';
    public const ROLE_CHEF = 'ROLE_CHEF';
    public const ROLE_LIVREUR = 'ROLE_LIVREUR';
    public const ROLE_RECEPTIONNISTE = 'ROLE_RECEPTIONNISTE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = []; 

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $adresse = null;

    #[ORM\OneToMany(targetEntity: Panier::class, mappedBy: 'user')]
    private Collection $paniers;

    #[ORM\OneToMany(targetEntity: Commande::class, mappedBy: 'user')]
    private Collection $commandes;

    #[ORM\OneToMany(targetEntity: Commande::class, mappedBy: 'chef')]
    private Collection $commandesChef;

    #[ORM\OneToMany(targetEntity: Commande::class, mappedBy: 'livreur')]
    private Collection $commandesLivreur;

    public function __construct()
    {
        $this->paniers = new ArrayCollection();
        $this->commandes = new ArrayCollection();
        $this->commandesChef = new ArrayCollection();
        $this->commandesLivreur = new ArrayCollection();
        $this->roles = [self::ROLE_USER]; // rôle par défaut
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string)$this->email; }

    // 🔹 Retourne exactement le rôle de l'utilisateur
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): static
    {
        // s'assure qu'il y a exactement un rôle
        if (count($roles) > 1) {
            throw new \InvalidArgumentException('Un utilisateur ne peut avoir qu’un seul rôle.');
        }
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    #[\Deprecated]
    public function eraseCredentials(): void {}

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $adresse): static { $this->adresse = $adresse; return $this; }

    public function getPaniers(): Collection { return $this->paniers; }
    public function addPanier(Panier $panier): static { if (!$this->paniers->contains($panier)) { $this->paniers->add($panier); $panier->setUser($this); } return $this; }
    public function removePanier(Panier $panier): static { if ($this->paniers->removeElement($panier)) { if ($panier->getUser() === $this) { $panier->setUser(null); } } return $this; }

    public function getCommandes(): Collection { return $this->commandes; }
    public function addCommande(Commande $commande): static { if (!$this->commandes->contains($commande)) { $this->commandes->add($commande); $commande->setUser($this); } return $this; }
    public function removeCommande(Commande $commande): static { if ($this->commandes->removeElement($commande)) { if ($commande->getUser() === $this) { $commande->setUser(null); } } return $this; }

    public function getCommandesChef(): Collection { return $this->commandesChef; }
    public function addCommandesChef(Commande $commandesChef): static { if (!$this->commandesChef->contains($commandesChef)) { $this->commandesChef->add($commandesChef); $commandesChef->setChef($this); } return $this; }
    public function removeCommandesChef(Commande $commandesChef): static { if ($this->commandesChef->removeElement($commandesChef)) { if ($commandesChef->getChef() === $this) { $commandesChef->setChef(null); } } return $this; }

    public function getCommandesLivreur(): Collection { return $this->commandesLivreur; }
    public function addCommandesLivreur(Commande $commandesLivreur): static { if (!$this->commandesLivreur->contains($commandesLivreur)) { $this->commandesLivreur->add($commandesLivreur); $commandesLivreur->setLivreur($this); } return $this; }
    public function removeCommandesLivreur(Commande $commandesLivreur): static { if ($this->commandesLivreur->removeElement($commandesLivreur)) { if ($commandesLivreur->getLivreur() === $this) { $commandesLivreur->setLivreur(null); } } return $this; }
}
