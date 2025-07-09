<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductOffer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductOffer>
 *
 * @method ProductOffer|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductOffer|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductOffer[]    findAll()
 * @method ProductOffer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductOffer::class);
    }

    /**
     * Finds the most relevant, current offer for a specific product.
     * It prioritizes product-specific offers over brand-wide offers.
     */
    public function findCurrentOfferForProduct(Product $product): ?ProductOffer
    {
        $now = new \DateTime();

        // 1. Look for an active offer specifically for this product
        $qbProduct = $this->createQueryBuilder('po_prod');
        $productOffer = $qbProduct
            ->where(':product MEMBER OF po_prod.products')
            ->andWhere('po_prod.startDate <= :now')
            ->andWhere('po_prod.endDate >= :now OR po_prod.endDate IS NULL')
            ->setParameter('product', $product)
            ->setParameter('now', $now)
            ->orderBy('po_prod.startDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($productOffer) {
            return $productOffer;
        }

        // 2. If no product-specific offer, look for a brand-level offer
        if ($product->getBrand()) {
            $qbBrand = $this->createQueryBuilder('po_brand');
            $brandOffer = $qbBrand
                ->where(':brand MEMBER OF po_brand.brands')
                ->andWhere('po_brand.startDate <= :now')
                ->andWhere('po_brand.endDate >= :now OR po_brand.endDate IS NULL')
                ->setParameter('brand', $product->getBrand())
                ->setParameter('now', $now)
                ->orderBy('po_brand.startDate', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            return $brandOffer;
        }

        return null;
    }
}
