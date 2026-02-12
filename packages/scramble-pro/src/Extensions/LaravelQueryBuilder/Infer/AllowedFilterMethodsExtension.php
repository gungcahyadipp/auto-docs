<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Infer;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers\AllowedFilterManager;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Filters\FiltersCallback;
use Spatie\QueryBuilder\Filters\FiltersOperator;
use Spatie\QueryBuilder\Filters\FiltersTrashed;

class AllowedFilterMethodsExtension implements MethodReturnTypeExtension, StaticMethodReturnTypeExtension
{
    public function __construct(private AllowedFilterManager $allowedFilterManager) {}

    public function shouldHandle(string|ObjectType $type): bool
    {
        if (is_string($type)) {
            return is_a($type, AllowedFilter::class, true);
        }

        return $type->isInstanceOf(AllowedFilter::class);
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        return match ($event->getName()) {
            'exact', 'partial', 'beginsWithStrict', 'endsWithStrict', 'belongsTo', 'scope' => $this->allowedFilterManager->createType(
                name: $event->getArg('name', 0),
                internalName: $event->getArg('internalName', 1, new NullType),
            ),
            'callback' => $this->allowedFilterManager->createType(
                name: $event->getArg('name', 0),
                filterClass: new Generic(FiltersCallback::class, [$event->getArg('callback', 1)]),
                internalName: $event->getArg('internalName', 2, new NullType),
            ),
            'custom' => $this->allowedFilterManager->createType(
                name: $event->getArg('name', 0),
                filterClass: $event->getArg('filterClass', 1),
                internalName: $event->getArg('internalName', 2, new NullType),
            ),
            'operator' => $this->allowedFilterManager->createType(
                name: $event->getArg('name', 0),
                filterClass: new Generic(FiltersOperator::class, [
                    $event->getArg('addRelationConstraint', 4, new LiteralBooleanType(true)),
                    $event->getArg('filterOperator', 1),
                    $event->getArg('boolean', 2, new LiteralStringType('and')),
                ]),
                internalName: $event->getArg('internalName', 3, new NullType),
            ),
            'trashed' => $this->allowedFilterManager->createType(
                name: $event->getArg('name', 0, new LiteralStringType('trashed')),
                filterClass: new ObjectType(FiltersTrashed::class),
                internalName: $event->getArg('internalName', 1, new NullType),
            ),
            default => null,
        };
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'default' => $this->allowedFilterManager->withPropertiesTypes($event->getInstance(), [
                'default' => $event->getArg('value', 0),
                'hasDefault' => new LiteralBooleanType(true),
            ]),
            'unsetDefault' => $this->allowedFilterManager->withPropertiesTypes($event->getInstance(), [
                'default' => new MixedType,
                'hasDefault' => new LiteralBooleanType(false),
            ]),
            'nullable' => $this->allowedFilterManager->withPropertiesTypes($event->getInstance(), [
                'nullable' => $event->getArg('nullable', 0, new LiteralBooleanType(true)),
            ]),
            'ignore' => $this->allowedFilterManager->withPropertiesTypes($event->getInstance(), [
                'ignored' => $event->getArg('values', 0),
            ]),
            default => null,
        };
    }
}
