<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Data;

use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

class Parameter
{
    /**
     * @param  array<mixed>|scalar|null|MissingValue  $example
     * @param  array<mixed>|scalar|null|MissingValue  $default
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly Type $type = new StringType,
        public readonly ?string $format = null,
        public readonly mixed $example = new MissingValue,
        public readonly mixed $default = new MissingValue,
    ) {}

    public function toSchema(): Type
    {
        return (clone $this->type)
            ->format($this->format ?: '')
            ->default($this->default)
            ->examples([$this->example]);
    }
}
