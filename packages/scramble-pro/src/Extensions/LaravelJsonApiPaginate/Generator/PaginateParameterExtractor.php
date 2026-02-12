<?php

namespace Dedoc\ScramblePro\Extensions\LaravelJsonApiPaginate\Generator;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelJsonApiPaginate\Index\PaginateIndexBuilder;

class PaginateParameterExtractor implements ParameterExtractor
{
    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        if (! $paginatorType = $this->getJsonApiPaginator($routeInfo)) {
            return $parameterExtractionResults;
        }

        /** @var string $numberParameter */
        $numberParameter = config('json-api-paginate.number_parameter');
        /** @var string $cursorParameter */
        $cursorParameter = config('json-api-paginate.cursor_parameter');
        /** @var string $sizeParameter */
        $sizeParameter = config('json-api-paginate.size_parameter');
        /** @var string $paginationParameter */
        $paginationParameter = config('json-api-paginate.pagination_parameter');
        /** @var int $defaultPageSize */
        $defaultPageSize = $paginatorType->getAttribute('jsonApiPageSize') ?? config('json-api-paginate.default_size');

        $queryParameters = [
            Parameter::make($paginationParameter.'['.$sizeParameter.']', 'query')
                ->setSchema(Schema::fromType(
                    (new IntegerType)->default($defaultPageSize)
                ))
                ->description('The number of results that will be returned per page.'),
        ];

        if (config('json-api-paginate.use_cursor_pagination')) {
            $queryParameters[] = Parameter::make($paginationParameter.'['.$cursorParameter.']', 'query')
                ->setSchema(Schema::fromType(new StringType))
                ->description('The cursor to start the pagination from.');
        } else {
            $queryParameters[] = Parameter::make($paginationParameter.'['.$numberParameter.']', 'query')
                ->setSchema(Schema::fromType(new IntegerType))
                ->description('The page number to start the pagination from.');
        }

        return [...$parameterExtractionResults, new ParametersExtractionResult($queryParameters)];
    }

    private function getJsonApiPaginator(RouteInfo $routeInfo): ?Type
    {
        $index = $routeInfo->indexBuildingBroker->getIndex(PaginateIndexBuilder::class);

        $apiPaginatorCandidates = $index->data['apiPaginatorCandidates'] ?? [];
        /** @var Scope|null $scope */
        $scope = $index->data['scope'] ?? null;

        if (! $apiPaginatorCandidates || ! $scope) {
            return null;
        }

        foreach ($apiPaginatorCandidates as $apiPaginatorCandidate) {
            $type = ReferenceTypeResolver::getInstance()->resolve($scope, $scope->getType($apiPaginatorCandidate));

            if ($type->getAttribute('jsonApiPaginator')) {
                return $type;
            }
        }

        return null;
    }
}
