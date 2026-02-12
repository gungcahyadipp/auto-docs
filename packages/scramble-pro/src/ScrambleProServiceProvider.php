<?php

namespace Dedoc\ScramblePro;

use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Configuration\ParametersExtractors;
use Dedoc\Scramble\Infer\Configuration\ClassLikeAndChildren;
use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\InferExtensions as ScrambleExtensions;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Dedoc\ScramblePro\Extensions\JsonApi;
use Dedoc\ScramblePro\Extensions\LaravelActions;
use Dedoc\ScramblePro\Extensions\LaravelData;
use Dedoc\ScramblePro\Extensions\LaravelData\OpenApiDocumentTransformers\LaravelDataContextualNamesTransformer;
use Dedoc\ScramblePro\Extensions\LaravelJsonApiPaginate;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use TiMacDonald\JsonApi\Link;

class ScrambleProServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('scramble-pro');
    }

    public function bootingPackage()
    {
        Scramble::registerExtensions([
            LaravelData\Infer\AfterContextableDataClassDefinitionCreatedExtension::class,
            LaravelData\Infer\AfterBaseDataCollectableClassDefinitionCreatedExtension::class,

            LaravelData\Infer\DataCollectionMethodsExtension::class,
            LaravelData\Infer\DataMethodsExtension::class,
            LaravelData\Infer\WrappableDataMethodsExtension::class,
            LaravelData\Infer\IncludeableDataMethodsExtension::class,
            LaravelData\Infer\DataStaticCreationMethodsExtension::class,
            LaravelData\Infer\DataModelCastsExtension::class,

            LaravelData\Infer\LazyDefinitionExtension::class,

            LaravelData\Generator\DataCollectionSchemaExtension::class,
            LaravelData\Generator\DataTransformedSchemaExtension::class,
            LaravelData\Generator\DataSchemaExtension::class,
            LaravelData\Generator\InputDataSchemaExtension::class,

            LaravelQueryBuilder\Infer\AllowedFilterMethodsExtension::class,
            LaravelQueryBuilder\Infer\AllowedSortMethodsExtension::class,
            LaravelQueryBuilder\Infer\AllowedIncludeMethodsExtension::class,
            LaravelQueryBuilder\Infer\QueryBuilderMethodsExtension::class,
            LaravelQueryBuilder\Infer\QueryBuilderDefinitionExtension::class,
            LaravelQueryBuilder\Infer\QueryBuilderEffectExtension::class,

            LaravelQueryBuilder\Index\QueryRequestIndexBuilder::class,

            JsonApi\Generator\JsonApiResourceResponseToSchemaExtension::class,
            JsonApi\Generator\JsonApiPaginatedResourceResponseToSchemaExtension::class,
            JsonApi\Generator\JsonApiResourceToSchemaExtension::class,
            JsonApi\Generator\JsonApiResourceCollectionToSchemaExtension::class,
            JsonApi\Generator\ResourceIdentifierToSchemaExtension::class,
            JsonApi\Generator\LinkToSchemaExtension::class,

            JsonApi\Infer\AfterJsonApiResourceDefinitionCreatedExtension::class,
            JsonApi\Infer\ResourceMethodsExtension::class,
            JsonApi\Infer\ResourceCollectionMethodsExtension::class,

            LaravelJsonApiPaginate\Index\PaginateIndexBuilder::class,
            LaravelJsonApiPaginate\Infer\PaginateMethodExtension::class,
        ]);

        Scramble::infer()
            ->configure()
            ->buildDefinitionsUsingAstFor([
                Link::class,
                new ClassLikeAndChildren(Lazy::class),
            ]);

        Scramble::configure()
            ->withOperationTransformers(function (OperationTransformers $transformers) {
                $transformers
                    ->prepend([
                        LaravelActions\Generator\PatchRouteAction::class,
                    ])
                    ->append([
                        LaravelData\Generator\DataRequestExtension::class,
                    ]);
            })
            ->withParametersExtractors(function (ParametersExtractors $extractors) {
                $extractors->append([
                    LaravelActions\Generator\ActionParametersExtractor::class,
                    LaravelQueryBuilder\Generator\QueryBuilderParametersExtractor::class,
                    LaravelJsonApiPaginate\Generator\PaginateParameterExtractor::class,
                ]);
            })
            ->withDocumentTransformers(LaravelDataContextualNamesTransformer::class);

        Context::getInstance()->extensionsBroker->priority([
            JsonApi\Infer\ResourceMethodsExtension::class,
            ScrambleExtensions\JsonResourceExtension::class,
        ]);

        FormRequestParametersExtractor::ignoreInstanceOf([
            \Spatie\LaravelData\Contracts\BaseData::class,
            \Lorisleiva\Actions\ActionRequest::class,
        ]);
    }
}
