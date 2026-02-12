<?php

namespace Dedoc\ScramblePro\Extensions\JsonApi\Infer;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Illuminate\Http\JsonResponse;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class ResourceCollectionMethodsExtension implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $callee): bool
    {
        return $callee->isInstanceOf(JsonApiResourceCollection::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->getName()) {
            'response', 'toResponse' => new Generic(JsonResponse::class, [
                ResourceCollectionTypeManager::make($event->getInstance())->getResponseType(),
                new UnknownType,
                new KeyedArrayType([
                    new ArrayItemType_('Content-type', new LiteralStringType('application/vnd.api+json')),
                ]),
            ]),
            default => null,
        };
    }
}
