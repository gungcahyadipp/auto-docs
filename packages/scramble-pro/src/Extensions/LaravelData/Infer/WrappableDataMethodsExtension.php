<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Infer;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelData\TemplateTypesLocator;
use Spatie\LaravelData\Contracts\WrappableData;
use Spatie\LaravelData\Support\Wrapping\WrapType;

class WrappableDataMethodsExtension implements MethodReturnTypeExtension
{
    public function __construct(private TemplateTypesLocator $templateTypesLocator) {}

    public function shouldHandle(ObjectType $type): bool
    {
        if (! $type instanceof Generic) {
            return false;
        }

        return $type->isInstanceOf(WrappableData::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'wrap' => $this->handleWrapCall($event),
            'withoutWrapping' => $this->handleWithoutWrappingCall($event),
            'getWrap' => $this->handleGetWrapCall($event),
            default => null,
        };
    }

    private function handleWrapCall(MethodCallEvent $event)
    {
        $valueType = $event->getArg('key', 0);

        if (! $valueType instanceof LiteralStringType) {
            return null;
        }

        /** @var Generic $type */
        $type = $event->getInstance();

        if (! $dataContextType = $this->getDataContextType($event)) {
            return null;
        }

        $dataContextType->templateTypes[4 /* TWrap */]->templateTypes[1 /* TKey */] = $valueType;

        return $type;
    }

    private function handleWithoutWrappingCall(MethodCallEvent $event)
    {
        /** @var Generic $type */
        $type = $event->getInstance();

        if (! $dataContextType = $this->getDataContextType($event)) {
            return null;
        }

        // @todo LiteralEnumType?
        $dataContextType->templateTypes[4 /* TWrap */]->templateTypes[0 /* TType */] = new LiteralStringType(WrapType::Disabled->value);

        return $type;
    }

    private function handleGetWrapCall(MethodCallEvent $event)
    {
        if (! $dataContextType = $this->getDataContextType($event)) {
            return null;
        }

        return $dataContextType->templateTypes[4 /* TWrap */];
    }

    private function getDataContextType(MethodCallEvent $event)
    {
        $definition = app(Infer::class)->analyzeClass($event->getInstance()->name);

        return $this->templateTypesLocator->findDataContextTemplateType($definition, $event->getInstance(), 'TDataContext');
    }
}
