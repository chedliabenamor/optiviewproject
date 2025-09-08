<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 *
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findProductsForOfferQueryBuilder(int $offerId): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.productOffers', 'po')
            ->where('po.id = :offerId')
            ->setParameter('offerId', $offerId);
    }

    /**
     * @param \App\Entity\Category $category
     * @return Product[]
     */
    public function findActiveByCategory(\App\Entity\Category $category): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.category = :category')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('category', $category)
            ->getQuery()->getResult();
    }

    /**
     * @param \App\Entity\Category $category
     * @return Product[]
     */
    public function findArchivedByCategory(\App\Entity\Category $category): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.category = :category')
            ->andWhere('p.deletedAt IS NOT NULL')
            ->setParameter('category', $category)
            ->getQuery()->getResult();
    }
}
