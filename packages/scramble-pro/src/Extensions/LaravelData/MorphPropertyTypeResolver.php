<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node\Expr as PhpParserExpr;
use PhpParser\Node\Scalar as PhpParserScalar;
use Spatie\LaravelData\Support\DataProperty;

class MorphPropertyTypeResolver
{
    public function resolve(Generic $type, DataProperty $dataProperty): ?Type
    {
        $method = $this->getMorphMethod($dataProperty->className);

        if (! $method) {
            return null;
        }

        $flow = $method->getFlowContainer();

        $origins = $flow->findValueOriginsByExitType(
            fn (Type $t) => $t instanceof LiteralString && $t->getValue() === $type->name,
        );

        if (! $origins) {
            return null;
        }

        if (! $propertiesParameterName = $this->getFirstParameterName($method)) {
            return null;
        }

        $morphPropertyType = $flow->getTypeAt(
            new PhpParserExpr\ArrayDimFetch(
                new PhpParserExpr\Variable($propertiesParameterName),
                new PhpParserScalar\String_($dataProperty->name),
            ),
            $origins[0],
        );

        if ($morphPropertyType instanceof UnknownType) {
            return null;
        }

        return ReferenceTypeResolver::getInstance()->resolve($method->getScope(), $morphPropertyType);
    }

    private function getMorphMethod(string $className): ?Infer\Definition\FunctionLikeAstDefinition
    {
        $method = app(Infer::class)
            ->analyzeClass($className)
            ->getMethod('morph');

        return $method instanceof Infer\Definition\FunctionLikeAstDefinition
            ? $method
            : null;
    }

    private function getFirstParameterName(Infer\Definition\FunctionLikeAstDefinition $method): ?string
    {
        $key = array_keys($method->type->arguments)[0] ?? null;
        if ($key) {
            return (string) $key;
        }

        return null;
    }
}
