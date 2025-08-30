<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;

class SkuGeneratorService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Generate SKU for Product: REF-{productId}-{category}-{brand}
     */
    public function generateProductSku(Product $product): string
    {
        $productId = $product->getId();
        $category = $product->getCategory() ? $this->sanitizeForSku($product->getCategory()->getName()) : 'NOCAT';
        $brand = $product->getBrand() ? $this->sanitizeForSku($product->getBrand()->getName()) : 'NOBRAND';
        
        $baseSku = "REF-{$productId}-{$category}-{$brand}";
        
        return $this->ensureUniqueSku($baseSku, Product::class);
    }

    /**
     * Generate SKU for ProductVariant: REF-{productId}-{category}-{brand}-{variantId}
     */
    public function generateProductVariantSku(ProductVariant $variant): string
    {
        $product = $variant->getProduct();
        if (!$product) {
            throw new \InvalidArgumentException('ProductVariant must have a Product assigned to generate SKU');
        }
        
        $productId = $product->getId();
        $variantId = $variant->getId();
        $category = $product->getCategory() ? $this->sanitizeForSku($product->getCategory()->getName()) : 'NOCAT';
        $brand = $product->getBrand() ? $this->sanitizeForSku($product->getBrand()->getName()) : 'NOBRAND';
        
        // If variant ID is not available yet, use a timestamp-based identifier
        if (!$variantId) {
            $variantId = 'V' . time();
        }
        
        $baseSku = "REF-{$productId}-{$category}-{$brand}-{$variantId}";
        
        return $this->ensureUniqueSku($baseSku, ProductVariant::class);
    }

    /**
     * Sanitize string for use in SKU (remove spaces, special chars, convert to uppercase)
     */
    private function sanitizeForSku(string $input): string
    {
        // Remove accents and special characters, convert to uppercase
        $sanitized = iconv('UTF-8', 'ASCII//TRANSLIT', $input);
        // Remove non-alphanumeric characters and replace with nothing
        $sanitized = preg_replace('/[^A-Za-z0-9]/', '', $sanitized);
        // Convert to uppercase and limit length
        return strtoupper(substr($sanitized, 0, 10));
    }

    /**
     * Ensure SKU is unique by adding a suffix if needed
     */
    private function ensureUniqueSku(string $baseSku, string $entityClass): string
    {
        $repository = $this->entityManager->getRepository($entityClass);
        $sku = $baseSku;
        $counter = 1;

        while ($this->skuExists($sku, $entityClass)) {
            $sku = $baseSku . '-' . $counter;
            $counter++;
        }

        return $sku;
    }

    /**
     * Check if SKU already exists in the database
     */
    private function skuExists(string $sku, string $entityClass): bool
    {
        $repository = $this->entityManager->getRepository($entityClass);
        
        $queryBuilder = $repository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.sku = :sku');

        // For soft-deletable entities, exclude deleted ones
        if ($entityClass === Product::class || $entityClass === ProductVariant::class) {
            $queryBuilder->andWhere('e.deletedAt IS NULL');
        }

        $count = $queryBuilder
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
