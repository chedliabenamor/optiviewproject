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
    int $page,
    int $pageSize,
    array $filters = [],
    bool $archived = false
): array {
    $qb = $this->createQueryBuilder('v')
        ->leftJoin('v.color', 'c')
        ->leftJoin('v.style', 'st')
        ->leftJoin('v.genre', 'g')
        ->andWhere('v.product = :productId')
        ->setParameter('productId', $productId);

    // ✅ switch between active/archived
    if ($archived) {
        // Archived tab: include variants that are archived themselves OR whose attributes are archived
        $qb->andWhere('(
            v.deletedAt IS NOT NULL
            OR (c.id IS NOT NULL AND c.deletedAt IS NOT NULL)
            OR (st.id IS NOT NULL AND st.deletedAt IS NOT NULL)
            OR (g.id IS NOT NULL AND g.deletedAt IS NOT NULL)
        )');
    } else {
        // Active tab: only show variants that are active and whose attributes are not archived
        $qb->andWhere('v.deletedAt IS NULL')
           ->andWhere('(c.id IS NULL OR c.deletedAt IS NULL)')
           ->andWhere('(st.id IS NULL OR st.deletedAt IS NULL)')
           ->andWhere('(g.id IS NULL OR g.deletedAt IS NULL)');
    }

    // Apply filters
    if (!empty($filters['sku'])) {
        $qb->andWhere('v.sku LIKE :sku')
            ->setParameter('sku', '%' . $filters['sku'] . '%');
    }

    if (!empty($filters['color'])) {
        $qb->andWhere('c.name = :colorName')
            ->setParameter('colorName', $filters['color']);
    }

    if (!empty($filters['stock'])) {
        switch ($filters['stock']) {
            case 'in':
                $qb->andWhere('v.stock > 10');
                break;
            case 'low':
                $qb->andWhere('v.stock > 0 AND v.stock <= 10');
                break;
            case 'out':
                $qb->andWhere('v.stock = 0');
                break;
        }
    }

    // Create a separate query builder for the total count to avoid issues with pagination clauses
    $countQb = clone $qb;
    $total = (int) $countQb->select('count(v.id)')->getQuery()->getSingleScalarResult();

    // Now, get the paginated data
    $data = $qb->orderBy('v.createdAt', 'DESC')
        ->setFirstResult(($page - 1) * $pageSize)
        ->setMaxResults($pageSize)
        ->getQuery()
        ->getResult();

    $pages = (int) ceil($total / $pageSize);

    return [
        'data' => $data,
        'total' => $total,
        'pages' => $pages,
    ];
}

public function findUniqueColorsByProduct(int $productId): array
{
    return $this->createQueryBuilder('v')
        ->select('DISTINCT c.name')
        ->join('v.color', 'c')
        ->where('v.product = :productId')
        ->andWhere('c.name IS NOT NULL')
        ->setParameter('productId', $productId)
        ->orderBy('c.name', 'ASC')
        ->getQuery()
        ->getResult();
}

}
