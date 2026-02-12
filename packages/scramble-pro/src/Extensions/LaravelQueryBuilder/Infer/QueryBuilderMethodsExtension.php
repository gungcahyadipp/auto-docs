<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Infer;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Spatie\QueryBuilder\QueryBuilder;

class QueryBuilderMethodsExtension implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(QueryBuilder::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            // These methods are handled by the definition itself, so we don't want to interfere here.
            'allowedFilters', 'allowedSorts', 'allowedIncludes', 'defaultSorts', 'allowedFields', 'defaultSort' => null,

            // Assuming fluent interfaces, so calling not implemented methods here will not fully break documentation.
            default => $event->getInstance(),
        };
    }
}
