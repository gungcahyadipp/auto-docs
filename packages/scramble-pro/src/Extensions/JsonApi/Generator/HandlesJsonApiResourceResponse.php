<?php

namespace Dedoc\ScramblePro\Extensions\JsonApi\Generator;

use Dedoc\Scramble\Support\Type as InferType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Dedoc\ScramblePro\Extensions\JsonApi\Utils\JsonApiResourceReflection;
use TiMacDonald\JsonApi\JsonApiResource;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

/**
 * @see JsonApiResourceCollection
 * @see JsonApiResource
 *
 * @mixin JsonApiResourceResponseToSchemaExtension|JsonApiPaginatedResourceResponseToSchemaExtension
 */
trait HandlesJsonApiResourceResponse
{
    protected function getWithType(ObjectType $type): ?InferType\KeyedArrayType
    {
        if ($this->isUserDefinedWithMethod($type)) {
            return parent::getWithType($type);
        }

        if (! $collectedResource = $this->getResourceType($type)) {
            return null;
        }

        if (! $includedResources = $this->getIncludedResources($collectedResource)) {
            return null;
        }

        return new InferType\KeyedArrayType([
            new InferType\ArrayItemType_('included', $includedResources, isOptional: true),
        ]);
    }

    protected function isUserDefinedWithMethod(ObjectType $resource): bool
    {
        $resourceDefinition = $this->infer->index->getClass($resource->name);

        $withDefiningClassName = $resourceDefinition?->getMethod('with')?->definingClassName;

        return $withDefiningClassName !== JsonApiResourceCollection::class
            && $withDefiningClassName !== JsonApiResource::class;
    }

    protected function getIncludedResources(ObjectType $resource): ?InferType\ArrayType
    {
        if (! $relationshipsType = JsonApiResourceReflection::createForClass($resource->name)->getRelationshipsType()) {
            return null;
        }

        $types = collect();
        (new InferType\TypeWalker)
            ->walk($relationshipsType, function (Type $t) use (&$types) {
                if ($t->isInstanceOf(JsonApiResource::class)) {
                    $types->push($t);
                }

                // @todo Ideally, the check before should catch collected type but for now it lives as either literal string type or as an object, so explicit handling is needed.
                if ($t instanceof ObjectType && $t->isInstanceOf(JsonApiResourceCollection::class)) {
                    $types->push(
                        ResourceCollectionTypeManager::make($t)->getCollectedType()
                    );
                }
            });

        if (! $included = $types->values()->all()) {
            return null;
        }

        return new InferType\ArrayType(InferType\Union::wrap($included));
    }

    public function getResourceType(InferType\ObjectType $type): ?ObjectType
    {
        if ($type->isInstanceOf(JsonApiResourceCollection::class)) {
            $collectedType = ResourceCollectionTypeManager::make($type)->getCollectedType();

            return $collectedType instanceof Generic ? $collectedType : null;
        }

        return $type;
    }
}
