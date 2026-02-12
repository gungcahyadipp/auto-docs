<?php

namespace Dedoc\ScramblePro\Extensions\JsonApi\Generator;

use Dedoc\Scramble\Support\Generator\Types as OpenApiType;
use Dedoc\Scramble\Support\Type as InferType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResourceCollectionTypeToSchema;
use Illuminate\Http\JsonResponse;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class JsonApiResourceCollectionToSchemaExtension extends ResourceCollectionTypeToSchema
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof InferType\Generic
            && $type->isInstanceOf(JsonApiResourceCollection::class)
            && count($type->templateTypes) >= 2;
    }

    /**
     * @param  InferType\Generic  $type
     */
    public function toSchema(Type $type): ?OpenApiType\Type
    {
        $valueType = $this->getResourceType($type);

        if (! $valueType) {
            return null;
        }

        return $this->openApiTransformer->transform(new InferType\ArrayType($valueType));
    }

    protected function getResponseType(ObjectType $type): Type
    {
        return new Generic(JsonResponse::class, [
            parent::getResponseType($type),
            new InferType\UnknownType,
            new InferType\KeyedArrayType([
                new InferType\ArrayItemType_('Content-type', new InferType\Literal\LiteralStringType('application/vnd.api+json')),
            ]),
        ]);
    }

    public function getResourceType(InferType\Generic $type): ?ObjectType
    {
        $collectedType = (new ResourceCollectionTypeManager($type, $this->infer->index))->getCollectedType();

        return $collectedType instanceof Generic ? $collectedType : null;
    }
}
