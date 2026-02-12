<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Infer;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Infer\ShallowFunctionDefinition;
use Spatie\LaravelData\Lazy;

class LazyDefinitionExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === Lazy::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $definition->methods['defaultIncluded'] = $this->getDefaultIncludedDefinition();
    }

    private function getDefaultIncludedDefinition()
    {
        $templates = [
            $defaultIncludedType = new TemplateType('TSetDefaultIncluded'),
        ];

        $definition = new ShallowFunctionDefinition(
            type: $type = new FunctionType(
                name: 'defaultIncluded',
                arguments: ['defaultIncluded' => $defaultIncludedType],
                returnType: new SelfType(Lazy::class),
            ),
            argumentsDefaults: [
                'defaultIncluded' => new LiteralBooleanType(true),
            ],
            definingClassName: Lazy::class,
            selfOutType: new Generic('self', [$defaultIncludedType]),
        );
        $definition->isFullyAnalyzed = true;

        $type->templates = $templates;

        return $definition;
    }
}
