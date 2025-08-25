<?php

namespace App\Entity;

use App\Repository\WishlistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WishlistRepository::class)]
class Wishlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'wishlist', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'wishlist', targetEntity: WishlistItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $wishlistItems;

    public function __construct()
    {
        $this->wishlistItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, WishlistItem>
     */
    public function getWishlistItems(): Collection
    {
        return $this->wishlistItems;
    }

    public function addWishlistItem(WishlistItem $wishlistItem): static
    {
        if (!$this->wishlistItems->contains($wishlistItem)) {
            $this->wishlistItems->add($wishlistItem);
            $wishlistItem->setWishlist($this);
        }
        return $this;
    }

    public function removeWishlistItem(WishlistItem $wishlistItem): static
    {
        if ($this->wishlistItems->removeElement($wishlistItem)) {
            if ($wishlistItem->getWishlist() === $this) {
                $wishlistItem->setWishlist(null);
            }
        }
        return $this;
    }

    public function hasProduct(Product $product, ?ProductVariant $variant = null): bool
    {
        foreach ($this->wishlistItems as $item) {
            if ($item->getProduct() === $product) {
                if ($variant === null && $item->getProductVariant() === null) {
                    return true;
                } elseif ($variant !== null && $item->getProductVariant() === $variant) {
                    return true;
                }
            }
        }
        return false;
    }

    public function __toString(): string
    {
        return sprintf('Wishlist (ID: %d) - %d items', $this->getId(), $this->getWishlistItems()->count());
    }
}
