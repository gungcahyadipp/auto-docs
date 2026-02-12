<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Infer;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\TemplateType;
use Spatie\LaravelData\Contracts\BaseDataCollectable;

class AfterBaseDataCollectableClassDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    use AddsOrderedTemplates;

    public function shouldHandle(string $name): bool
    {
        return is_a($name, BaseDataCollectable::class, true);
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $classDefinition = $event->classDefinition;

        // Makes sure that template types are ordered consistently in class definition, so handling code can rely on this order.
        $this->addOrderedTemplateTypes(
            $classDefinition->templateTypes,
            [
                new TemplateType('TKey'),
                new TemplateType('TValue'),
                new TemplateType('TDataContext'),
            ]
        );

        if ($this->hasAlreadyHandled($classDefinition)) {
            return;
        }

        app(Infer::class)->index->registerClassDefinition($classDefinition);
    }

    private function hasAlreadyHandled(ClassDefinition $classDefinition)
    {
        $keyedTemplates = collect($classDefinition->templateTypes)->keyBy('name');

        return $keyedTemplates->has('TKey') && $keyedTemplates->has('TValue');
    }
}
