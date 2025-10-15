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
    private string $productOverlayUriPrefix;
    private string $productVariantOverlayUriPrefix;

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
        // Overlay mappings for 2D/3D try-on
        $this->productOverlayUriPrefix = $vichUploaderMappings['product_overlay_files']['uri_prefix'] ?? '/uploads/overlays';
        $this->productVariantOverlayUriPrefix = $vichUploaderMappings['product_variant_overlay_files']['uri_prefix'] ?? '/uploads/overlays/variants';
    }

    #[Route('/api/products/{id}/quick-view', name: 'api_product_quick_view', methods: ['GET'])]
    public function quickView(Product $product): JsonResponse
    {
        try {
            // Block archived products or products tied to archived category/brand
            if (method_exists($product, 'getDeletedAt') && $product->getDeletedAt() !== null) {
                return new JsonResponse(['error' => 'Product archived'], Response::HTTP_NOT_FOUND);
            }
            if ($product->getCategory() && method_exists($product->getCategory(), 'getDeletedAt') && $product->getCategory()->getDeletedAt() !== null) {
                return new JsonResponse(['error' => 'Category archived'], Response::HTTP_NOT_FOUND);
            }
            if ($product->getBrand() && method_exists($product->getBrand(), 'getDeletedAt') && $product->getBrand()->getDeletedAt() !== null) {
                return new JsonResponse(['error' => 'Brand archived'], Response::HTTP_NOT_FOUND);
            }
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
            // Product-level overlay image (2D/3D asset URL)
            $productOverlay = method_exists($product, 'getOverlayAsset') ? $product->getOverlayAsset() : null;
            $data['overlayImage'] = $productOverlay ? ($this->productOverlayUriPrefix . '/' . $productOverlay) : null;
            $data['quantityInStock'] = $product->getQuantityInStock();
            $data['description'] = $product->getDescription();
            // Expose simple meta fields for popup (skip archived attributes)
            $data['brand'] = ($product->getBrand() && $product->getBrand()->getDeletedAt() === null) ? $product->getBrand()->getName() : null;
            $data['category'] = ($product->getCategory() && $product->getCategory()->getDeletedAt() === null) ? $product->getCategory()->getName() : null;
            $data['style'] = ($product->getStyle() && $product->getStyle()->getDeletedAt() === null) ? $product->getStyle()->getName() : null;
            $data['shape'] = ($product->getShape() && $product->getShape()->getDeletedAt() === null) ? $product->getShape()->getName() : null;
            $data['genre'] = ($product->getGenre() && $product->getGenre()->getDeletedAt() === null) ? $product->getGenre()->getName() : null;
            $data['loyaltyPoints'] = $product->getLoyaltyPoints();

            // Helper to compute the best active offer and discounted price
            $computeOffer = function (?string $basePrice, array $offers) {
                if ($basePrice === null) {
                    return null;
                }
                $todayStart = new \DateTimeImmutable('today');
                $todayEnd = (new \DateTimeImmutable('tomorrow'))->modify('-1 second');
                $best = null;
                $original = (float) $basePrice;
                foreach ($offers as $offer) {
                    if (!$offer->isActive()) { continue; }
                    if (method_exists($offer, 'getDeletedAt') && $offer->getDeletedAt() !== null) { continue; }
                    $start = $offer->getStartDate();
                    $end = $offer->getEndDate();
                    if (!$start || !$end) { continue; }
                    // Active any time today
                    if ($end < $todayStart || $start > $todayEnd) { continue; }

                    $discounted = $original;
                    if ($offer->getDiscountType() === \App\Entity\ProductOffer::TYPE_PERCENTAGE) {
                        $discounted = max(0, $original - ($original * (float)$offer->getDiscountValue() / 100));
                    } elseif ($offer->getDiscountType() === \App\Entity\ProductOffer::TYPE_FIXED) {
                        $discounted = max(0, $original - (float)$offer->getDiscountValue());
                    }

                    if ($best === null || $discounted < (float)$best['discounted_price']) {
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
            if ($product->getPrimaryColor() && $product->getPrimaryColor()->getDeletedAt() === null) {
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
                $variantsPayload = [];
                foreach ($product->getProductVariants() as $variant) {
                    // Skip only if the variant itself is archived/inactive
                    if (method_exists($variant, 'getDeletedAt') && $variant->getDeletedAt() !== null) { continue; }
                    if (method_exists($variant, 'isActive') && !$variant->isActive()) { continue; }
                    // Hide options whose attributes are archived
                    if ($variant->getColor() && method_exists($variant->getColor(), 'getDeletedAt') && $variant->getColor()->getDeletedAt() !== null) { continue; }
                    if ($variant->getStyle() && method_exists($variant->getStyle(), 'getDeletedAt') && $variant->getStyle()->getDeletedAt() !== null) { continue; }
                    if ($variant->getGenre() && method_exists($variant->getGenre(), 'getDeletedAt') && $variant->getGenre()->getDeletedAt() !== null) { continue; }
                    // Variant images (use accessor building URL)
                    $variantImages = [];
                    if ($variant->getProductVariantImages() && $variant->getProductVariantImages()->count() > 0) {
                        foreach ($variant->getProductVariantImages() as $vImage) {
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
                    // - Include product-specific offers (applies to all variants of the product)
                    $variantOffers = [];
                    foreach ($variant->getProductOffers() as $o) { $variantOffers[] = $o; }
                    $productOffers = [];
                    foreach ($product->getProductOffers() as $po) { $productOffers[] = $po; }
                    $variantOffers = array_merge($variantOffers, $productOffers, $categoryOffers, $brandOffers);
                    $variantOffer = $computeOffer($variant->getPrice(), $variantOffers);

                    $variantsPayload[] = [
                        'id' => $variant->getId(),
                        'name' => (string) $variant,
                        'price' => $variant->getPrice(),
                        'quantityInStock' => $variant->getStock(),
                        'sku' => $variant->getSku(),
                        'stockStatus' => $variant->getStockStatus(),
                        'color' => ($variant->getColor() ? [
                            'id' => $variant->getColor()->getId(),
                            'name' => $variant->getColor()->getName(),
                        ] : null),
                        'style' => ($variant->getStyle() ? [ 'name' => $variant->getStyle()->getName() ] : null),
                        'genre' => ($variant->getGenre() ? [ 'name' => $variant->getGenre()->getName() ] : null),
                        'productVariantImages' => $variantImages,
                        // Variant-level overlay URL (if any)
                        'overlayImage' => ($variant->getOverlayAsset() ? ($this->productVariantOverlayUriPrefix . '/' . $variant->getOverlayAsset()) : null),
                        'offer' => $variantOffer,
                    ];
                }
                $data['productVariants'] = array_values($variantsPayload);
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
