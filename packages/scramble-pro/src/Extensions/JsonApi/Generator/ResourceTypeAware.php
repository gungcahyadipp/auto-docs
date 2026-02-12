<?php

namespace Dedoc\ScramblePro\Extensions\JsonApi\Generator;

use Dedoc\Scramble\Support\Generator\Types as OpenApiType;
use Dedoc\Scramble\Support\Helpers\JsonResourceHelper;
use Dedoc\Scramble\Support\Type\ObjectType;
use Throwable;

/**
 * @mixin JsonApiResourceToSchemaExtension|ResourceIdentifierToSchemaExtension
 */
trait ResourceTypeAware
{
    private function getTypeOfResourceType(ObjectType $type): OpenApiType\StringType
    {
        try {
            $resourceClass = $type->name;

            $modelType = JsonResourceHelper::modelType($this->infer->analyzeClass($type->name));

            if (! $modelType instanceof ObjectType) {
                return new OpenApiType\StringType;
            }

            $modelClass = $modelType->name;

            return (new OpenApiType\StringType)
                ->enum([
                    (new $resourceClass(new $modelClass))->toType(request()), // @phpstan-ignore method.notFound
                ]);
        } catch (Throwable) {
            // Anything may go wrong here, but that is fine, we'll just fall back to string.
        }

        return new OpenApiType\StringType;
    }
}
