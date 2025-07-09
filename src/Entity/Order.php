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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentStatus = null; // e.g., 'paid', 'pending', 'failed'

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transactionId = null; // From payment gateway

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
            $this->updateTotalAmount();
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
            $this->updateTotalAmount();
        }
        return $this;
    }
    
    private function updateTotalAmount(): void
    {
        $total = '0.00';
        
        foreach ($this->orderItems as $item) {
            $unitPrice = $item->getUnitPrice();
            if ($unitPrice === null) {
                throw new \RuntimeException(sprintf(
                    'Order item %d is missing unit price. Product variant: %s',
                    $item->getId() ?? 'new',
                    $item->getProductVariant() ? $item->getProductVariant()->getId() : 'none'
                ));
            }
            $total = bcadd($total, bcmul($item->getQuantity(), $unitPrice, 2), 2);
        }
        
        $this->totalAmount = $total;
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
}
