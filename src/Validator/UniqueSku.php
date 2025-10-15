<?php

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class UniqueSku extends Constraint
{
    public string $message = 'SKU already in use';

    public function validatedBy(): string
    {
        // Links this constraint to its validator service
        return UniqueSkuValidator::class;
    }
}
