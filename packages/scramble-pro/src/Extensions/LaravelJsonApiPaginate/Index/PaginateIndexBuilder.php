<?php

namespace Dedoc\ScramblePro\Extensions\LaravelJsonApiPaginate\Index;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\IndexBuilders\Bag;
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;
use PhpParser\Node;

/**
 * @phpstan-type PaginateIndexBuilderBag array{"scope"?: Scope, "apiPaginatorCandidates"?: (Node\Expr\StaticCall|Node\Expr\MethodCall)[]}
 *
 * @implements IndexBuilder<PaginateIndexBuilderBag>
 */
class PaginateIndexBuilder implements IndexBuilder
{
    /**
     * @param  Bag<PaginateIndexBuilderBag>  $bag
     */
    public function __construct(public readonly Bag $bag) {}

    public function afterAnalyzedNode(Scope $scope, Node $node): void
    {
        if (! $node instanceof Node\Expr\MethodCall && ! $node instanceof Node\Expr\StaticCall) {
            return;
        }

        if (! $node->name instanceof Node\Identifier) {
            return;
        }

        if ($node->name->name !== config('json-api-paginate.method_name')) {
            return;
        }

        $this->bag->set(
            $key = 'apiPaginatorCandidates',
            [
                ...($this->bag->data[$key] ?? []),
                $node,
            ],
        );
        $this->bag->set('scope', $scope);
    }
}
