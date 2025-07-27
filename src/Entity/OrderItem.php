<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;
    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        // Optionally clear the variant if the product changes
        if ($this->productVariant && $this->productVariant->getProduct() !== $product) {
            $this->productVariant = null;
        }
        return $this;
    }
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false, name: 'order_id')] // Explicitly name foreign key
    #[Assert\NotNull]
    private ?Order $relatedOrder = null;

    #[ORM\ManyToOne(inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductVariant $productVariant = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $unitPrice = '0.00'; // Price at the time of order

    public function __construct()
    {
        $this->quantity = 1;
        $this->unitPrice = '0.00';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRelatedOrder(): ?Order
    {
        return $this->relatedOrder;
    }

    public function setRelatedOrder(?Order $relatedOrder): static
    {
        $this->relatedOrder = $relatedOrder;
        return $this;
    }

    public function getProductVariant(): ?ProductVariant
    {
        return $this->productVariant;
    }

    public function setProductVariant(?ProductVariant $productVariant): self
    {
        $this->productVariant = $productVariant;

        // Also set the parent Product to ensure data consistency
        if ($productVariant) {
            $this->setProduct($productVariant->getProduct());
        }

        // Automatically set the unit price from the product variant
        if ($productVariant !== null) {
            $price = $productVariant->getPrice();
            if ($price !== null) {
                $this->setUnitPrice($price);
            }
        }

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function __toString(): string
    {
        $variant = $this->getProductVariant();
        
        if ($variant) {
            // This uses the __toString() method from ProductVariant we created earlier
            $productName = (string) $variant; 
        } else {
            $productName = 'N/A';
        }
        
        $quantity = $this->getQuantity() ?? 0;

        return sprintf('%s (Quantity: %d)', $productName, $quantity);
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getSubtotal(): float
    {
        return $this->getQuantity() * (float)$this->getUnitPrice();
    }
}
