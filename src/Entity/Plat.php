<?php

namespace App\Entity;

use App\Repository\PlatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlatRepository::class)]
class Plat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prix = null;

    #[ORM\Column]
    private ?bool $disponible = null;

    // ====== خدمتك: panierItems ======
    /**
     * @var Collection<int, PanierItem>
     */
    #[ORM\OneToMany(targetEntity: PanierItem::class, mappedBy: 'plat')]
    private Collection $panierItems;

    public function __construct()
    {
        $this->panierItems = new ArrayCollection();
        $this->commandeItems = new ArrayCollection();
    }

    // ====== Wissal: image ======
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /**
     * @var Collection<int, CommandeItem>
     */
    #[ORM\OneToMany(targetEntity: CommandeItem::class, mappedBy: 'plat')]
    private Collection $commandeItems;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): static
    {
        $this->prix = $prix;
        return $this;
    }

    public function isDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): static
    {
        $this->disponible = $disponible;
        return $this;
    }

    // ====== panierItems getters & setters ======
    /**
     * @return Collection<int, PanierItem>
     */
    public function getPanierItems(): Collection
    {
        return $this->panierItems;
    }

    public function addPanierItem(PanierItem $panierItem): static
    {
        if (!$this->panierItems->contains($panierItem)) {
            $this->panierItems->add($panierItem);
            $panierItem->setPlat($this);
        }
        return $this;
    }

    public function removePanierItem(PanierItem $panierItem): static
    {
        if ($this->panierItems->removeElement($panierItem)) {
            if ($panierItem->getPlat() === $this) {
                $panierItem->setPlat(null);
            }
        }
        return $this;
    }

    // ====== image getter & setter ======
    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return Collection<int, CommandeItem>
     */
    public function getCommandeItems(): Collection
    {
        return $this->commandeItems;
    }

    public function addCommandeItem(CommandeItem $commandeItem): static
    {
        if (!$this->commandeItems->contains($commandeItem)) {
            $this->commandeItems->add($commandeItem);
            $commandeItem->setPlat($this);
        }

        return $this;
    }

    public function removeCommandeItem(CommandeItem $commandeItem): static
    {
        if ($this->commandeItems->removeElement($commandeItem)) {
            // set the owning side to null (unless already changed)
            if ($commandeItem->getPlat() === $this) {
                $commandeItem->setPlat(null);
            }
        }

        return $this;
    }
}
