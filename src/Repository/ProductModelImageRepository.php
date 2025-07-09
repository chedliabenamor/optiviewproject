<?php

namespace App\Repository;

use App\Entity\ProductModelImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductModelImage>
 *
 * @method ProductModelImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductModelImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductModelImage[]    findAll()
 * @method ProductModelImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductModelImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductModelImage::class);
    }
}
