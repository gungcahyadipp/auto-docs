<?php

namespace Dedoc\ScramblePro\Extensions\JsonApi\Generator;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\Type;
use TiMacDonald\JsonApi\Link;

class LinkToSchemaExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type->isInstanceOf(Link::class);
    }

    public function toSchema(Type $type): ObjectType
    {
        return (new ObjectType)
            ->addProperty('href', (new StringType)->format('uri'))
            ->addProperty('rel', new StringType)
            ->addProperty('describedby', new StringType)
            ->addProperty('title', new StringType)
            ->addProperty('type', new StringType)
            ->addProperty('hreflang', (new AnyOf)->setItems([new StringType, (new ArrayType)->setItems(new StringType)]))
            ->addProperty('meta', new ObjectType)
            ->setRequired(['href']);
    }

    public function reference(\Dedoc\Scramble\Support\Type\ObjectType $type): Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }
}
