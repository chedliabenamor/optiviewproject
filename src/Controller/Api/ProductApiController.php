<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Repository\ProductOfferRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class ProductApiController extends AbstractController
{
    private string $productModelImagesUriPrefix;
    private string $productVariantImagesUriPrefix;
    private string $productOverviewImagesUriPrefix;

    public function __construct(
        private ProductRepository $productRepository,
        private SerializerInterface $serializer,
        private ProductOfferRepository $productOfferRepository,
        array $vichUploaderMappings
    ) {
        // ProductModelImage entity uses mapping name 'product_images'
        $this->productModelImagesUriPrefix = $vichUploaderMappings['product_images']['uri_prefix'];
        $this->productVariantImagesUriPrefix = $vichUploaderMappings['product_variant_images']['uri_prefix'];
        $this->productOverviewImagesUriPrefix = $vichUploaderMappings['product_overview_images']['uri_prefix'];
    }

    #[Route('/api/products/{id}/quick-view', name: 'api_product_quick_view', methods: ['GET'])]
    public function quickView(Product $product): JsonResponse
    {
        try {
            // Serialize the product with all necessary associations
            $data = $this->serializer->normalize(
                $product,
                null,
                [
                    'groups' => ['product_quick_view'],
                    AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                        return $object->getId();
                    }
                ]
            );

            // Add additional data that might not be in the serialization groups
            // Build full URL for overview image to avoid 404s
            $overview = $product->getOverviewImage();
            $data['overviewImage'] = $overview ? ($this->productOverviewImagesUriPrefix . '/' . $overview) : null;
            $data['quantityInStock'] = $product->getTotalStock();
            $data['description'] = $product->getDescription();
            // Expose simple meta fields for popup
            $data['brand'] = $product->getBrand() ? $product->getBrand()->getName() : null;
            $data['category'] = $product->getCategory() ? $product->getCategory()->getName() : null;
            $data['style'] = $product->getStyle() ? $product->getStyle()->getName() : null;
            $data['shape'] = $product->getShape() ? $product->getShape()->getName() : null;
            $data['genre'] = $product->getGenre() ? $product->getGenre()->getName() : null;
            $data['loyaltyPoints'] = $product->getLoyaltyPoints();

            // Helper to compute the best active offer and discounted price
            $computeOffer = function (?string $basePrice, array $offers) {
                if ($basePrice === null) {
                    return null;
                }
                $now = new \DateTime();
                $best = null;
                $original = (float) $basePrice;
                foreach ($offers as $offer) {
                    if (!$offer->isActive()) { continue; }
                    $start = $offer->getStartDate();
                    $end = $offer->getEndDate();
                    if (!$start || !$end) { continue; }
                    if ($now < $start || $now > $end) { continue; }

                    $discounted = $original;
                    if ($offer->getDiscountType() === \App\Entity\ProductOffer::TYPE_PERCENTAGE) {
                        $discounted = max(0, $original - ($original * (float)$offer->getDiscountValue() / 100));
                    } elseif ($offer->getDiscountType() === \App\Entity\ProductOffer::TYPE_FIXED) {
                        $discounted = max(0, $original - (float)$offer->getDiscountValue());
                    }

                    if ($best === null || $discounted < $best['discounted_price']) {
                        $percentage = $original > 0 ? (($original - $discounted) / $original) * 100 : 0;
                        $best = [
                            'has_offer' => true,
                            'original_price' => number_format($original, 2, '.', ''),
                            'discounted_price' => number_format($discounted, 2, '.', ''),
                            'discount_percentage' => $percentage,
                            'offer_end_date' => $end->format(DATE_ATOM),
                            'time_remaining' => ['expired' => false],
                        ];
                    }
                }

                return $best ?: null;
            };

            // Add product-level color (primary) if set
            if ($product->getPrimaryColor()) {
                $data['productColor'] = [
                    'id' => $product->getPrimaryColor()->getId(),
                    'name' => $product->getPrimaryColor()->getName(),
                ];
            }

            // Preload category/brand-level offers once (apply to product and its variants)
            $categoryOffers = [];
            if ($product->getCategory()) {
                $categoryOffers = $this->productOfferRepository->findActiveOffersByCategory($product->getCategory());
            }
            $brandOffers = [];
            if ($product->getBrand()) {
                $brandOffers = $this->productOfferRepository->findActiveOffersByBrand($product->getBrand());
            }

            // Process variants with color, style, genre and images
            if ($product->getProductVariants() && $product->getProductVariants()->count() > 0) {
                $data['productVariants'] = $product->getProductVariants()->map(function($variant) use ($product, $computeOffer, $categoryOffers, $brandOffers) {
                    // Variant images (use accessor building URL)
                    $variantImages = [];
                    if ($variant->getProductVariantImages() && $variant->getProductVariantImages()->count() > 0) {
                        foreach ($variant->getProductVariantImages() as $vImage) {
                            // Build full URL using Vich mapping prefix to avoid 404s due to base path
                            $fileName = $vImage->getImageName();
                            $url = $fileName ? ($this->productVariantImagesUriPrefix . '/' . $fileName) : $vImage->getImageUrl();
                            $variantImages[] = [
                                'imageUrl' => $url,
                                'altText' => $vImage->getAltText(),
                            ];
                        }
                    }

                    // Compute offers applicable to variant (per rules):
                    // - Include variant-specific offers
                    // - Include category and brand offers
                    // - DO NOT include product-specific offers
                    $variantOffers = [];
                    foreach ($variant->getProductOffers() as $o) { $variantOffers[] = $o; }
                    // Merge cat/brand offers
                    $variantOffers = array_merge($variantOffers, $categoryOffers, $brandOffers);
                    $variantOffer = $computeOffer($variant->getPrice(), $variantOffers);

                    return [
                        'id' => $variant->getId(),
                        'name' => (string) $variant,
                        'price' => $variant->getPrice(),
                        'quantityInStock' => $variant->getStock(),
                        'sku' => $variant->getSku(),
                        'stockStatus' => $variant->getStockStatus(),
                        'color' => $variant->getColor() ? [
                            'id' => $variant->getColor()->getId(),
                            'name' => $variant->getColor()->getName(),
                        ] : null,
                        'style' => $variant->getStyle() ? [
                            'name' => $variant->getStyle()->getName(),
                        ] : null,
                        'genre' => $variant->getGenre() ? [
                            'name' => $variant->getGenre()->getName(),
                        ] : null,
                        'productVariantImages' => $variantImages,
                        'offer' => $variantOffer,
                    ];
                })->toArray();
            }

            // Process images
            if ($product->getProductModelImages() && $product->getProductModelImages()->count() > 0) {
                $data['productModelImages'] = $product->getProductModelImages()->map(function($image) {
                    return [
                        'id' => $image->getId(),
                        'imageUrl' => $this->productModelImagesUriPrefix . '/' . $image->getImageUrl(),
                        'altText' => $image->getAltText()
                    ];
                })->toArray();
            }

            // Compute product-level offer (used before variant selection) per rules:
            // - Include product-specific offers
            // - Include category and brand offers
            $productOffers = [];
            foreach ($product->getProductOffers() as $o) { $productOffers[] = $o; }
            $applicableProductOffers = array_merge($productOffers, $categoryOffers, $brandOffers);
            $productOffer = $computeOffer($product->getPrice(), $applicableProductOffers);
            if ($productOffer) {
                $data['offer'] = $productOffer;
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Failed to load product details', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/api/product/{id}/variants', name: 'api_product_variants', methods: ['GET'])]
    public function getVariants(Product $product): JsonResponse
    {
        $variants = $product->getProductVariants()->map(function ($variant) {
            return [
                'id' => $variant->getId(),
                'text' => (string) $variant, // Uses the __toString() method
                'price' => $variant->getPrice(),
            ];
        });

        return new JsonResponse($variants->getValues());
    }
}
