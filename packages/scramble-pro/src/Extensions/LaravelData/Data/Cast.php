<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Data;

use Illuminate\Support\Arr;

class Cast
{
    public function __construct(
        public string $instance,
        public array $arguments = [],
    ) {}

    public static function fromString(string $cast)
    {
        $data = explode(':', $cast, 2);

        $instance = count($data) === 2 ? $data[0] : $cast;
        $arguments = count($data) === 2 ? explode(',', $data[1]) : [];

        return new self($instance, $arguments);
    }

    public function isInstanceOf(string $class)
    {
        return is_a($this->instance, $class, true);
    }

    public function getArgument(int $index, mixed $default = null)
    {
        return Arr::get($this->arguments, $index, $default);
    }
}
