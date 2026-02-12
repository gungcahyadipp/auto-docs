<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Index;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\IndexBuilders\Bag;
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @phpstan-type QueryRequestIndexBuilderBag array{"scope"?: Scope, "queryBuilders"?: Type[]}
 *
 * @implements IndexBuilder<QueryRequestIndexBuilderBag>
 */
class QueryRequestIndexBuilder implements IndexBuilder
{
    /**
     * @param  Bag<QueryRequestIndexBuilderBag>  $bag
     * @param  Bag<array<string, Node\Expr\CallLike>>[]  $effectCalls
     */
    public function __construct(
        public readonly Bag $bag,
        public readonly array $effectCalls = [],
    ) {}

    public function afterAnalyzedNode(Scope $scope, Node $node): void
    {
        $queryBuilders = $this->bag->data['queryBuilders'] ?? [];
        $this->bag->set('scope', $scope);

        $nodeType = $scope->getType($node);

        // keep node reference when the type somehow is related to QueryBuilder
        if (
            $nodeType instanceof StaticMethodCallReferenceType
            && is_a($nodeType->callee, QueryBuilder::class, true)
        ) {
            $queryBuilders[] = $nodeType;
        }

        if ($this->isQueryBuilderBuilderCandidateCall($node)) {
            $queryBuilders[] = $nodeType;
        }

        if ($nodeType->isInstanceOf(QueryBuilder::class)) {
            $queryBuilders[] = $nodeType;
        }

        $persistedQueryBuilder = count($queryBuilders) ? $queryBuilders[count($queryBuilders) - 1] ?? null : null;

        if (
            $persistedQueryBuilder
            && $nodeType instanceof MethodCallReferenceType
            && $nodeType->callee === $persistedQueryBuilder
        ) {
            $queryBuilders[] = $nodeType;
        }

        $this->bag->set('queryBuilders', $queryBuilders);
    }

    protected function isQueryBuilderBuilderCandidateCall(Node $node): bool
    {
        return in_array($node, $this->effectCalls, strict: true);
    }
}
