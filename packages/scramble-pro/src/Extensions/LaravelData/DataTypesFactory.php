<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Union;
use Spatie\LaravelData\Support\Transformation\DataContext;
use Spatie\LaravelData\Support\Wrapping\Wrap;
use Spatie\LaravelData\Support\Wrapping\WrapType;

class DataTypesFactory
{
    public function makeDataContextType(): Generic
    {
        return new Generic(DataContext::class, [
            /* TIncludePartials */ new MixedType,
            /* TExcludePartials */ new MixedType,
            /* TOnlyPartials */ new MixedType,
            /* TExceptPartials */ new MixedType,
            /* TWrap */ new Generic(Wrap::class, [
                /* TType */ new ObjectType(WrapType::class),
                /* TKey */ new Union([new StringType, new NullType]),
            ]),
        ]);
    }
}
