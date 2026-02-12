<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Exceptions;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Exception;

class InvalidDataNormalizedType extends Exception
{
    public function __construct(string $dataClass, string $expectedNormalizedClass, string $actualNormalizedClass)
    {
        parent::__construct('Expected '.class_basename($expectedNormalizedClass).' and got '.class_basename($actualNormalizedClass).' when normalizing '.$dataClass);
    }

    public static function expectedGeneric(Type $type, string $dataClass)
    {
        return new self($dataClass, Generic::class, $type::class);
    }
}
