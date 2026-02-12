<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Infer\Types;

use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Extensions\ResolvingType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Utils;

class NormalizedArguments implements ResolvingType
{
    public function resolve(ReferenceResolutionEvent $event): ?Type
    {
        if (! $event->type instanceof Generic) {
            throw new \InvalidArgumentException('NormalizedArguments must be generic');
        }

        if (! isset($event->type->templateTypes[0])) {
            throw new \InvalidArgumentException('NormalizedArguments must have single type argument');
        }

        if (! $event->type->templateTypes[0] instanceof KeyedArrayType) {
            throw new \InvalidArgumentException('NormalizedArguments must accept KeyedArrayType');
        }

        $argumentsArray = collect($event->type->templateTypes[0]->items)
            ->mapWithKeys(fn (ArrayItemType_ $t) => [
                $t->key => $t->value,
            ]);

        return new KeyedArrayType(
            collect(Utils::getNormalizedArgumentTypes($argumentsArray->all()))
                ->map(fn (Type $t) => new ArrayItemType_(null, $t))
                ->all()
        );
    }
}
