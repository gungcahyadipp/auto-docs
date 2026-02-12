<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Generator;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelData\Types\DataTransformedType;

class DataTransformedSchemaExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof DataTransformedType;
    }

    /**
     * @param  DataTransformedType  $type
     */
    public function toSchema(Type $type)
    {
        return $this->openApiTransformer->transform($type->type);
    }

    public function toResponse(Type $type)
    {
        return $this->openApiTransformer->toResponse($type->type);
    }
}
