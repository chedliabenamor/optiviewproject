<?php

namespace App\Entity;

use App\Repository\ProductVariantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use App\Entity\Style;
use App\Entity\Genre;

#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['sku'], message: 'This SKU already exists.')]
class ProductVariant{
    /**
     * Returns the image URL of the first ProductVariantImage, or null if none exist.
     */
    public function getFirstImageUrl(): ?string
    {
        $firstImage = $this->productVariantImages->first();
        if ($firstImage && method_exists($firstImage, 'getImageUrl')) {
            return $firstImage->getImageUrl();
        }
        return null;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productVariants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'productVariants')]
    private ?Color $color = null;

    #[ORM\ManyToOne(targetEntity: Style::class)]
    private ?Style $style = null;

    #[ORM\ManyToOne(targetEntity: Genre::class)]
    private ?Genre $genre = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(length: 255)]
    private ?string $currency = 'EUR';

    #[ORM\Column]
    private ?int $stock = 0;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'productVariant', targetEntity: ProductVariantImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $productVariantImages;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'productVariant', targetEntity: OrderItem::class)]
    private Collection $orderItems;

    #[ORM\ManyToMany(targetEntity: ProductOffer::class, mappedBy: 'productVariants')]
    private Collection $productOffers;

    public function __construct()
    {
        $this->productVariantImages = new ArrayCollection();
        $this->orderItems = new ArrayCollection();
        $this->productOffers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getColor(): ?Color
    {
        return $this->color;
    }

    public function setColor(?Color $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getStyle(): ?Style
    {
        return $this->style;
    }

    public function setStyle(?Style $style): static
    {
        $this->style = $style;

        return $this;
    }

    public function getGenre(): ?Genre
    {
        return $this->genre;
    }

    public function setGenre(?Genre $genre): static
    {
        $this->genre = $genre;

        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, ProductVariantImage>
     */
    public function getProductVariantImages(): Collection
    {
        return $this->productVariantImages;
    }

    public function addProductVariantImage(ProductVariantImage $productVariantImage): static
    {
        if (!$this->productVariantImages->contains($productVariantImage)) {
            $this->productVariantImages->add($productVariantImage);
            $productVariantImage->setProductVariant($this);
        }

        return $this;
    }

    public function removeProductVariantImage(ProductVariantImage $productVariantImage): static
    {
        if ($this->productVariantImages->removeElement($productVariantImage)) {
            // set the owning side to null (unless already changed)
            if ($productVariantImage->getProductVariant() === $this) {
                $productVariantImage->setProductVariant(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, ProductOffer>
     */
    public function getProductOffers(): Collection
    {
        return $this->productOffers;
    }

    public function addProductOffer(ProductOffer $productOffer): static
    {
        if (!$this->productOffers->contains($productOffer)) {
            $this->productOffers->add($productOffer);
            $productOffer->addProductVariant($this);
        }

        return $this;
    }

    public function removeProductOffer(ProductOffer $productOffer): static
    {
        if ($this->productOffers->removeElement($productOffer)) {
            $productOffer->removeProductVariant($this);
        }

        return $this;
    }



    public function __toString(): string
    {
        return $this->getProduct()?->getName() . ' - ' . ($this->getColor()?->getName() ?? '') . ($this->getSku() ? ' (' . $this->getSku() . ')' : '');
    }
}
