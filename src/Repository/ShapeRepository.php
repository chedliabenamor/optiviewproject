<?php

namespace App\Repository;

use App\Entity\Shape;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Shape>
 *
 * @method Shape|null find($id, $lockMode = null, $lockVersion = null)
 * @method Shape|null findOneBy(array $criteria, array $orderBy = null)
 * @method Shape[]    findAll()
 * @method Shape[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShapeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shape::class);
    }
}
