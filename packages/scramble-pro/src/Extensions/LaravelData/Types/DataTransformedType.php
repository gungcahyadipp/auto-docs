<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Types;

use Dedoc\Scramble\Support\Type\AbstractType;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Symfony\Component\HttpFoundation\Response;

/**
 * This is the synthetic type that comes out when `toJson`, `toArray`, or `toResponse` methods are
 * called on data instances. It exists so the return type annotations in methods will not override the
 * resulting type.
 *
 * @todo This may be not the best solution. The more generic approach can be used of specifying that some types are "castable" to other types.
 */
class DataTransformedType extends AbstractType
{
    public function __construct(
        public readonly Generic $type,
    ) {}

    public function acceptedBy(Type $type): bool
    {
        return $type instanceof StringType
            || $type instanceof ArrayType
            || $type instanceof KeyedArrayType
            || ($type instanceof ObjectType && $type->isInstanceOf(Response::class));
    }

    public function isSame(Type $type)
    {
        return false;
    }

    public function toString(): string
    {
        return 'transformed:'.$this->type->toString();
    }
}
