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
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ProductRepository::class)]

#[Vich\Uploadable]
#[Gedmo\SoftDeleteable(fieldName: "deletedAt", timeAware: false)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product_quick_view'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['product_quick_view'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product_quick_view'])]
    private ?string $sku = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['product_quick_view'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    #[Groups(['product_quick_view'])]
    private ?string $price = null;

    #[ORM\Column(name: "stock", type: Types::INTEGER)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?int $quantityInStock = null;

    #[ORM\Column(type: Types::INTEGER, options: ["default" => 0])]
    #[Groups(['product_quick_view'])]
    private int $loyaltyPoints = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null; // Main product image (Consider if this is still needed or can be removed)

    #[ORM\Column(length: 255)]
    #[Groups(['product_quick_view'])]
    private ?string $currency = 'EUR';

    // Vich Uploadable Field for the overview image file
    #[Vich\UploadableField(mapping: 'product_overview_images', fileNameProperty: 'overviewImage')]
    #[Groups(['product_quick_view'])]
    private ?File $overviewImageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $overviewImage = null; // Stores the filename of the overview image


    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)] // Allow products without a brand initially
    private ?Brand $brand = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[Groups(['product_quick_view'])]
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
    #[Groups(['product_quick_view'])]
    private Collection $productModelImages;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Review::class, orphanRemoval: true)]
    private Collection $reviews;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\ManyToMany(targetEntity: ProductOffer::class, mappedBy: 'products')]
    private Collection $productOffers;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: CartItem::class, orphanRemoval: true)]
    private Collection $cartItems;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductVariant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['product_quick_view'])]
    private Collection $productVariants;

    public function __construct()
    {
        $this->colors = new ArrayCollection();
        $this->productModelImages = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->productOffers = new ArrayCollection();
        $this->cartItems = new ArrayCollection();
        $this->productVariants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable(); // Changed to DateTimeImmutable
    }

    public function setOverviewImageFile(?File $file = null): void
    {
        $this->overviewImageFile = $file;

        if (null !== $file) {
            $this->updatedAt = new \DateTime();
        }
    }

    public function getOverviewImageFile(): ?File
    {
        return $this->overviewImageFile;
    }

    public function getOverviewImage(): ?string
    {
        return $this->overviewImage;
    }
    
    public function setOverviewImage(?string $overviewImage): self
    {
        $this->overviewImage = $overviewImage;
        return $this;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
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

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;

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


    // Virtual field for EasyAdmin Stock Status
    public function getStockStatus(): string
    {
        $stock = $this->getQuantityInStock();
        if ($stock === null) {
            return 'Unknown';
        }
        if ($stock === 0) {
            return 'Out of Stock';
        } elseif ($stock < 10) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }

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

    /**
     * Calculates the total stock from all product variants.
     *
     * @return int
     */
    public function getTotalStock(): int
    {
        $totalStock = 0;
        foreach ($this->getProductVariants() as $variant) {
            $totalStock += $variant->getStock();
        }
        return $totalStock;
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
