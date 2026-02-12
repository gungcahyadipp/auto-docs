<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\Includes\IncludedCount;
use Spatie\QueryBuilder\Includes\IncludedExists;
use Spatie\QueryBuilder\Includes\IncludedRelationship;

class AllowedIncludeManager
{
    use ManagesProperties;

    const ORDERED_PROPERTY_TO_TEMPLATE_MAP = [
        'internalName' => 'TInternalName',
        'name' => 'TName',
        'includeClass' => 'TIncludeClass',
    ];

    public function createType(
        Type $internalName = new MixedType,
        Type $name = new MixedType,
        Type $includeClass = new ObjectType(IncludedRelationship::class),
    ) {
        if (count(func_get_args()) === 2 && $name instanceof LiteralStringType) {
            return $this->createIncludesFromString($internalName, $name);
        }

        return new Generic(AllowedInclude::class, [
            /* TInternalName */ $internalName,
            /* TName */ $name,
            /* TIncludeClass */ $includeClass,
        ]);
    }

    protected function createIncludesFromString(Type $internalName, LiteralStringType $name): KeyedArrayType
    {
        $includes = new KeyedArrayType([
            new ArrayItemType_(null, $this->createType($internalName, $name, new ObjectType(IncludedRelationship::class))),
        ]);

        $relationship = $name->value;

        if (! Str::contains($relationship, '.')) {
            /** @var string $countSuffix */
            $countSuffix = config('query-builder.count_suffix', 'Count');
            /** @var string $existsSuffix */
            $existsSuffix = config('query-builder.exists_suffix', 'Exists');

            $includes->items = array_merge($includes->items, [
                new ArrayItemType_(
                    null,
                    $this->createType($internalName, new LiteralStringType($relationship.$countSuffix), new ObjectType(IncludedCount::class)),
                ),
                new ArrayItemType_(
                    null,
                    $this->createType($internalName, new LiteralStringType($relationship.$existsSuffix), new ObjectType(IncludedExists::class)),
                ),
            ]);
        }

        return $includes;
    }
}
