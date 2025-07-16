<?php

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class StockStatusFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOption('choices', [
                'In Stock' => 'in_stock',
                'Low Stock' => 'low_stock',
                'Out of Stock' => 'out_of_stock',
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        $alias = $filterDataDto->getEntityAlias();
        $expr = $queryBuilder->expr();
        $condition = null;

        switch ($value) {
            case 'in_stock':
                $condition = $expr->andX($expr->gte(sprintf('%s.quantityInStock', $alias), 10));
                break;
            case 'low_stock':
                $condition = $expr->andX(
                    $expr->gt(sprintf('%s.quantityInStock', $alias), 0),
                    $expr->lt(sprintf('%s.quantityInStock', $alias), 10)
                );
                break;
            case 'out_of_stock':
                $condition = $expr->andX($expr->eq(sprintf('%s.quantityInStock', $alias), 0));
                break;
        }

        if (null !== $condition) {
            // This is the crucial part. If no other filters are applied, we must
            // use where() instead of andWhere().
            if (empty($queryBuilder->getDQLPart('where'))) {
                $queryBuilder->where($condition);
            } else {
                $queryBuilder->andWhere($condition);
            }
        }
    }
}
