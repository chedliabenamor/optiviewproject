<?php

namespace App\Repository;

use App\Entity\ProductVariantImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductVariantImage>
 *
 * @method ProductVariantImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductVariantImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductVariantImage[]    findAll()
 * @method ProductVariantImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductVariantImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariantImage::class);
    }

    //    /**
    //     * @return ProductVariantImage[] Returns an array of ProductVariantImage objects
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

    //    public function findOneBySomeField($value): ?ProductVariantImage
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
