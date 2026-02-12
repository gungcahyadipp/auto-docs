<?php

namespace Dedoc\ScramblePro\Extensions\JsonApi\Infer;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\InferExtensions\ShallowFunctionDefinition;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\StaticReference;
use Dedoc\Scramble\Support\Type\TemplateType;
use TiMacDonald\JsonApi\JsonApiResource;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class AfterJsonApiResourceDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === JsonApiResource::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $definition->methods['newCollection'] = $this->buildNewCollectionMethodDefinition();
    }

    private function buildNewCollectionMethodDefinition(): ShallowFunctionDefinition
    {
        $templates = [
            $tResource1 = new TemplateType('TResource1'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'newCollection',
                arguments: [
                    'resource' => $tResource1,
                ],
                returnType: new Generic(JsonApiResourceCollection::class, [
                    $tResource1,
                    new ArrayType,
                    new ObjectType(StaticReference::STATIC),
                ]),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: JsonApiResource::class,
            isStatic: true,
        );
    }
}
