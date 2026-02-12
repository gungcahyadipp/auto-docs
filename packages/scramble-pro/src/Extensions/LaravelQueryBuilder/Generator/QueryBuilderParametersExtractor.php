<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Generator;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Handler\IndexBuildingHandler;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;
use Dedoc\Scramble\Support\IndexBuilders\Bag;
use Dedoc\Scramble\Support\IndexBuilders\ScopeCollector;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\EnumCaseType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Data\Parameter as DataParameter;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Index\QueryRequestIndexBuilder;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers\AllowedFilterManager;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers\AllowedIncludeManager;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers\AllowedSortManager;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers\QueryBuilderManager;
use Illuminate\Support\Arr;
use PhpParser\NodeTraverser;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\FilterOperator;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\Filters\FiltersOperator;
use Spatie\QueryBuilder\Filters\FiltersTrashed;
use Spatie\QueryBuilder\QueryBuilder;

class QueryBuilderParametersExtractor implements ParameterExtractor
{
    public function __construct(private TypeTransformer $openApiTransformer) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        $queryBuilderBags = $this->getActionQueryBuilders($routeInfo);

        $queryBuilderInstances = array_values(array_filter(array_map(
            $this->getResolvedQueryBuilderType(...),
            $queryBuilderBags,
        )));

        if (! $queryBuilderInstances) {
            return $parameterExtractionResults;
        }

        $queryParameters = [];

        $filters = collect($queryBuilderInstances)->map($this->getFilters(...))->flatten()->all();
        if (count($filters)) {
            $parameters = array_map(
                function (DataParameter $filter) {
                    $parameter = (new Parameter("filter[$filter->name]", 'query'))
                        ->description($filter->description ?: '')
                        ->setSchema(Schema::fromType($filter->toSchema()));

                    $parameter->setAttribute('isFlat', true);

                    return $parameter;
                },
                $filters,
            );

            $queryParameters = array_merge($queryParameters, $parameters);
        }

        foreach ($queryBuilderInstances as $queryBuilderType) {
            if (count($sorts = $this->getSorts($queryBuilderType))) {
                $possibleSorts = collect($sorts)->map(fn ($sort) => $sort->name)->all();
                $possibleSortsDescription = implode(', ', array_map(fn ($sort) => '`'.$sort.'`', $possibleSorts));

                $sortParameter = (new Parameter('sort', 'query'))
                    ->description('Available sorts are '.$possibleSortsDescription.'. You can sort by multiple options by separating them with a comma. To sort in descending order, use `-` sign in front of the sort, for example: `-'.$possibleSorts[0].'`.')
                    ->setSchema(Schema::fromType(
                        $type = (new StringType)
                    ));

                if ($defaultSorts = $this->getDefaultSorts($queryBuilderType)) {
                    $type->default(implode(', ', array_filter($defaultSorts)));
                }

                $queryParameters = array_merge($queryParameters, [$sortParameter]);
            }
        }

        $includes = collect($queryBuilderInstances)->map($this->getIncludes(...))->flatten()->all();
        if (count($includes)) {
            $possibleIncludesDescription = implode(', ', array_map(fn ($include) => '`'.$include.'`', $includes));

            $includeParameter = (new Parameter('include', 'query'))
                ->description('Available includes are '.$possibleIncludesDescription.'. You can include multiple options by separating them with a comma.')
                ->setSchema(Schema::fromType(new StringType));

            $queryParameters = array_merge($queryParameters, [$includeParameter]);
        }

        $fields = collect($queryBuilderInstances)->map($this->getFields(...))->flatten()->all();
        if (count($fields)) {
            /** @var array<string, string[]> $groupedFields */
            $groupedFields = collect($fields)
                ->reduce(function ($acc, $field) {
                    $fieldGroups = explode('.', $field, 2);

                    $group = count($fieldGroups) === 1 ? '' : $fieldGroups[0];
                    $field = count($fieldGroups) === 1 ? $field : $fieldGroups[1];

                    $acc[$group] ??= [];
                    $acc[$group][] = $field;

                    return $acc;
                }, []);

            foreach ($groupedFields as $group => $fields) {
                $possibleFieldsDescription = implode(', ', array_map(fn ($field) => '`'.$field.'`', $fields));

                $fieldsParameterName = $group === '' ? 'fields' : 'fields['.$group.']';

                $fieldsParameter = (new Parameter($fieldsParameterName, 'query'))
                    ->description('Available fields are '.$possibleFieldsDescription.'. You can include multiple options by separating them with a comma.')
                    ->setSchema(Schema::fromType(
                        $type = (new StringType)
                    ));

                $fieldsParameter->setAttribute('isFlat', true);

                $queryParameters = array_merge($queryParameters, [$fieldsParameter]);
            }
        }

        foreach ($queryParameters as $queryParameter) {
            $queryParameter->setAttribute('isInQuery', true);
        }

        return [...$parameterExtractionResults, new ParametersExtractionResult($queryParameters)];
    }

    /**  @return string[] */
    private function getIncludes(Generic $queryBuilderType): array
    {
        $includes = app(QueryBuilderManager::class)->getPropertyType($queryBuilderType, 'allowedIncludes');

        if (! $includes instanceof KeyedArrayType) {
            return [];
        }

        return collect($includes->items)
            ->flatMap(function (ArrayItemType_ $item) {
                return $item->value instanceof KeyedArrayType ? $item->value->items : [$item];
            })
            ->flatMap(function (ArrayItemType_ $item) {
                $allowedIncludeType = $item->value;

                if (! $allowedIncludeType instanceof Generic || ! $allowedIncludeType->isInstanceOf(AllowedInclude::class)) {
                    return [];
                }

                $name = app(AllowedIncludeManager::class)->getPropertyType($allowedIncludeType, 'name')->value ?? null;

                if (! $name) {
                    return [];
                }

                return [$name];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**  @return string[] */
    private function getFields(Generic $queryBuilderType): array
    {
        $fields = app(QueryBuilderManager::class)->getPropertyType($queryBuilderType, 'allowedFields');

        if (! $fields instanceof KeyedArrayType) {
            return [];
        }

        return collect($fields->items)
            ->map(function (ArrayItemType_ $item): ?string {
                return $item->value->value ?? null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return string[] */
    private function getDefaultSorts(Generic $queryBuilderType): array
    {
        return array_values(array_filter(array_map(function (ArrayItemType_ $item) {
            $allowedSortType = $item->value;

            if (! $allowedSortType instanceof Generic || ! $allowedSortType->isInstanceOf(AllowedSort::class)) {
                return null;
            }

            $name = app(AllowedSortManager::class)->getPropertyType($allowedSortType, 'name')->value ?? null;
            $direction = app(AllowedSortManager::class)->getPropertyType($allowedSortType, 'defaultDirection')->value ?? null;

            return ($direction === SortDirection::DESCENDING ? '-' : '').$name;
        }, app(QueryBuilderManager::class)->getPropertyType($queryBuilderType, 'defaultSorts')->items ?? [])));
    }

    /** @return DataParameter[] */
    private function getSorts(Generic $queryBuilderType): array
    {
        $sorts = app(QueryBuilderManager::class)->getPropertyType($queryBuilderType, 'allowedSorts');

        if (! $sorts instanceof KeyedArrayType) {
            return [];
        }

        return array_values(array_filter(array_map(function (ArrayItemType_ $item) {
            $allowedSortType = $item->value;
            if (! $allowedSortType instanceof Generic || ! $allowedSortType->isInstanceOf(AllowedSort::class)) {
                return null;
            }

            $name = app(AllowedSortManager::class)->getPropertyType($allowedSortType, 'name')->value ?? null;

            if (! $name) {
                return null;
            }

            /** @var PhpDocNode|null $phpDocNode */
            $phpDocNode = $allowedSortType->getAttribute('docNode');

            return new DataParameter(
                name: $name,
                description: $this->makeNameableParameterDescription($allowedSortType, $phpDocNode),
                example: ExamplesExtractor::make($phpDocNode)->extract()[0] ?? new MissingValue,
                default: ExamplesExtractor::make($phpDocNode, '@default')->extract()[0] ?? $this->getDefaultSortValue($allowedSortType),
            );
        }, $sorts->items)));
    }

    /** @return DataParameter[] */
    private function getFilters(Generic $queryBuilderType): array
    {
        $filters = app(QueryBuilderManager::class)->getPropertyType($queryBuilderType, 'allowedFilters');

        if (! $filters instanceof KeyedArrayType) {
            return [];
        }

        return array_values(array_filter(array_map(function (ArrayItemType_ $item) {
            $allowedFilterType = $item->value;
            if (! $allowedFilterType instanceof Generic || ! $allowedFilterType->isInstanceOf(AllowedFilter::class)) {
                return null;
            }

            $name = app(AllowedFilterManager::class)->getPropertyType($allowedFilterType, 'name')->value ?? null;

            if (! $name) {
                return null;
            }

            /** @var PhpDocNode|null $phpDocNode */
            $phpDocNode = $allowedFilterType->getAttribute('docNode');
            $phpDocReflector = $phpDocNode ? new SchemaClassDocReflector($phpDocNode) : null;

            $type = new StringType;
            if ($phpDocReflector && isset($phpDocReflector->getTagValue('@var')->type) && ($nativeType = $phpDocReflector->getTagValue('@var')->type)) {
                $type = $this->openApiTransformer->transform(PhpDocTypeHelper::toType($nativeType));
            }

            return new DataParameter(
                name: $name,
                description: $this->makeNameableParameterDescription($allowedFilterType, $phpDocNode),
                type: $type,
                format: $phpDocNode ? array_values($phpDocNode->getTagsByName('@format'))[0]->value->value ?? null : null,
                example: ExamplesExtractor::make($phpDocNode)->extract()[0] ?? new MissingValue,
                default: ExamplesExtractor::make($phpDocNode, '@default')->extract()[0] ?? $this->getDefaultFilterValue($allowedFilterType),
            );
        }, $filters->items)));
    }

    private function makeNameableParameterDescription(Generic $type, ?PhpDocNode $phpDocNode): string
    {
        $parts = [$phpDocNode ? $this->makeDescriptionFromComments($phpDocNode) : null];

        $filterType = app(AllowedFilterManager::class)->getPropertyType($type, 'filterClass');

        if ($filterType instanceof ObjectType && $filterType->isInstanceOf(FiltersTrashed::class)) {
            $parts[] = 'Can be a value of `with` (response will contain deleted items as well), `only` (will contain only deleted items), or any arbitrary value (will contain only not deleted items).';
        }

        if ($this->isDynamicFilterOperator($filterType)) {
            $parts[] = 'Supports operators in the value: `>`, `<`, `>=`, `<=`, `<>`, or none (indicating equality). For example: `>value` for greater than `value`, or `value` for equal.';
        }

        return implode('. ', array_filter($parts));
    }

    /**
     * @phpstan-assert-if-true Generic $filterType
     */
    private function isDynamicFilterOperator(?Type $filterType): bool
    {
        $secondTemplateType = $filterType->templateTypes[1] ?? null;

        return $filterType instanceof Generic
            && $filterType->isInstanceOf(FiltersOperator::class)
            && $secondTemplateType instanceof EnumCaseType
            && $secondTemplateType->isInstanceOf(FilterOperator::class)
            && $secondTemplateType->caseName === 'DYNAMIC';
    }

    /**
     * @return array<mixed>|scalar|null|MissingValue
     */
    private function getDefaultFilterValue(Generic $allowedFilterType): mixed
    {
        $defaultPropertyType = app(AllowedFilterManager::class)->getPropertyType($allowedFilterType, 'default');

        return match (true) {
            $defaultPropertyType instanceof NullType => null,
            default => $defaultPropertyType->value ?? new MissingValue,
        };
    }

    private function makeDescriptionFromComments(PhpDocNode $phpDocNode): string
    {
        return trim($phpDocNode->getAttribute('summary').' '.$phpDocNode->getAttribute('description')); // @phpstan-ignore binaryOp.invalid, binaryOp.invalid
    }

    /**
     * @param  Bag<array{"scope"?: Scope, "queryBuilders"?: Type[]}>  $index
     */
    private function getResolvedQueryBuilderType(Bag $index): ?Generic
    {
        $lastQueryBuilderInstance = null;
        $lastScope = null;

        foreach (($index->data['queryBuilders'] ?? []) as $type) {
            if (! ($index->data['scope'] ?? null)) {
                continue;
            }

            $resolvedType = ReferenceTypeResolver::getInstance()->resolve($index->data['scope'], $type);

            if (! $resolvedType->isInstanceOf(QueryBuilder::class)) {
                break;
            }

            $lastScope = $index->data['scope'];
            $lastQueryBuilderInstance = $resolvedType;
        }

        if ($lastScope && $lastQueryBuilderInstance instanceof TemplateType && $lastQueryBuilderInstance->isInstanceOf(QueryBuilder::class) && $lastQueryBuilderInstance->is instanceof ObjectType) {
            $lastQueryBuilderInstance = ReferenceTypeResolver::getInstance()->resolve(
                $lastScope,
                // At this point the `name` should be defined, otherwise isInstanceOf check won't pass.
                new NewCallReferenceType($lastQueryBuilderInstance->is->name, []),
            );
        }

        return $lastQueryBuilderInstance instanceof Generic ? $lastQueryBuilderInstance : null;
    }

    /**
     * @return array<mixed>|scalar|null|MissingValue
     */
    private function getDefaultSortValue(Generic $allowedSortType): mixed
    {
        $defaultPropertyType = app(AllowedSortManager::class)->getPropertyType($allowedSortType, 'defaultDirection');

        return match (true) {
            $defaultPropertyType instanceof NullType => null,
            default => $defaultPropertyType->value ?? new MissingValue,
        };
    }

    /**
     * @return Bag<array{scope?: Scope, queryBuilders?: Type[]}>[]
     */
    protected function getActionQueryBuilders(RouteInfo $routeInfo): array
    {
        if (! $node = $routeInfo->actionNode()) {
            return [];
        }

        $index = app(Index::class);
        $fileNameResolver = new FileNameResolver($routeInfo->getActionReflector()->getNameContext());

        // Do additional QueryRequestIndexBuilder pass because after the first pass on route method,
        // side effects analysis may've discovered functions that create query builder instances.

        $traverser = new NodeTraverser;

        /** @var Bag<array{scope?: Scope, queryBuilders?: Type[]}> $bag */
        $bag = new Bag;

        $traverser->addVisitor(new TypeInferer(
            $index,
            $fileNameResolver,
            $routeInfo->getScope(),
            Context::getInstance()->extensionsBroker->extensions,
            [new IndexBuildingHandler([new ScopeCollector, new QueryRequestIndexBuilder(
                $bag,
                $routeInfo->getActionType()?->getAttribute('queryBuilderCalls') ?: [], // @phpstan-ignore argument.type
            )])],
        ));

        $traverser->traverse(Arr::wrap($node));

        /** @var Bag<array{scope?: Scope, queryBuilders?: Type[]}>[] $queryBuilderIndexes */
        $queryBuilderIndexes = $routeInfo->getActionType()?->getAttribute('queryBuilderIndexes') ?: [];

        return [
            $bag,
            ...$queryBuilderIndexes,
        ];
    }
}
