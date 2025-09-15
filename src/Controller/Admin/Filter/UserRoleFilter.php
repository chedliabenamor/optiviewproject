<?php

namespace App\Controller\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UserRoleFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName = 'roles', $label = 'Role'): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOptions([
                'choices' => [
                    'Administrator' => 'ROLE_ADMIN',
                    'User' => 'ROLE_USER',
                ],
                'multiple' => true,
                'required' => false,
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $data = $filterDataDto->getValue();
        // Accept various shapes: ['value'=>...] or simple array/string
        if (is_array($data) && array_key_exists('value', $data)) {
            $selectedValues = $data['value'];
        } else {
            $selectedValues = $data;
        }
        if (empty($selectedValues)) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $orX = $queryBuilder->expr()->orX();

        // Support array or single value
        $values = is_array($selectedValues) ? $selectedValues : [$selectedValues];
        foreach (array_values($values) as $i => $role) {
            if (!$role) { continue; }
            $param = sprintf('role_%d', $i);
            $orX->add($queryBuilder->expr()->like(sprintf('%s.%s', $alias, $filterDataDto->getProperty()), ":$param"));
            $queryBuilder->setParameter($param, '%"' . $role . '"%');
        }

        if (method_exists($orX, 'count') ? $orX->count() > 0 : true) {
            $queryBuilder->andWhere($orX);
        }
    }
}
