<?php

namespace App\Validator;

use App\Entity\ProductVariant;
use App\Repository\ProductVariantRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class UniqueSkuValidator extends ConstraintValidator
{
    public function __construct(private readonly ProductVariantRepository $repository)
    {
    }

    /**
     * @param string|null $value
     * @param UniqueSku   $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueSku) {
            throw new UnexpectedTypeException($constraint, UniqueSku::class);
        }

        // Allow null/empty: use other constraints (e.g. NotBlank) to enforce presence
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // The object under validation should be a ProductVariant
        $object = $this->context->getObject();
        $currentId = null;
        if ($object instanceof ProductVariant) {
            $currentId = $object->getId();
        }

        // Check if another active (not soft-deleted) variant already uses this SKU
        $qb = $this->repository->createQueryBuilder('v')
            ->andWhere('v.sku = :sku')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('sku', $value)
            ->setMaxResults(1);

        if ($currentId !== null) {
            $qb->andWhere('v.id != :currentId')->setParameter('currentId', $currentId);
        }

        $conflict = $qb->getQuery()->getOneOrNullResult();

        if ($conflict !== null) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}

