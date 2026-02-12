<?php

namespace Dedoc\ScramblePro;

use Dedoc\Scramble\Support\Type\AbstractType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\TypeHelper;

class Utils
{
    /**
     * When some methods allow passing arguments both as separate literals, or as an array of literals,
     * this method is used to get all the literals as an array.
     *
     * @return AbstractType[]
     */
    public static function getNormalizedArgumentTypes(array $argsTypes): array
    {
        $argsTypes = array_map(
            fn ($type) => TypeHelper::unpackIfArray($type),
            $argsTypes,
        );

        return array_map(
            function ($t) {
                $type = ($t instanceof ArrayItemType_ ? $t->value : $t);
                if ($docNode = $t->getAttribute('docNode')) {
                    $type->setAttribute('docNode', $docNode);
                }

                return $type;
            },
            ($argsTypes[0] ?? null) instanceof KeyedArrayType ? $argsTypes[0]->items : $argsTypes,
        );
    }

    /**
     * Simple PHP algorithm that takes a string like this as an input: '{code, name}' and returns an array
     * as an output: ['code', 'name']. So it takes out outter-most curly braces, splits the string by
     * commas, and trims the resulting substrings.
     *
     * The complex thing here is that the input string may contain nested curly braces and that substrings
     * should not be affected in any way. For example, '{code, name, foo.{bar, baz}}' must give the
     * following output: ['code', 'name', 'foo.{bar, baz}'].
     */
    public static function splitCurlyBracesString(string $input): array
    {
        // Remove outermost curly braces if present
        if (strlen($input) >= 2 && $input[0] === '{' && $input[strlen($input) - 1] === '}') {
            $input = substr($input, 1, -1);
        }

        $parts = [];
        $current = '';
        $depth = 0;
        $length = strlen($input);

        // Process each character
        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];

            // Increase or decrease depth when encountering nested braces
            if ($char === '{') {
                $depth++;
                $current .= $char;
            } elseif ($char === '}') {
                $depth--;
                $current .= $char;
            }
            // Split on comma only if not within nested braces
            elseif ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        // Add the final part if any remains
        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }
}
