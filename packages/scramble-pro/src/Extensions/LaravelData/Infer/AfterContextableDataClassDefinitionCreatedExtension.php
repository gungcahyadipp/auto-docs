<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Infer;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\FunctionLikeType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\ScramblePro\Extensions\LaravelData\DataTypesFactory;
use Spatie\LaravelData\Contracts\ContextableData;

class AfterContextableDataClassDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    use AddsOrderedTemplates;

    public function __construct(private DataTypesFactory $dataTypesFactory) {}

    public function shouldHandle(string $name): bool
    {
        return is_a($name, ContextableData::class, true);
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $classDefinition = $event->classDefinition;

        // Makes sure that template types are ordered consistently in class definition, so handling code can rely on this order.
        $this->addOrderedTemplateTypes(
            $classDefinition->templateTypes,
            [new TemplateType('TDataContext')],
        );

        $dataContextType = collect($classDefinition->templateTypes)->first(fn (TemplateType $t) => $t->name === 'TDataContext');

        $classDefinition->properties['_dataContext'] = new ClassPropertyDefinition(
            $dataContextType,
            $this->createDataContextType($classDefinition),
        );
    }

    public function createDataContextType(Infer\Definition\ClassDefinition $definition): Generic
    {
        $dataContextType = $this->dataTypesFactory->makeDataContextType();

        if ($definition->hasMethodDefinition('includeProperties')) {
            // @phpstan-ignore property.nonObject
            $dataContextType->templateTypes[0 /* TIncludePartials */] = $this->getPartialProperties($definition->getMethodDefinition('includeProperties')->type);
        }

        if ($definition->hasMethodDefinition('excludeProperties')) {
            // @phpstan-ignore property.nonObject
            $dataContextType->templateTypes[1 /* TExcludePartials */] = $this->getPartialProperties($definition->getMethodDefinition('excludeProperties')->type);
        }

        if ($definition->hasMethodDefinition('onlyProperties')) {
            // @phpstan-ignore property.nonObject
            $dataContextType->templateTypes[2 /* TOnlyPartials */] = $this->getPartialProperties($definition->getMethodDefinition('onlyProperties')->type);
        }

        if ($definition->hasMethodDefinition('exceptProperties')) {
            // @phpstan-ignore property.nonObject
            $dataContextType->templateTypes[3 /* TExceptPartials */] = $this->getPartialProperties($definition->getMethodDefinition('exceptProperties')->type);
        }

        return $dataContextType;
    }

    private function getPartialProperties(FunctionLikeType $type): KeyedArrayType|MixedType
    {
        $returnType = $type->getReturnType();

        if (! $returnType instanceof KeyedArrayType) {
            return new MixedType;
        }

        return new KeyedArrayType(array_map(
            fn (ArrayItemType_ $t) => new ArrayItemType_(
                key: null,
                value: $t->value instanceof LiteralStringType
                    ? $t->value
                    : tap(new LiteralStringType($t->key), fn ($t) => $t->setAttribute('conditional', true))
            ),
            $returnType->items,
        ));
    }
}
