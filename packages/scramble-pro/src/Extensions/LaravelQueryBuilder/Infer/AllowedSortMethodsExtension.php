<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Infer;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers\AllowedSortManager;
use Spatie\QueryBuilder\AllowedSort;

class AllowedSortMethodsExtension implements MethodReturnTypeExtension, StaticMethodReturnTypeExtension
{
    public function __construct(private AllowedSortManager $allowedSortManager) {}

    public function shouldHandle(string|ObjectType $type): bool
    {
        if (is_string($type)) {
            return is_a($type, AllowedSort::class, true);
        }

        return $type->isInstanceOf(AllowedSort::class);
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        return match ($event->getName()) {
            'custom', 'callback', 'field' => $this->allowedSortManager->createType(
                name: $event->getArg('name', 0),
            ),
            default => null,
        };
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'ignore' => $this->allowedSortManager->withPropertiesTypes($event->getInstance(), [
                'ignored' => $event->getArg('values', 0),
            ]),
            'defaultDirection' => $this->allowedSortManager->withPropertiesTypes($event->getInstance(), [
                'defaultDirection' => $event->getArg('direction', 0),
            ]),
            default => null,
        };
    }
}
