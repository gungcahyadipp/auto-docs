<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Infer;

use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers\AllowedIncludeManager;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\Includes\IncludedCallback;
use Spatie\QueryBuilder\Includes\IncludedCount;
use Spatie\QueryBuilder\Includes\IncludedExists;

class AllowedIncludeMethodsExtension implements StaticMethodReturnTypeExtension
{
    public function __construct(private AllowedIncludeManager $allowedIncludeManager) {}

    public function shouldHandle(string $type): bool
    {
        return is_a($type, AllowedInclude::class, true);
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        return match ($event->getName()) {
            'relationship' => $this->allowedIncludeManager->createType(
                name: $event->getArg('name', 0),
                internalName: $event->getArg('internalName', 1, new NullType),
            ),
            'count' => $this->allowedIncludeManager->createType(
                name: $event->getArg('name', 0),
                includeClass: new ObjectType(IncludedCount::class),
                internalName: $event->getArg('internalName', 1, new NullType),
            ),
            'exists' => $this->allowedIncludeManager->createType(
                name: $event->getArg('name', 0),
                includeClass: new ObjectType(IncludedExists::class),
                internalName: $event->getArg('internalName', 1, new NullType),
            ),
            'callback' => $this->allowedIncludeManager->createType(
                name: $event->getArg('name', 0),
                includeClass: new ObjectType(IncludedCallback::class),
                internalName: $event->getArg('internalName', 1, new NullType),
            ),
            'custom' => $this->allowedIncludeManager->createType(
                name: $event->getArg('name', 0),
                includeClass: $event->getArg('includeClass', 1),
                internalName: $event->getArg('internalName', 2, new NullType),
            ),
            default => null,
        };
    }
}
