<?php

namespace Dedoc\ScramblePro\Extensions\JsonApi\Generator;

use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResourceResponseTypeToSchema;
use TiMacDonald\JsonApi\JsonApiResource;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class JsonApiResourceResponseToSchemaExtension extends ResourceResponseTypeToSchema
{
    use HandlesJsonApiResourceResponse;

    public function shouldHandle(Type $type): bool
    {
        return parent::shouldHandle($type)
            && (
                $type->templateTypes[0]->isInstanceOf(JsonApiResource::class)
                || $type->templateTypes[0]->isInstanceOf(JsonApiResourceCollection::class)
            );
    }
}
