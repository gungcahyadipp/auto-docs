<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;

class AllowedSortManager
{
    use ManagesProperties;

    const ORDERED_PROPERTY_TO_TEMPLATE_MAP = [
        'defaultDirection' => 'TDefaultDirection',
        'internalName' => 'TInternalName',
        'name' => 'TName',
        'sortClass' => 'TSortClass',
    ];

    public function createType(
        Type $defaultDirection = new MixedType,
        Type $internalName = new MixedType,
        Type $name = new MixedType,
        Type $sortClass = new MixedType,
    ) {
        return new Generic(AllowedSort::class, [
            /* TDefaultDirection */ $defaultDirection,
            /* TInternalName */ $internalName,
            /* TName */ $name,
            /* TSortClass */ $sortClass,
        ]);
    }

    public function createDefaultSort(LiteralStringType $name): Generic
    {
        $direction = Str::startsWith($name->value, '-') ? SortDirection::DESCENDING : SortDirection::ASCENDING;

        return $this->createType(
            defaultDirection: new LiteralStringType($direction),
            name: new LiteralStringType(Str::replaceFirst('-', '', $name->value)),
        );
    }
}
