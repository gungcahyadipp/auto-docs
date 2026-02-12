<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData;

use Illuminate\Contracts\Validation\InvokableRule as InvokableRuleContract;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Spatie\LaravelData\Attributes\Validation\ObjectValidationAttribute;
use Spatie\LaravelData\Exceptions\CannotResolveRouteParameterReference;
use Spatie\LaravelData\Support\Validation\RuleDenormalizer as BaseRuleDenormalizer;
use Spatie\LaravelData\Support\Validation\ValidationPath;

class RuleDenormalizer extends BaseRuleDenormalizer
{
    /** @return array<string|object|RuleContract|InvokableRuleContract> */
    public function execute(mixed $rule, ValidationPath $path): array
    {
        if ($rule instanceof ObjectValidationAttribute) {
            try {
                return parent::execute($rule, $path);
            } catch (CannotResolveRouteParameterReference) {
                return [];
            }
        }

        return parent::execute($rule, $path);
    }
}
