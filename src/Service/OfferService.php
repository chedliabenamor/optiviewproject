<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductOffer;
use App\Entity\ProductVariant;
use App\Repository\ProductOfferRepository;
use Doctrine\ORM\EntityManagerInterface;

class OfferService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductOfferRepository $productOfferRepository
    ) {
    }

    /**
     * Check if an offer is currently active
     */
    public function isOfferActive(ProductOffer $offer): bool
    {
        // Consider offers active for display/pricing if they overlap any time today
        $todayStart = new \DateTimeImmutable('today');
        $todayEnd = (new \DateTimeImmutable('tomorrow'))->modify('-1 second');

        return $offer->isActive()
            && $offer->getEndDate() >= $todayStart
            && $offer->getStartDate() <= $todayEnd
            && $offer->getDeletedAt() === null;
    }

    /**
     * Get the best active offer for a product
     */
    public function getBestOfferForProduct(Product $product): ?ProductOffer
    {
        $offers = $this->getActiveOffersForProduct($product);
        
        if (empty($offers)) {
            return null;
        }

        // Sort offers by discount amount (highest first)
        usort($offers, function (ProductOffer $a, ProductOffer $b) use ($product) {
            $discountA = $this->calculateDiscountAmount($a, $product->getPrice());
            $discountB = $this->calculateDiscountAmount($b, $product->getPrice());
            return $discountB <=> $discountA;
        });

        return $offers[0];
    }

    /**
     * Get the best active offer for a product variant
     */
    public function getBestOfferForVariant(ProductVariant $variant): ?ProductOffer
    {
        $offers = $this->getActiveOffersForVariant($variant);
        
        if (empty($offers)) {
            return null;
        }

        // Sort offers by discount amount (highest first)
        usort($offers, function (ProductOffer $a, ProductOffer $b) use ($variant) {
            $discountA = $this->calculateDiscountAmount($a, $variant->getPrice());
            $discountB = $this->calculateDiscountAmount($b, $variant->getPrice());
            return $discountB <=> $discountA;
        });

        return $offers[0];
    }

    /**
     * Get all active offers for a product
     */
    public function getActiveOffersForProduct(Product $product): array
    {
        $offers = [];
        
        // Direct product offers
        foreach ($product->getProductOffers() as $offer) {
            if ($this->isOfferActive($offer)) {
                $offers[] = $offer;
            }
        }

        // Category offers
        if ($product->getCategory()) {
            $categoryOffers = $this->productOfferRepository->findActiveOffersByCategory($product->getCategory());
            foreach ($categoryOffers as $offer) {
                if ($this->isOfferActive($offer)) {
                    $offers[] = $offer;
                }
            }
        }

        // Brand offers
        if ($product->getBrand()) {
            $brandOffers = $this->productOfferRepository->findActiveOffersByBrand($product->getBrand());
            foreach ($brandOffers as $offer) {
                if ($this->isOfferActive($offer)) {
                    $offers[] = $offer;
                }
            }
        }

        // Use array_unique with SORT_STRING to avoid deep object comparison
        return array_unique($offers, SORT_STRING);
    }

    /**
     * Get all active offers for a product variant
     */
    public function getActiveOffersForVariant(ProductVariant $variant): array
    {
        $offers = [];
        $product = $variant->getProduct();
        
        // Direct variant offers
        foreach ($variant->getProductOffers() as $offer) {
            if ($this->isOfferActive($offer)) {
                $offers[] = $offer;
            }
        }

        // Direct product offers (avoid calling getActiveOffersForProduct to prevent recursion)
        foreach ($product->getProductOffers() as $offer) {
            if ($this->isOfferActive($offer)) {
                $offers[] = $offer;
            }
        }

        // Category offers
        if ($product->getCategory()) {
            $categoryOffers = $this->productOfferRepository->findActiveOffersByCategory($product->getCategory());
            foreach ($categoryOffers as $offer) {
                if ($this->isOfferActive($offer)) {
                    $offers[] = $offer;
                }
            }
        }

        // Brand offers
        if ($product->getBrand()) {
            $brandOffers = $this->productOfferRepository->findActiveOffersByBrand($product->getBrand());
            foreach ($brandOffers as $offer) {
                if ($this->isOfferActive($offer)) {
                    $offers[] = $offer;
                }
            }
        }

        // Use array_unique with SORT_STRING to avoid deep object comparison
        return array_unique($offers, SORT_STRING);
    }

    /**
     * Calculate discounted price for a product
     */
    public function calculateDiscountedPrice(Product $product): array
    {
        $originalPrice = (float) $product->getPrice();
        
        // Debug: Check if price is stored as cents and needs conversion
        if ($originalPrice > 1000) {
            $originalPrice = $originalPrice / 100;
        }
        
        $bestOffer = $this->getBestOfferForProduct($product);

        if (!$bestOffer) {
            return [
                'original_price' => $originalPrice,
                'discounted_price' => $originalPrice,
                'discount_amount' => 0,
                'discount_percentage' => 0,
                'has_offer' => false,
                'offer' => null
            ];
        }

        $discountAmount = $this->calculateDiscountAmount($bestOffer, $originalPrice);
        $discountedPrice = max(0, $originalPrice - $discountAmount);
        $discountPercentage = $originalPrice > 0 ? round(($discountAmount / $originalPrice) * 100, 2) : 0;

        return [
            'original_price' => $originalPrice,
            'discounted_price' => $discountedPrice,
            'discount_amount' => $discountAmount,
            'discount_percentage' => $discountPercentage,
            'has_offer' => true,
            'offer' => $bestOffer,
            'offer_end_date' => $bestOffer->getEndDate(),
            'time_remaining' => $this->calculateTimeRemaining($bestOffer)
        ];
    }

    /**
     * Calculate discounted price for a product variant
     */
    public function calculateDiscountedPriceForVariant(ProductVariant $variant): array
    {
        $originalPrice = (float) $variant->getPrice();
        
        // Debug: Check if price is stored as cents and needs conversion
        if ($originalPrice > 1000) {
            $originalPrice = $originalPrice / 100;
        }
        
        $bestOffer = $this->getBestOfferForVariant($variant);

        if (!$bestOffer) {
            return [
                'original_price' => $originalPrice,
                'discounted_price' => $originalPrice,
                'discount_amount' => 0,
                'discount_percentage' => 0,
                'has_offer' => false,
                'offer' => null
            ];
        }

        $discountAmount = $this->calculateDiscountAmount($bestOffer, $originalPrice);
        $discountedPrice = max(0, $originalPrice - $discountAmount);
        $discountPercentage = $originalPrice > 0 ? round(($discountAmount / $originalPrice) * 100, 2) : 0;

        return [
            'original_price' => $originalPrice,
            'discounted_price' => $discountedPrice,
            'discount_amount' => $discountAmount,
            'discount_percentage' => $discountPercentage,
            'has_offer' => true,
            'offer' => $bestOffer,
            'offer_end_date' => $bestOffer->getEndDate(),
            'time_remaining' => $this->calculateTimeRemaining($bestOffer)
        ];
    }

    /**
     * Calculate discount amount based on offer type
     */
    private function calculateDiscountAmount(ProductOffer $offer, float $price): float
    {
        $discountValue = (float) $offer->getDiscountValue();

        return match ($offer->getDiscountType()) {
            ProductOffer::DISCOUNT_TYPE_PERCENTAGE => ($price * $discountValue) / 100,
            ProductOffer::DISCOUNT_TYPE_FIXED => min($discountValue, $price), // Don't exceed the original price
            default => 0
        };
    }

    /**
     * Calculate time remaining until offer expires
     */
    public function calculateTimeRemaining(ProductOffer $offer): array
    {
        $now = new \DateTime();
        $endDate = $offer->getEndDate();

        if ($endDate <= $now) {
            return [
                'expired' => true,
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
                'seconds' => 0,
                'total_seconds' => 0
            ];
        }

        $interval = $now->diff($endDate);
        $totalSeconds = $endDate->getTimestamp() - $now->getTimestamp();

        return [
            'expired' => false,
            'days' => $interval->days,
            'hours' => $interval->h,
            'minutes' => $interval->i,
            'seconds' => $interval->s,
            'total_seconds' => $totalSeconds
        ];
    }

    /**
     * Get discount label for display
     */
    public function getDiscountLabel(ProductOffer $offer, float $originalPrice): string
    {
        $discountValue = (float) $offer->getDiscountValue();

        return match ($offer->getDiscountType()) {
            ProductOffer::DISCOUNT_TYPE_PERCENTAGE => "-{$discountValue}%",
            ProductOffer::DISCOUNT_TYPE_FIXED => "-€{$discountValue}",
            default => ""
        };
    }

    /**
     * Check if offer will expire soon (within 24 hours)
     */
    public function isOfferExpiringSoon(ProductOffer $offer, int $hoursThreshold = 24): bool
    {
        $now = new \DateTime();
        $threshold = clone $now;
        $threshold->add(new \DateInterval("PT{$hoursThreshold}H"));

        return $offer->getEndDate() <= $threshold && $offer->getEndDate() > $now;
    }

}
