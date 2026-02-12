<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Infer;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\ScramblePro\Extensions\LaravelData\Types\DataTransformedType;
use Illuminate\Http\JsonResponse;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Resource;

class DataMethodsExtension implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        // @todo non-collection
        if (! $type instanceof Generic) {
            return false;
        }

        return $type->isInstanceOf(Data::class)
            || $type->isInstanceOf(Resource::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'include', 'exclude', 'only', 'except',
            'includePermanently', 'excludePermanently', 'onlyPermanently', 'exceptPermanently',
            'includeWhen', 'excludeWhen', 'onlyWhen', 'exceptWhen',
            'wrap', 'withoutWrapping', 'getWrap' => null,
            'toJson', 'toArray' => new DataTransformedType($event->getInstance()),
            'toResponse' => new Generic(JsonResponse::class, [
                $event->getInstance(),
                new UnknownType,
                new ArrayType,
            ]),
            default => $event->getInstance(), // Assuming fluent interfaces, so calling not implemented methods here will not fully break documentation. @todo
        };
    }
}
