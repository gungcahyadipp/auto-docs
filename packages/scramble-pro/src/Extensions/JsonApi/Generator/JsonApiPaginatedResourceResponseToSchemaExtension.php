<?php

namespace Dedoc\ScramblePro\Extensions\JsonApi\Generator;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\PaginatedResourceResponseTypeToSchema;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class JsonApiPaginatedResourceResponseToSchemaExtension extends PaginatedResourceResponseTypeToSchema
{
    use HandlesJsonApiResourceResponse;

    public function shouldHandle(Type $type): bool
    {
        return parent::shouldHandle($type)
            && $type->templateTypes[0]->isInstanceOf(JsonApiResourceCollection::class);
    }

    /**
     * @see JsonApiResourceCollection::paginationInformation()
     */
    protected function getDefaultPaginationInformationArray(Generic $type): KeyedArrayType
    {
        $default = parent::getDefaultPaginationInformationArray($type);

        $paginationInformation = $this->getPaginationInformationMethod($type->templateTypes[0]); // @phpstan-ignore argument.type

        if ($paginationInformation) {
            return $default;
        }

        $links = $default->getItemValueTypeByKey('links');
        if ($links instanceof KeyedArrayType) {
            foreach ($links->items as $link) {
                $link->value = new StringType;
                $link->isOptional = true;
            }
        }

        return $default;
    }

    protected function isUserDefinedPaginationInformationMethod(FunctionLikeDefinition $method): bool
    {
        return $method->definingClassName !== JsonApiResourceCollection::class;
    }
}
