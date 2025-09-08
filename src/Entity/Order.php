<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')] // 'order' is a reserved keyword in SQL
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: "deletedAt", timeAware: false)]
class Order
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'relatedOrder', targetEntity: OrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $orderItems;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $totalAmount = '0.00';

    #[ORM\Column(type: Types::INTEGER, options: ["default" => 0])]
    private int $totalPointsEarned = 0;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
        self::STATUS_REFUNDED
    ])]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shippingAddress = null; // Could be denormalized or linked to an Address entity

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $billingAddress = null; // Could be denormalized or linked to an Address entity

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['paypal', 'credit card'])]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['DHL', 'UPS', 'Poste', 'GLS'])]
    private ?string $shippingProvider = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['Standard', 'Express', 'Same-day'])]
    private ?string $deliveryType = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['Domestic', 'International'])]
    private ?string $destination = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentStatus = null; // e.g., 'paid', 'pending', 'failed'

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transactionId = null; // From payment gateway


    // Static tax amount (7%)
    public const TAX_PERCENT = 7;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $taxAmount = '0.00';


    #[ORM\Column(length: 10, nullable: false)]
    #[Assert\Choice(choices: ['EUR', 'USD'])]
    private ?string $currency = 'EUR';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->status = self::STATUS_PENDING;
        $this->paymentStatus = 'pending';
        $this->totalAmount = '0.00';
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
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
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setRelatedOrder($this);
            $this->updateTotals(); // Use the new comprehensive update method
        }
        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            // set the owning side to null (unless already changed)
            if ($orderItem->getRelatedOrder() === $this) {
                $orderItem->setRelatedOrder(null);
            }
            $this->updateTotals(); // Use the new comprehensive update method
        }
        return $this;
    }

    public function getTotalPointsEarned(): int
    {
        return $this->totalPointsEarned;
    }

    public function setTotalPointsEarned(int $totalPointsEarned): static
    {
        $this->totalPointsEarned = $totalPointsEarned;

        return $this;
    }

    /**
     * Recalculates the total amount and total points earned from the order items.
     * It's crucial to call this method whenever the collection of order items changes.
     */
    public function updateTotals(): void
    {
        $totalAmount = '0.00';
        $totalPoints = 0;

        foreach ($this->orderItems as $item) {
            // Calculate total amount
            $unitPrice = $item->getUnitPrice();
            if ($unitPrice === null) {
                throw new \RuntimeException(sprintf(
                    'Order item %d is missing unit price. Product variant: %s',
                    $item->getId() ?? 'new',
                    $item->getProductVariant() ? $item->getProductVariant()->getId() : 'none'
                ));
            }
            $totalAmount = bcadd($totalAmount, bcmul($item->getQuantity(), $unitPrice, 2), 2);

            // Calculate total points
            // This assumes points are set on the OrderItem when it's created.
            $totalPoints += $item->getPointsEarned();
        }

        $this->totalAmount = $totalAmount;
        $this->totalPointsEarned = $totalPoints;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getShippingAddress(): ?string
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(?string $shippingAddress): static
    {
        $this->shippingAddress = $shippingAddress;
        return $this;
    }

    public function getBillingAddress(): ?string
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(?string $billingAddress): static
    {
        $this->billingAddress = $billingAddress;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(?string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): static
    {
        $this->transactionId = $transactionId;
        return $this;
    }


    /**
     * Calculate subtotal from order items
     */
    public function getSubtotal(): string
    {
        $subtotal = '0.00';
        foreach ($this->orderItems as $item) {
            $subtotal = bcadd($subtotal, bcmul($item->getQuantity(), $item->getUnitPrice(), 2), 2);
        }
        return $subtotal;
    }


    /**
     * Get tax amount (7% of subtotal)
     */
    public function getTaxAmount(): string
    {
        return bcmul($this->getSubtotal(), self::TAX_PERCENT / 100, 2);
    }

    /**
     * Calculate shipping fee based on destination, deliveryType, total, and currency
     */
    public function getShippingFee(): string
    {
        $currency = $this->getCurrency() ?? 'EUR';
        $isEuro = $currency === 'EUR';
        $base = 0;
        $add = 0;
        // Base fee by location
        if ($this->destination === 'International') {
            $base = $isEuro ? 30.90 : 30.90;
        } else {
            $base = $isEuro ? 20.90 : 20.90;
        }
        // Additional by delivery type
        if ($this->deliveryType === 'Express') {
            $add = $isEuro ? 15.00 : 15.00;
        } elseif ($this->deliveryType === 'Same-day') {
            $add = $isEuro ? 20.00 : 20.00;
        } else {
            $add = 0.00;
        }
        $fee = $base + $add;
        // Free shipping if Standard and total (with tax) >= 100
        $totalWithTax = (float)$this->getTotalAmount() + (float)$this->getTaxAmount();
        if ($this->deliveryType === 'Standard' && $totalWithTax >= 100) {
            $fee = 0.00;
        }
        return number_format($fee, 2, '.', '');
    }

    /**
     * Final total (items total + tax + shipping)
     */
    public function getFinalTotal(): string
    {
        $total = (float)$this->getTotalAmount();
        $tax = (float)$this->getTaxAmount();
        $shipping = (float)$this->getShippingFee();
        return number_format($total + $tax + $shipping, 2, '.', '');
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

    public function getShippingProvider(): ?string
    {
        return $this->shippingProvider;
    }

    public function setShippingProvider(?string $shippingProvider): static
    {
        $this->shippingProvider = $shippingProvider;
        return $this;
    }

    public function getDeliveryType(): ?string
    {
        return $this->deliveryType;
    }

    public function setDeliveryType(?string $deliveryType): static
    {
        $this->deliveryType = $deliveryType;
        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(?string $destination): static
    {
        $this->destination = $destination;
        return $this;
    }


    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): static
    {
        $this->trackingNumber = $trackingNumber;
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

    public function calculateTotalAmount(): void
    {
        $total = 0.0;
        foreach ($this->orderItems as $item) {
            $total += $item->getSubtotal();
        }
        $this->totalAmount = (string)$total;
    }
    /**
     * Get conversion rate from EUR to USD using Frankfurter API
     */
    public function getConversionRate(): float
    {
        if ($this->currency === 'USD') {
            try {
                $amount = 1;
                $url = "https://api.frankfurter.app/latest?amount=$amount&from=EUR&to=USD";
                $response = @file_get_contents($url);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if (isset($data['rates']['USD'])) {
                        return (float)$data['rates']['USD'];
                    }
                }
            } catch (\Exception $e) {}
            // fallback to 1 if API fails
            return 1.0;
        }
        return 1.0;
    }

    /**
     * Convert an amount from EUR to USD if needed
     */
    public function getConvertedAmount($amount): string
    {
        $rate = $this->getConversionRate();
        return number_format((float)$amount * $rate, 2, '.', '');
    }
}
