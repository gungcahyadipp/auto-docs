<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Infer;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Type\VoidType;
use Dedoc\ScramblePro\Extensions\LaravelData\DataTypesFactory;
use Dedoc\ScramblePro\Extensions\LaravelData\Types\DataTransformedType;
use Illuminate\Http\JsonResponse;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

class DataCollectionMethodsExtension implements MethodReturnTypeExtension
{
    public function __construct(private DataTypesFactory $dataTypesFactory) {}

    public function shouldHandle(ObjectType $type): bool
    {
        // @todo non-collection
        if (! $type instanceof Generic) {
            return false;
        }

        return $type->isInstanceOf(DataCollection::class)
            || $type->isInstanceOf(PaginatedDataCollection::class)
            || $type->isInstanceOf(CursorPaginatedDataCollection::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            '__construct' => $this->handleConstructCall($event),
            'include', 'exclude', 'only', 'except',
            'includePermanently', 'excludePermanently', 'onlyPermanently', 'exceptPermanently',
            'includeWhen', 'excludeWhen', 'onlyWhen', 'exceptWhen',
            'wrap', 'withoutWrapping', 'getWrap' => null,
            'toJson', 'toArray' => new DataTransformedType($event->instance),
            'toResponse' => new Generic(JsonResponse::class, [
                $event->getInstance(),
                new UnknownType,
                new ArrayType,
            ]),
            default => $event->getInstance(), // Assuming fluent interfaces, so calling not implemented methods here will not fully break documentation. @todo
        };
    }

    private function handleConstructCall(MethodCallEvent $event)
    {
        /** @var Generic $type */
        $type = $event->instance;

        if (! ($classType = $event->getArg('dataClass', 0)) instanceof GenericClassStringType) {
            return null;
        }

        // asserting we're working with the expected structure
        if (count($event->getDefinition()->templateTypes) !== 3) {
            return null;
        }

        $type->templateTypes[0 /* TKey */] = new IntegerType;
        $type->templateTypes[1 /* TValue */] = $classType->type;
        $type->templateTypes[2 /* TDataContext */] = $this->dataTypesFactory->makeDataContextType();

        return new VoidType;
    }
}
