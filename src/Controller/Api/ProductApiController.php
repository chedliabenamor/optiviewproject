<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class ProductApiController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private SerializerInterface $serializer,
        private string $overviewImagesPath,
        private string $productModelImagesPath
    ) {
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
            $data['overviewImage'] = $product->getOverviewImage() ? $this->overviewImagesPath . '/' . $product->getOverviewImage() : null;
            $data['quantityInStock'] = $product->getTotalStock();
            $data['description'] = $product->getDescription();
            $data['category'] = $product->getCategory() ? $product->getCategory()->getName() : null;
            $data['categoryName'] = $product->getCategory() ? $product->getCategory()->getName() : null;
            $currency = $product->getCurrency();
            $data['currency'] = is_object($currency) ? $currency->getCode() : ($currency ?? 'EUR');

            // Process variants if any
            if ($product->getProductVariants() && $product->getProductVariants()->count() > 0) {
                $data['productVariants'] = $product->getProductVariants()->map(function($variant) {
                    return [
                        'id' => $variant->getId(),
                        'name' => (string) $variant,
                        'price' => $variant->getPrice(),
                        'quantityInStock' => $variant->getStock(),
                        'currency' => is_object($variant->getCurrency()) ? $variant->getCurrency()->getCode() : ($variant->getCurrency() ?? 'EUR')
                    ];
                })->toArray();
            }

            // Process images
            if ($product->getProductModelImages() && $product->getProductModelImages()->count() > 0) {
                $data['productModelImages'] = $product->getProductModelImages()->map(function($image) {
                    return [
                        'id' => $image->getId(),
                        'imageUrl' => $this->productModelImagesPath . '/' . $image->getImageUrl(),
                        'altText' => $image->getAltText()
                    ];
                })->toArray();
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
