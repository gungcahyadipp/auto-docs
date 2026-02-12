<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\Type;
use Spatie\QueryBuilder\AllowedFilter;

class AllowedFilterManager
{
    use ManagesProperties;

    const ORDERED_PROPERTY_TO_TEMPLATE_MAP = [
        'internalName' => 'TInternalName',
        'ignored' => 'TIgnored',
        'default' => 'TDefault',
        'hasDefault' => 'THasDefault',
        'nullable' => 'TNullable',
        'name' => 'TName',
        'filterClass' => 'TFilterClass',
    ];

    public function createType(
        Type $internalName = new MixedType,
        Type $ignored = new MixedType,
        Type $default = new MixedType,
        Type $hasDefault = new MixedType,
        Type $nullable = new MixedType,
        Type $name = new MixedType,
        Type $filterClass = new MixedType,
    ) {
        return new Generic(AllowedFilter::class, [
            /* TInternalName */ $internalName,
            /* TIgnored */ $ignored,
            /* TDefault */ $default,
            /* THasDefault */ $hasDefault,
            /* TNullable */ $nullable,
            /* TName */ $name,
            /* TFilterClass */ $filterClass,
        ]);
    }
}
