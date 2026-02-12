<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData;

use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Spatie\LaravelData\Contracts\BaseDataCollectable;
use Spatie\LaravelData\Support\DataProperty;

class DataTransformConfig
{
    const INPUT = 'input';

    const OUTPUT = 'output';

    const SYNTHETIC_INPUT_CLASS = '$$LARAVEL_DATA_INPUT';

    public function __construct(
        public string $direction,
    ) {}

    public function getPropertyName(?DataProperty $dataProperty): ?string
    {
        if (! $dataProperty) {
            return null;
        }

        return match ($this->direction) {
            static::INPUT => $dataProperty->inputMappedName ?: $dataProperty->name,
            static::OUTPUT => $dataProperty->outputMappedName ?: $dataProperty->name,
        };
    }

    public function wrapToInputType(Type $propType): Type
    {
        if ($this->direction === static::OUTPUT) {
            return $propType;
        }

        if ($propType instanceof Union) {
            return Union::wrap(array_map(
                $this->wrapToInputType(...),
                $propType->types,
            ));
        }

        if ($propType instanceof ArrayType) {
            return new ArrayType(new Generic(static::SYNTHETIC_INPUT_CLASS, [$propType->value]));
        }

        if (is_a($propType->name, BaseDataCollectable::class, true)) {
            return new ArrayType(new Generic(static::SYNTHETIC_INPUT_CLASS, [$propType->templateTypes[1]]));
        }

        return new Generic(static::SYNTHETIC_INPUT_CLASS, [$propType]);
    }

    public function shouldWrap(): bool
    {
        return $this->direction === static::OUTPUT;
    }
}
