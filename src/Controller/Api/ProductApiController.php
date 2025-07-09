<?php

namespace App\Controller\Api;

use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ProductApiController extends AbstractController
{
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
