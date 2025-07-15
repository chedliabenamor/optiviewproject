<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use App\Entity\ProductVariant;
use App\Entity\Color;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
#[Gedmo\SoftDeleteable(fieldName: "deletedAt", timeAware: false)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $price = null;

    #[ORM\Column(name: "stock", type: Types::INTEGER)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?int $quantityInStock = null;

    #[ORM\Column(type: Types::INTEGER, options: ["default" => 0])]
    private int $loyaltyPoints = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null; // Main product image (Consider if this is still needed or can be removed)

    #[ORM\Column(length: 255)]
    private ?string $currency = 'EUR';

    // Vich Uploadable Field for the overview image file
    #[Vich\UploadableField(mapping: 'product_overview_images', fileNameProperty: 'overviewImage')]
    private ?File $overviewImageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $overviewImage = null; // Stores the filename of the overview image


    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)] // Allow products without a brand initially
    private ?Brand $brand = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Style $style = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Shape $shape = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Genre $genre = null;

    #[ORM\ManyToMany(targetEntity: Color::class, inversedBy: 'products')]
    private Collection $colors;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductModelImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $productModelImages;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Review::class, orphanRemoval: true)]
    private Collection $reviews;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\ManyToMany(targetEntity: ProductOffer::class, mappedBy: 'products')]
    private Collection $productOffers;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: CartItem::class, orphanRemoval: true)]
    private Collection $cartItems;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductVariant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $productVariants;
    #[ORM\Column(length: 20)]
    private ?string $stockStatus = null;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateStockStatus(): void
    {
        $lowStockThreshold = 10;

        // Calculate based on total stock (sum of variants + product's own stock if applicable)
        $totalStock = $this->quantityInStock;
        foreach ($this->productVariants as $variant) {
            $totalStock += $variant->getStock();
        }

        if ($totalStock === 0) {
            $this->stockStatus = 'Out of Stock';
        } elseif ($totalStock <= $lowStockThreshold) {
            $this->stockStatus = 'Low Stock';
        } else {
            $this->stockStatus = 'In Stock';
        }
    }

    public function __construct()
    {
        $this->productModelImages = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable(); // Changed to DateTimeImmutable
        $this->productOffers = new ArrayCollection();
        $this->cartItems = new ArrayCollection();
        $this->productVariants = new ArrayCollection();
        $this->colors = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        // If an overview image file is set during creation, also set updatedAt
        // to ensure VichUploaderBundle processes it correctly.
        if (null !== $this->overviewImageFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        // If updatedAt is not already set (e.g., by setOverviewImageFile),
        // set it here. This ensures that even if only other fields are updated,
        // the updatedAt timestamp is correctly managed.
        if (null === $this->updatedAt) {
            $this->updatedAt = new \DateTimeImmutable();
        } else {
            // If overviewImageFile was set, updatedAt would already be DateTimeImmutable.
            // If not, and updatedAt was a DateTime, ensure it's DateTimeImmutable.
            if ($this->updatedAt instanceof \DateTime) {
                $this->updatedAt = \DateTimeImmutable::createFromMutable($this->updatedAt);
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getQuantityInStock(): ?int
    {
        return $this->quantityInStock;
    }

    public function setQuantityInStock(int $quantityInStock): static
    {
        $this->quantityInStock = $quantityInStock;
        $this->updateStockStatus();
        return $this;
    }

    public function getLoyaltyPoints(): int
    {
        return $this->loyaltyPoints;
    }

    public function setLoyaltyPoints(int $loyaltyPoints): static
    {
        $this->loyaltyPoints = $loyaltyPoints;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
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


    // Update getter/setter methods
    public function getOverviewImage(): ?string
    {
        return $this->overviewImage;
    }

    // Virtual field for EasyAdmin Stock Status
    // public function getStockStatus(): string
    // {
    //     $stock = $this->getQuantityInStock();
    //     if ($stock === null) {
    //         return 'Unknown';
    //     }
    //     if ($stock === 0) {
    //         return 'Out of Stock';
    //     } elseif ($stock > 0 && $stock <= 10) {
    //         return 'Low Stock';
    //     } else {
    //         return 'In Stock';
    //     }
    // }
    public function getStockStatus(): ?string
    {
        return $this->stockStatus; // <-- read directly from DB
    }

    public function setStockStatus(?string $stockStatus): self
    {
        $this->stockStatus = $stockStatus;
        return $this;
    }

    public function setOverviewImage(?string $overviewImage): static
    {
        $this->overviewImage = $overviewImage;
        return $this;
    }
    // private function calculateStockStatus(): string
    // {
    //     $lowStockThreshold = 10;

    //     // Calculate total stock (main + variants)
    //     $totalStock = $this->quantityInStock ?? 0;
    //     foreach ($this->productVariants as $variant) {
    //         $totalStock += $variant->getStock();
    //     }

    //     if ($totalStock === 0) {
    //         return 'Out of Stock';
    //     } elseif ($totalStock <= $lowStockThreshold) {
    //         return 'Low Stock';
    //     } else {
    //         return 'In Stock';
    //     }
    // }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function getProductVariants(): Collection
    {
        return $this->productVariants;
    }

    public function addProductVariant(ProductVariant $productVariant): static
    {
        if (!$this->productVariants->contains($productVariant)) {
            $this->productVariants->add($productVariant);
            $productVariant->setProduct($this);
        }

        return $this;
    }

    public function removeProductVariant(ProductVariant $productVariant): static
    {
        if ($this->productVariants->removeElement($productVariant)) {
            // set the owning side to null (unless already changed)
            if ($productVariant->getProduct() === $this) {
                $productVariant->setProduct(null);
            }
        }

        return $this;
    }

    public function setOverviewImageFile(?File $overviewImageFile = null): void
    {
        $this->overviewImageFile = $overviewImageFile;

        if (null !== $overviewImageFile) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getOverviewImageFile(): ?File
    {
        return $this->overviewImageFile;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): static
    {
        $this->brand = $brand;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
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

    public function getShape(): ?Shape
    {
        return $this->shape;
    }

    public function setShape(?Shape $shape): static
    {
        $this->shape = $shape;
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

    /**
     * @return Collection<int, Color>
     */
    public function getColors(): Collection
    {
        return $this->colors;
    }

    public function addColor(Color $color): static
    {
        if (!$this->colors->contains($color)) {
            $this->colors->add($color);
        }

        return $this;
    }

    public function removeColor(Color $color): static
    {
        $this->colors->removeElement($color);

        return $this;
    }

    /**
     * @return Collection<int, ProductModelImage>
     */
    public function getProductModelImages(): Collection
    {
        return $this->productModelImages;
    }

    public function addProductModelImage(ProductModelImage $productModelImage): static
    {
        if (!$this->productModelImages->contains($productModelImage)) {
            $this->productModelImages->add($productModelImage);
            $productModelImage->setProduct($this);
        }
        return $this;
    }

    public function removeProductModelImage(ProductModelImage $productModelImage): static
    {
        if ($this->productModelImages->removeElement($productModelImage)) {
            // set the owning side to null (unless already changed)
            if ($productModelImage->getProduct() === $this) {
                $productModelImage->setProduct(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setProduct($this);
        }
        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getProduct() === $this) {
                $review->setProduct(null);
            }
        }
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? 'Product';
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
            $productOffer->addProduct($this);
        }

        return $this;
    }

    public function removeProductOffer(ProductOffer $productOffer): static
    {
        if ($this->productOffers->removeElement($productOffer)) {
            $productOffer->removeProduct($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getCartItems(): Collection
    {
        return $this->cartItems;
    }

    public function addCartItem(CartItem $cartItem): static
    {
        if (!$this->cartItems->contains($cartItem)) {
            $this->cartItems->add($cartItem);
            $cartItem->setProduct($this);
        }

        return $this;
    }

    public function removeCartItem(CartItem $cartItem): static
    {
        if ($this->cartItems->removeElement($cartItem)) {
            // set the owning side to null (unless already changed)
            if ($cartItem->getProduct() === $this) {
                $cartItem->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
}
