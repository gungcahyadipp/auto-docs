<?php

namespace Dedoc\ScramblePro\Extensions\JsonApi\Generator;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types as OpenApiType;
use Dedoc\Scramble\Support\Type as InferType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

class ResourceIdentifierToSchemaExtension extends TypeToSchemaExtension
{
    use ResourceTypeAware;

    const SYNTHETIC_IDENTIFIER_CLASS = '$$JSON_API_RESOURCE_IDENTIFIER';

    public function shouldHandle(Type $type): bool
    {
        return $type instanceof InferType\Generic
            && $type->name === self::SYNTHETIC_IDENTIFIER_CLASS
            && count($type->templateTypes) === 1
            && $type->templateTypes[0] instanceof ObjectType;
    }

    /**
     * @param  InferType\Generic  $type
     */
    public function toSchema(Type $type): ?OpenApiType\Type
    {
        if (! $type->templateTypes[0] instanceof ObjectType) {
            return null;
        }

        return (new OpenApiType\ObjectType)
            ->addProperty('type', $this->getTypeOfResourceType($type->templateTypes[0]))
            ->addProperty('id', new OpenApiType\StringType)
            ->setRequired(['type', 'id']);
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference('schemas', $type->templateTypes[0]->name.'Identifier', $this->components); // @phpstan-ignore property.notFound
    }
}
