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

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false, name: 'order_id')]
    #[Assert\NotNull]
    private ?Order $relatedOrder = null;

    #[ORM\ManyToOne(inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductVariant $productVariant = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $quantity = 1;


    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $unitPrice = '0.00';

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(choices: ['pending', 'paid', 'failed'])]
    private ?string $paymentStatus = 'pending';

    #[ORM\Column(type: Types::INTEGER, options: ["default" => 0])]
    private int $pointsEarned = 0;

    /**
     * @var null|\App\Repository\OfferRepository
     */
    private static $offerRepository = null;

    public function __construct()
    {
        $this->quantity = 1;
        $this->unitPrice = '0.00';
    }

    public static function setOfferRepository($offerRepository): void
    {
        self::$offerRepository = $offerRepository;
    }

    public function getOfferDiscount(): string
    {
        $product = $this->getProduct();
        if (!$product || !self::$offerRepository) {
            return '0.00';
        }
        $offers = self::$offerRepository->findBy(['active' => true]);
        foreach ($offers as $offer) {
            if ($offer->getProducts()->contains($product)) {
                return bcmul($offer->getDiscount(), $this->getQuantity(), 2);
            }
        }
        return '0.00';
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        if ($this->productVariant && $this->productVariant->getProduct() !== $product) {
            $this->productVariant = null;
        }
        return $this;
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
        if ($productVariant) {
            $this->setProduct($productVariant->getProduct());
        }
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

    public function getPointsEarned(): int
    {
        return $this->pointsEarned;
    }

    public function setPointsEarned(int $pointsEarned): static
    {
        $this->pointsEarned = $pointsEarned;
        return $this;
    }

    public function calculatePointsEarned(): int
    {
        if ($this->getProduct()) {
            return $this->getProduct()->getLoyaltyPoints() * $this->getQuantity();
        }
        return 0;
    }
    /**
     * Get subtotal in order currency (EUR or USD)
     */
    public function getConvertedSubtotal(): string
    {
        $subtotal = $this->getSubtotal();
        $order = $this->getRelatedOrder();
        if ($order && method_exists($order, 'getConvertedAmount')) {
            return $order->getConvertedAmount($subtotal);
        }
        return number_format($subtotal, 2, '.', '');
    }

    /**
     * Get VAT (7%) in order currency
     */
    public function getConvertedVatAmount(): string
    {
        $vat = $this->getSubtotal() * 0.07;
        $order = $this->getRelatedOrder();
        if ($order && method_exists($order, 'getConvertedAmount')) {
            return $order->getConvertedAmount($vat);
        }
        return number_format($vat, 2, '.', '');
    }

    /**
     * Get total with VAT in order currency
     */
    public function getConvertedTotalWithVat(): string
    {
        $total = $this->getSubtotal() + $this->getSubtotal() * 0.07;
        $order = $this->getRelatedOrder();
        if ($order && method_exists($order, 'getConvertedAmount')) {
            return $order->getConvertedAmount($total);
        }
        return number_format($total, 2, '.', '');
    }
    /**
     * Get VAT (7%) for this item
     */
    public function getVatAmount(): string
    {
        $subtotal = $this->getSubtotal();
        return number_format($subtotal * 0.07, 2, '.', '');
    }

    /**
     * Get total with VAT for this item
     */
    public function getTotalWithVat(): string
    {
        return number_format($this->getSubtotal() + $this->getVatAmount(), 2, '.', '');
    }
}
