<?php

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class UniqueSku extends Constraint
{
    public string $message = 'The SKU "{{ value }}" is already in use. Please choose a different SKU.';

    public function validatedBy(): string
    {
        // Links this constraint to its validator service
        return UniqueSkuValidator::class;
    }
}
