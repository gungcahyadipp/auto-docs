<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Infer\Types;

use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Extensions\ResolvingType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;

class MapUsing implements ResolvingType
{
    const DEFAULT_MAP_METHOD = 'createType';

    public static function forNormalizedArguments(string $class, string $method = self::DEFAULT_MAP_METHOD): Generic
    {
        return new Generic(MapUsing::class, [
            new Generic(NormalizedArguments::class, [
                new TemplateType('Arguments'),
            ]),
            new ObjectType($class),
            new LiteralStringType($method),
        ]);
    }

    public function resolve(ReferenceResolutionEvent $event): ?Type
    {
        if (! $event->type instanceof Generic) {
            throw new \InvalidArgumentException('MapUsing must be generic');
        }

        if (count($event->type->templateTypes) !== 3) {
            throw new \InvalidArgumentException('MapUsing must accept 3 type arguments');
        }

        [$subject, $mapUsing, $mapMethod] = $event->type->templateTypes;

        if (! $subject instanceof KeyedArrayType) {
            throw new \InvalidArgumentException('MapUsing argument #1 must be KeyedArrayType');
        }

        if (! $mapUsing instanceof ObjectType) {
            throw new \InvalidArgumentException('MapUsing argument #2 must be ObjectType');
        }

        if (! $mapMethod instanceof LiteralStringType) {
            throw new \InvalidArgumentException('MapUsing argument #3 must be ObjectType');
        }

        $mapMethod = $mapMethod->value;

        if (! method_exists($mapUsing->name, $mapMethod)) {
            throw new \InvalidArgumentException('MapUsing argument #2 class must have `'.$mapMethod.'` method implemented');
        }

        $argumentsArray = collect($subject->items)
            ->map(fn (ArrayItemType_ $t) => $t->value)
            ->all();

        return new KeyedArrayType(array_map(
            function ($t) use ($mapUsing, $mapMethod) {
                if ($t instanceof LiteralStringType) {
                    $type = app($mapUsing->name)->{$mapMethod}(name: $t);
                    $type->setAttribute('docNode', $t->getAttribute('docNode'));
                    $t = $type;
                }

                return new ArrayItemType_(key: null, value: $t);
            },
            $argumentsArray,
        ));
    }
}
