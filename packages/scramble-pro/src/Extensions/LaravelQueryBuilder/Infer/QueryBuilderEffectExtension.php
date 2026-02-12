<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Infer;

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterSideEffectCallAnalyzed;
use Dedoc\Scramble\Infer\Extensions\Event\SideEffectCallEvent;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\IndexBuilders\Bag;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Index\QueryRequestIndexBuilder;
use Spatie\QueryBuilder\QueryBuilder;
use WeakMap;

class QueryBuilderEffectExtension implements AfterSideEffectCallAnalyzed
{
    private WeakMap $existenceCache;

    private WeakMap $queryBuilderCache;

    public function __construct()
    {
        $this->existenceCache = new WeakMap;
        $this->queryBuilderCache = new WeakMap;
    }

    public function shouldHandle(SideEffectCallEvent $event): bool
    {
        return $this->existenceCache[$event->calledDefinition] ??= $this->hasQueryBuilderDefined($event->calledDefinition);
    }

    public function afterSideEffectCallAnalyzed(SideEffectCallEvent $event)
    {
        if ($this->queryBuilderCache->offsetExists($event->calledDefinition)) {
            return;
        }

        $this->queryBuilderCache->offsetSet($event->calledDefinition, true);

        /** @var Index $index */
        $index = app(Index::class);

        if (! $index->getClassDefinition($event->calledDefinition->definingClassName)) {
            (new ClassAnalyzer($index))->analyze($event->calledDefinition->definingClassName);
        }

        $calledDefinition = $index
            ->getClassDefinition($event->calledDefinition->definingClassName)
            ->getMethodDefinition(
                $event->calledDefinition->type->name,
                indexBuilders: [$indexBuilder = new QueryRequestIndexBuilder(new Bag)], // @phpstan-ignore argument.type
            );

        if ($calledDefinition && $calledDefinition->getReturnType()->isInstanceOf(QueryBuilder::class)) {
            if (isset($indexBuilder->bag->data['queryBuilders'])) {
                $this->removeReturnedQueryBuilderFromEffects(
                    $calledDefinition->getReturnType()->getOriginal(),
                    $indexBuilder->bag->data['queryBuilders'],
                );
            }

            $event->definition->type->setAttribute(
                'queryBuilderCalls',
                [
                    ...($event->definition->type->getAttribute('queryBuilderCalls') ?: []),
                    $event->node,
                ],
            );
        }

        /** @var Bag<array{scope?: Scope, queryBuilders?: Type[]}>[] $queryBuilderResults */
        $queryBuilderResults = $event->definition->type->getAttribute('queryBuilderIndexes') ?: [];

        $event->definition->type->setAttribute(
            'queryBuilderIndexes',
            [...$queryBuilderResults, $indexBuilder->bag],
        );
    }

    /**
     * @throws \ReflectionException
     */
    private function hasQueryBuilderDefined(FunctionLikeDefinition $calledDefinition): bool
    {
        if (! $calledDefinition->definingClassName) {
            return false;
        }
        /**
         * Called definition may be a part of the query builder classes itself, and we don't want to handle it
         * as it will mess with the definition of query builder which itself is handled in the separate extension.
         */
        if (is_a($calledDefinition->definingClassName, QueryBuilder::class, true)) {
            return false;
        }

        if ($calledDefinition->type->returnType instanceof ObjectType && $calledDefinition->type->returnType->isInstanceOf(QueryBuilder::class)) {
            return true;
        }

        $classReflector = ClassReflector::make($calledDefinition->definingClassName);
        $source = $classReflector->getMethod($calledDefinition->type->name)->getMethodCode();

        // *::for, new *(
        preg_match_all('/(\b\w+)::for|\bnew\s+(\w+)\(/', $source, $matches);
        $queryBuilderNameCandidates = array_values(array_filter(array_merge($matches[1], $matches[2])));

        return collect($queryBuilderNameCandidates)
            ->map(new FileNameResolver($classReflector->getNameContext()))
            ->some(fn ($class) => is_a($class, QueryBuilder::class, true));
    }

    /**
     * @param  Type[]  $queryBuilders
     */
    private function removeReturnedQueryBuilderFromEffects(?Type $returnedQueryBuilder, array &$queryBuilders): void
    {
        if (! $returnedQueryBuilder) {
            return;
        }

        $builders = [$returnedQueryBuilder];
        while ($returnedQueryBuilder !== null) { // @phpstan-ignore notIdentical.alwaysTrue
            if (! $returnedQueryBuilder instanceof MethodCallReferenceType) {
                $returnedQueryBuilder = null;
                break;
            }
            $builders[] = $returnedQueryBuilder = $returnedQueryBuilder->callee;
        }

        $queryBuilders = array_values(array_filter($queryBuilders, function (Type $queryBuilderCandidateType) use ($builders) {
            return ! in_array($queryBuilderCandidateType, $builders, strict: true);
        }));
    }
}
