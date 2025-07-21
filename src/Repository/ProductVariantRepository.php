<?php

namespace App\Repository;

use App\Entity\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductVariant>
 *
 * @method ProductVariant|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductVariant|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductVariant[]    findAll()
 * @method ProductVariant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariant::class);
    }

    //    /**
    //     * @return ProductVariant[] Returns an array of ProductVariant objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ProductVariant
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Fetch paginated and filterable variants for a product.
     *
     * @param int $productId
     * @param int $page
     * @param int $pageSize
     * @param array $filters (sku, color, stock)
     * @return array [variants, total]
     */
    public function findPaginatedByProduct(
        int $productId,
        int $page = 1,
        int $pageSize = 10,
        array $filters = []
    ): array {
        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.product = :productId')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('productId', $productId);

        if (!empty($filters['sku'])) {
            $qb->andWhere('LOWER(v.sku) LIKE :sku')
               ->setParameter('sku', '%' . strtolower($filters['sku']) . '%');
        }
        if (!empty($filters['color'])) {
            $qb->leftJoin('v.color', 'c')
               ->andWhere('LOWER(c.name) LIKE :color')
               ->setParameter('color', '%' . strtolower($filters['color']) . '%');
        }
        if (!empty($filters['stock'])) {
            if ($filters['stock'] === 'in') {
                $qb->andWhere('v.stock > 10');
            } elseif ($filters['stock'] === 'low') {
                $qb->andWhere('v.stock > 0 AND v.stock <= 10');
            } elseif ($filters['stock'] === 'out') {
                $qb->andWhere('v.stock = 0');
            }
        }

        $qbCount = clone $qb;
        $total = (int) $qbCount->select('COUNT(v.id)')->getQuery()->getSingleScalarResult();

        $variants = $qb
            ->orderBy('v.id', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return ['variants' => $variants, 'total' => $total];
    }
}
