<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Infer;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelData\TemplateTypesLocator;
use Dedoc\ScramblePro\Utils;
use Illuminate\Support\Str;
use Spatie\LaravelData\Contracts\IncludeableData;

/**
 * @see IncludeableData
 * @see \Spatie\LaravelData\Concerns\IncludeableData
 */
class IncludeableDataMethodsExtension implements MethodReturnTypeExtension
{
    public function __construct(private TemplateTypesLocator $templateTypesLocator) {}

    public function shouldHandle(ObjectType $type): bool
    {
        // @todo non-collection
        if (! $type instanceof Generic) {
            return false;
        }

        return $type->isInstanceOf(IncludeableData::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'include', 'includePermanently', 'includeWhen' => $this->handleIncludeCall($event),
            'exclude', 'excludePermanently', 'excludeWhen' => $this->handleExcludeCall($event),
            'only', 'onlyPermanently', 'onlyWhen' => $this->handleOnlyCall($event),
            'except', 'exceptPermanently', 'exceptWhen' => $this->handleExceptCall($event),
            default => null,
        };
    }

    private function getNormalizedPartialsType(MethodCallEvent $event, Type $partialType)
    {
        $partials = Utils::getNormalizedArgumentTypes($event->arguments->all());

        $partials = collect($partials)
            ->filter(fn ($t) => $t instanceof LiteralStringType)
            ->map(fn (LiteralStringType $t) => new ArrayItemType_(
                key: null,
                value: tap(clone $t, fn ($t) => $t->setAttribute('conditional', Str::endsWith($event->name, 'When'))),
            ))
            ->values()
            ->toArray();

        $originalItems = $partialType instanceof KeyedArrayType ? $partialType->items : [];

        return tap(
            new KeyedArrayType(array_merge($originalItems, $partials)),
            fn (KeyedArrayType $t) => $t->setAttribute('notOriginal', true),
        );
    }

    private function handleIncludeCall(MethodCallEvent $event)
    {
        if (! $dataContextType = $this->getDataContextType($event)) {
            return null;
        }

        $dataContextType->templateTypes[0 /* TIncludePartials */] = $this->getNormalizedPartialsType(
            $event,
            $dataContextType->templateTypes[0 /* TIncludePartials */],
        );

        return $event->instance;
    }

    private function handleExcludeCall(MethodCallEvent $event)
    {
        if (! $dataContextType = $this->getDataContextType($event)) {
            return null;
        }

        $dataContextType->templateTypes[1 /* TExcludePartials */] = $this->getNormalizedPartialsType(
            $event,
            $dataContextType->templateTypes[1 /* TExcludePartials */],
        );

        return $event->instance;
    }

    private function handleOnlyCall(MethodCallEvent $event)
    {
        if (! $dataContextType = $this->getDataContextType($event)) {
            return null;
        }

        $dataContextType->templateTypes[2 /* TOnlyPartials */] = $this->getNormalizedPartialsType(
            $event,
            $dataContextType->templateTypes[2 /* TOnlyPartials */],
        );

        return $event->instance;
    }

    private function handleExceptCall(MethodCallEvent $event)
    {
        if (! $dataContextType = $this->getDataContextType($event)) {
            return null;
        }

        $dataContextType->templateTypes[3 /* TExceptPartials */] = $this->getNormalizedPartialsType(
            $event,
            $dataContextType->templateTypes[3 /* TExceptPartials */],
        );

        return $event->instance;
    }

    private function getDataContextType(MethodCallEvent $event)
    {
        $definition = app(Infer::class)->analyzeClass($event->getInstance()->name);

        return $this->templateTypesLocator->findDataContextTemplateType($definition, $event->getInstance(), 'TDataContext');
    }
}
