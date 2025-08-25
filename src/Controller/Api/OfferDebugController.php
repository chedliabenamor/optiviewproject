<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Service\OfferService;
use App\Repository\ProductOfferRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class OfferDebugController extends AbstractController
{
    public function __construct(
        private OfferService $offerService,
        private ProductOfferRepository $productOfferRepository
    ) {
    }

    #[Route('/api/debug/product/{id}/offers', name: 'api_debug_product_offers', methods: ['GET'])]
    public function debugProductOffers(Product $product): JsonResponse
    {
        $debug = [];
        
        // Basic product info
        $debug['product'] = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'category' => $product->getCategory() ? $product->getCategory()->getName() : null,
            'brand' => $product->getBrand() ? $product->getBrand()->getName() : null,
        ];

        // All offers in database
        $allOffers = $this->productOfferRepository->findAll();
        $debug['all_offers_count'] = count($allOffers);
        $debug['all_offers'] = [];
        
        foreach ($allOffers as $offer) {
            $debug['all_offers'][] = [
                'id' => $offer->getId(),
                'name' => $offer->getName(),
                'discount_type' => $offer->getDiscountType(),
                'discount_value' => $offer->getDiscountValue(),
                'start_date' => $offer->getStartDate()?->format('Y-m-d H:i:s'),
                'end_date' => $offer->getEndDate()?->format('Y-m-d H:i:s'),
                'is_active' => $offer->isActive(),
                'deleted_at' => $offer->getDeletedAt()?->format('Y-m-d H:i:s'),
                'categories_count' => $offer->getCategories()->count(),
                'brands_count' => $offer->getBrands()->count(),
                'products_count' => $offer->getProducts()->count(),
                'variants_count' => $offer->getProductVariants()->count(),
            ];
        }

        // Direct product offers
        $debug['direct_product_offers'] = [];
        foreach ($product->getProductOffers() as $offer) {
            $debug['direct_product_offers'][] = [
                'id' => $offer->getId(),
                'name' => $offer->getName(),
                'is_active_check' => $this->offerService->isOfferActive($offer),
            ];
        }

        // Category offers
        $debug['category_offers'] = [];
        if ($product->getCategory()) {
            $categoryOffers = $this->productOfferRepository->findActiveOffersByCategory($product->getCategory());
            foreach ($categoryOffers as $offer) {
                $debug['category_offers'][] = [
                    'id' => $offer->getId(),
                    'name' => $offer->getName(),
                    'is_active_check' => $this->offerService->isOfferActive($offer),
                ];
            }
        }

        // Brand offers
        $debug['brand_offers'] = [];
        if ($product->getBrand()) {
            $brandOffers = $this->productOfferRepository->findActiveOffersByBrand($product->getBrand());
            foreach ($brandOffers as $offer) {
                $debug['brand_offers'][] = [
                    'id' => $offer->getId(),
                    'name' => $offer->getName(),
                    'is_active_check' => $this->offerService->isOfferActive($offer),
                ];
            }
        }

        // Active offers for product
        $activeOffers = $this->offerService->getActiveOffersForProduct($product);
        $debug['active_offers_for_product'] = [];
        foreach ($activeOffers as $offer) {
            $debug['active_offers_for_product'][] = [
                'id' => $offer->getId(),
                'name' => $offer->getName(),
                'discount_type' => $offer->getDiscountType(),
                'discount_value' => $offer->getDiscountValue(),
            ];
        }

        // Best offer
        $bestOffer = $this->offerService->getBestOfferForProduct($product);
        $debug['best_offer'] = $bestOffer ? [
            'id' => $bestOffer->getId(),
            'name' => $bestOffer->getName(),
            'discount_type' => $bestOffer->getDiscountType(),
            'discount_value' => $bestOffer->getDiscountValue(),
        ] : null;

        // Final calculation
        $offerData = $this->offerService->calculateDiscountedPrice($product);
        $debug['final_calculation'] = $offerData;

        return new JsonResponse($debug);
    }
}
