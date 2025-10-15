<?php

namespace App\Entity;

use App\Repository\ProductVariantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Style;
use App\Entity\Genre;
    use Gedmo\Mapping\Annotation as Gedmo;
    use Symfony\Component\Serializer\Annotation\Groups;
    use App\Validator\UniqueSku;
    use Symfony\Component\HttpFoundation\File\File;
    use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
#[Vich\Uploadable]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['sku'], message: 'SKU already in use', ignoreNull: true)]
class ProductVariant
{
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
    #[Groups(['product_quick_view'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productVariants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'productVariants')]
    #[Groups(['product_quick_view'])]
    private ?Color $color = null;
    #[ORM\ManyToOne(targetEntity: Style::class)]
    #[Groups(['product_quick_view'])]
    private ?Style $style = null;

    #[ORM\ManyToOne(targetEntity: Genre::class)]
    #[Groups(['product_quick_view'])]
    private ?Genre $genre = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    #[Groups(['product_quick_view'])]
    #[UniqueSku]
    private ?string $sku = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['product_quick_view'])]
    #[Assert\NotBlank(message: 'Price is required')]
    private ?string $price = null;


    #[ORM\Column]
    #[Groups(['product_quick_view'])]
    #[Assert\NotBlank(message: 'Stock quantity is required')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Stock must be 0 or greater')]
    private ?int $stock = 0;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'productVariant', targetEntity: ProductVariantImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['product_quick_view'])]
    private Collection $productVariantImages;

    // Vich Uploadable Field for 3D/image overlay assets specific to this variant
    #[Vich\UploadableField(mapping: 'product_variant_overlay_files', fileNameProperty: 'overlayAsset')]
    #[Assert\File(
        mimeTypes: [
            // Image overlays
            'image/png', 'image/webp', 'image/jpeg',
            // 3D formats
            'model/gltf+json', 'model/gltf-binary', 'application/octet-stream', 'application/json', 'text/plain'
        ],
        mimeTypesMessage: 'Please upload a valid overlay file (.png, .webp, .jpg, .jpeg, .glb, .gltf, or .obj).'
    )]
    private ?File $overlayFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $overlayAsset = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'productVariant', targetEntity: OrderItem::class)]
    private Collection $orderItems;

    #[ORM\ManyToMany(targetEntity: ProductOffer::class, mappedBy: 'productVariants')]
    private Collection $productOffers;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;
    #[ORM\Column(length: 20)]
    private ?string $stockStatus = null;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateStockStatus(): void
    {
        $lowStockThreshold = 10;

        if ($this->stock === 0) {
            $this->stockStatus = 'Out of Stock';
        } elseif ($this->stock <= $lowStockThreshold) {
            $this->stockStatus = 'Low Stock';
        } else {
            $this->stockStatus = 'In Stock';
        }
    }
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


    public function getStock(): ?int
    {
        return $this->stock;
    }

   public function setStock(?int $stock): static
{
    $this->stock = $stock ?? 0; // Default to 0 if null
    $this->updateStockStatus(); // Update status when stock changes
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

    public function setOverlayFile(?File $file = null): void
    {
        $this->overlayFile = $file;
        if (null !== $file) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getOverlayFile(): ?File
    {
        return $this->overlayFile;
    }

    public function getOverlayAsset(): ?string
    {
        return $this->overlayAsset;
    }

    public function setOverlayAsset(?string $overlayAsset): self
    {
        $this->overlayAsset = $overlayAsset;
        return $this;
    }

    public function getStockStatus(): ?string
    {
        return $this->stockStatus;
    }

    public function setStockStatus(?string $stockStatus): static
    {
        $this->stockStatus = $stockStatus;
        return $this;
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
        $productName = $this->product ? $this->product->getName() : '[No Product]';
        $colorName = $this->color ? $this->color->getName() : '[No Color]';
    
        return sprintf('%s - %s', $productName, $colorName);
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }
}
