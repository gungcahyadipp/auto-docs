<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Validation\ValidationPath;

class DataValidationRulesResolver extends \Spatie\LaravelData\Resolvers\DataValidationRulesResolver
{
    protected function shouldSkipPropertyValidation(DataProperty $dataProperty, array $fullPayload, ValidationPath $propertyPath): bool
    {
        if ($dataProperty->type->kind->isDataObject()) {
            return true;
        }

        return false;
    }
}
