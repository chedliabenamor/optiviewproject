<?php

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Entity\ProductOffer;
use App\Repository\ProductOfferRepository;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class OrderTotalSubscriber implements EventSubscriberInterface
{
    public function __construct(private ProductOfferRepository $productOfferRepository)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->calculateTotal($args);
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->calculateTotal($args);
    }

    private function calculateTotal(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Order) {
            return;
        }

        $total = 0;
        foreach ($entity->getOrderItems() as $item) {
            $subtotal = $item->getUnitPrice() * $item->getQuantity();
            $product = $item->getProductVariant()->getProduct();

            // Apply product-specific offer (if any)
            if ($product) {
                $offer = $this->productOfferRepository->findCurrentOfferForProduct($product);
                if ($offer) {
                    $subtotal = $this->applyDiscount($subtotal, $offer);
                }
            }

            $total += $subtotal;
        }

        // Apply order-wide coupon code (if any)
        if ($entity->getCouponCode()) {
            $couponOffer = $this->productOfferRepository->findOneBy(['couponCode' => $entity->getCouponCode()]);
            if ($couponOffer && !$couponOffer->getProduct()) { // Ensure it's a general coupon
                $total = $this->applyDiscount($total, $couponOffer);
            }
        }

        $entity->setTotalAmount((string) $total);
    }

    private function applyDiscount(float $amount, ProductOffer $offer): float
    {
        if ($offer->getDiscountType() === ProductOffer::DISCOUNT_TYPE_PERCENTAGE) {
            return $amount - ($amount * ($offer->getDiscountValue() / 100));
        } elseif ($offer->getDiscountType() === ProductOffer::DISCOUNT_TYPE_FIXED) {
            return max(0, $amount - $offer->getDiscountValue()); // Ensure total doesn't go below zero
        }

        return $amount;
    }
}
