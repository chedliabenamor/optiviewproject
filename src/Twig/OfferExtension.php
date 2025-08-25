<?php

namespace App\Twig;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Service\OfferService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class OfferExtension extends AbstractExtension
{
    public function __construct(private OfferService $offerService)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('product_offer_data', [$this, 'getProductOfferData']),
            new TwigFunction('variant_offer_data', [$this, 'getVariantOfferData']),
            new TwigFunction('discount_label', [$this, 'getDiscountLabel']),
            new TwigFunction('time_remaining', [$this, 'getTimeRemaining']),
        ];
    }

    public function getProductOfferData(Product $product): array
    {
        return $this->offerService->calculateDiscountedPrice($product);
    }

    public function getVariantOfferData(ProductVariant $variant): array
    {
        return $this->offerService->calculateDiscountedPriceForVariant($variant);
    }

    public function getDiscountLabel(Product $product): string
    {
        $offer = $this->offerService->getBestOfferForProduct($product);
        if (!$offer) {
            return '';
        }
        
        // For percentage discounts, show the actual discount percentage
        if ($offer->getDiscountType() === 'percentage') {
            $discountValue = (float) $offer->getDiscountValue();
            return $discountValue > 0 ? '-' . round($discountValue) . '%' : '';
        }
        
        // For fixed discounts, calculate percentage or show fixed amount
        $originalPrice = (float) $product->getPrice();
        $discountAmount = $this->calculateDiscountAmount($offer, $originalPrice);
        $discountPercentage = $originalPrice > 0 ? ($discountAmount / $originalPrice) * 100 : 0;
        
        // If percentage is less than 1%, show fixed amount instead
        if ($discountPercentage < 1) {
            return '-€' . round($discountAmount);
        }
        
        return '-' . round($discountPercentage) . '%';
    }

    private function calculateDiscountAmount($offer, float $price): float
    {
        $discountValue = (float) $offer->getDiscountValue();

        return match ($offer->getDiscountType()) {
            'percentage' => ($price * $discountValue) / 100,
            'fixed' => min($discountValue, $price),
            default => 0
        };
    }

    public function getTimeRemaining(Product $product): array
    {
        $offer = $this->offerService->getBestOfferForProduct($product);
        if (!$offer) {
            return ['expired' => true];
        }
        
        return $this->offerService->calculateTimeRemaining($offer);
    }
}
