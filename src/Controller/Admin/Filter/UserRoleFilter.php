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
        $property = sprintf('%s.%s', $alias, $filterDataDto->getProperty());

        // Normalize into array of choices
        $values = is_array($selectedValues) ? array_values($selectedValues) : [$selectedValues];
        $values = array_filter($values, static fn($v) => !empty($v));
        if (empty($values)) {
            return;
        }

        $selectsAdmin = in_array('ROLE_ADMIN', $values, true);
        $selectsUser  = in_array('ROLE_USER', $values, true);

        // If both Admin and User are selected (or ambiguous), do not restrict
        if (($selectsAdmin && $selectsUser) || (count($values) > 1 && !$selectsAdmin && !$selectsUser)) {
            return;
        }

        // When filtering Admins: roles JSON contains "ROLE_ADMIN"
        if ($selectsAdmin && !$selectsUser) {
            // MySQL 8+: JSON_CONTAINS(roles, '"ROLE_ADMIN"') = 1
            $queryBuilder
                ->andWhere(sprintf("FUNCTION('JSON_CONTAINS', %s, :json_admin) = 1", $property))
                ->setParameter('json_admin', '"ROLE_ADMIN"');
            return;
        }

        // When filtering Users: users WITHOUT ROLE_ADMIN
        // JSON_CONTAINS(..., '"ROLE_ADMIN"') = 0 OR roles IS NULL OR roles = []
        if ($selectsUser && !$selectsAdmin) {
            $orX = $queryBuilder->expr()->orX();
            $orX->add(sprintf("FUNCTION('JSON_CONTAINS', %s, :json_admin) = 0", $property));
            $orX->add($queryBuilder->expr()->isNull($property));
            $orX->add($queryBuilder->expr()->eq($property, ':empty_json'));

            $queryBuilder
                ->andWhere($orX)
                ->setParameter('json_admin', '"ROLE_ADMIN"')
                ->setParameter('empty_json', '[]');
        }
    }
}
