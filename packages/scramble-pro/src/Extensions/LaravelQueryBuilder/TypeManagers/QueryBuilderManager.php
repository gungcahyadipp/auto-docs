<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\Type;
use Spatie\QueryBuilder\QueryBuilder;

class QueryBuilderManager
{
    use ManagesProperties;

    const ORDERED_PROPERTY_TO_TEMPLATE_MAP = [
        'request' => 'TRequest',
        'subject' => 'TSubject',
        'allowedFilters' => 'TAllowedFilters',
        'allowedSorts' => 'TAllowedSorts',
        'allowedIncludes' => 'TAllowedIncludes',
        'allowedFields' => 'TAllowedFields',
        'defaultSorts' => 'TDefaultSorts',
    ];

    public function createType(
        Type $request = new MixedType,
        Type $subject = new MixedType,
        Type $allowedFilters = new MixedType,
        Type $allowedSorts = new MixedType,
        Type $allowedIncludes = new MixedType,
        Type $allowedFields = new MixedType,
        Type $defaultSorts = new MixedType,
    ) {
        return new Generic(QueryBuilder::class, [
            /* TRequest */ $request,
            /* TSubject */ $subject,
            /* TAllowedFilters */ $allowedFilters,
            /* TAllowedSorts */ $allowedSorts,
            /* TAllowedIncludes */ $allowedIncludes,
            /* TAllowedFields */ $allowedFields,
            /* TDefaultSorts */ $defaultSorts,
        ]);
    }
}
