<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData;

use Dedoc\Scramble\Infer\Contracts\ClassDefinition;
use Dedoc\Scramble\Support\Generator\Types\Type as JsonSchema;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Exception;
use ReflectionClass;
use Spatie\LaravelData\Contracts\PropertyMorphableData;

trait TransformsPropertyMorphableData
{
    protected function isPropertyMorphableData(ClassDefinition $definition): bool
    {
        $name = $definition->getData()->name;

        return is_a($name, PropertyMorphableData::class, true)
            && (new ReflectionClass($definition->getData()->name))->isAbstract() // @phpstan-ignore argument.type
            && $definition->getMethod('morph');
    }

    protected function transformPropertyMorphableData(ClassDefinition $definition): JsonSchema
    {
        $resultingUnion = $this->getPossibleMorphTypes($definition);

        return $this->openApiTransformer->transform($resultingUnion);
    }

    protected function getPossibleMorphTypes(ClassDefinition $definition): Type
    {
        if (! $morphMethodDefinition = $definition->getMethod('morph')) {
            throw new Exception('Should not happen, `morph` existence is checked in `isPropertyMorphableData`');
        }

        $morphReturnType = $morphMethodDefinition->getReturnType();

        $types = $morphReturnType instanceof Union
            ? $morphReturnType->types
            : [$morphReturnType];

        $types = collect($types)
            ->filter(fn (Type $t) => $t instanceof GenericClassStringType)
            ->map(fn (GenericClassStringType $t) => new ObjectType($t->getValue()))
            ->values()
            ->all();

        return Union::wrap($types);
    }
}
