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
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ScrambleProServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('scramble-pro');
    }

    public function bootingPackage()
    {
        $this->registerLaravelDataExtensions();
        $this->registerLaravelQueryBuilderExtensions();
        $this->registerJsonApiExtensions();
        $this->registerLaravelJsonApiPaginateExtensions();
        $this->registerLaravelActionsExtensions();
        $this->registerOperationTransformers();
        $this->registerIgnoredFormRequestTypes();
    }

    protected function registerLaravelDataExtensions(): void
    {
        if (! class_exists(\Spatie\LaravelData\Data::class)) {
            return;
        }

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
        ]);

        Scramble::infer()
            ->configure()
            ->buildDefinitionsUsingAstFor([
                new ClassLikeAndChildren(\Spatie\LaravelData\Lazy::class),
            ]);

        Scramble::configure()
            ->withDocumentTransformers(LaravelDataContextualNamesTransformer::class);
    }

    protected function registerLaravelQueryBuilderExtensions(): void
    {
        if (! class_exists(\Spatie\QueryBuilder\QueryBuilder::class)) {
            return;
        }

        Scramble::registerExtensions([
            LaravelQueryBuilder\Infer\AllowedFilterMethodsExtension::class,
            LaravelQueryBuilder\Infer\AllowedSortMethodsExtension::class,
            LaravelQueryBuilder\Infer\AllowedIncludeMethodsExtension::class,
            LaravelQueryBuilder\Infer\QueryBuilderMethodsExtension::class,
            LaravelQueryBuilder\Infer\QueryBuilderDefinitionExtension::class,
            LaravelQueryBuilder\Infer\QueryBuilderEffectExtension::class,

            LaravelQueryBuilder\Index\QueryRequestIndexBuilder::class,
        ]);
    }

    protected function registerJsonApiExtensions(): void
    {
        if (! class_exists(\TiMacDonald\JsonApi\JsonApiResource::class)) {
            return;
        }

        Scramble::registerExtensions([
            JsonApi\Generator\JsonApiResourceResponseToSchemaExtension::class,
            JsonApi\Generator\JsonApiPaginatedResourceResponseToSchemaExtension::class,
            JsonApi\Generator\JsonApiResourceToSchemaExtension::class,
            JsonApi\Generator\JsonApiResourceCollectionToSchemaExtension::class,
            JsonApi\Generator\ResourceIdentifierToSchemaExtension::class,
            JsonApi\Generator\LinkToSchemaExtension::class,

            JsonApi\Infer\AfterJsonApiResourceDefinitionCreatedExtension::class,
            JsonApi\Infer\ResourceMethodsExtension::class,
            JsonApi\Infer\ResourceCollectionMethodsExtension::class,
        ]);

        Scramble::infer()
            ->configure()
            ->buildDefinitionsUsingAstFor([
                \TiMacDonald\JsonApi\Link::class,
            ]);

        Context::getInstance()->extensionsBroker->priority([
            JsonApi\Infer\ResourceMethodsExtension::class,
            ScrambleExtensions\JsonResourceExtension::class,
        ]);
    }

    protected function registerLaravelJsonApiPaginateExtensions(): void
    {
        if (! class_exists(\Spatie\JsonApiPaginate\JsonApiPaginateServiceProvider::class)) {
            return;
        }

        Scramble::registerExtensions([
            LaravelJsonApiPaginate\Index\PaginateIndexBuilder::class,
            LaravelJsonApiPaginate\Infer\PaginateMethodExtension::class,
        ]);
    }

    protected function registerLaravelActionsExtensions(): void
    {
        if (! trait_exists(\Lorisleiva\Actions\Concerns\AsAction::class)) {
            return;
        }

        // Laravel Actions operation transformers and parameter extractors
        // are registered in registerOperationTransformers()
    }

    protected function registerOperationTransformers(): void
    {
        $prependTransformers = [];
        $appendTransformers = [];
        $parameterExtractors = [];

        if (trait_exists(\Lorisleiva\Actions\Concerns\AsAction::class)) {
            $prependTransformers[] = LaravelActions\Generator\PatchRouteAction::class;
            $parameterExtractors[] = LaravelActions\Generator\ActionParametersExtractor::class;
        }

        if (class_exists(\Spatie\LaravelData\Data::class)) {
            $appendTransformers[] = LaravelData\Generator\DataRequestExtension::class;
        }

        if (class_exists(\Spatie\QueryBuilder\QueryBuilder::class)) {
            $parameterExtractors[] = LaravelQueryBuilder\Generator\QueryBuilderParametersExtractor::class;
        }

        if (class_exists(\Spatie\JsonApiPaginate\JsonApiPaginateServiceProvider::class)) {
            $parameterExtractors[] = LaravelJsonApiPaginate\Generator\PaginateParameterExtractor::class;
        }

        if ($prependTransformers || $appendTransformers || $parameterExtractors) {
            Scramble::configure()
                ->withOperationTransformers(function (OperationTransformers $transformers) use ($prependTransformers, $appendTransformers) {
                    if ($prependTransformers) {
                        $transformers->prepend($prependTransformers);
                    }
                    if ($appendTransformers) {
                        $transformers->append($appendTransformers);
                    }
                })
                ->withParametersExtractors(function (ParametersExtractors $extractors) use ($parameterExtractors) {
                    if ($parameterExtractors) {
                        $extractors->append($parameterExtractors);
                    }
                });
        }
    }

    protected function registerIgnoredFormRequestTypes(): void
    {
        $ignoreClasses = [];

        if (class_exists(\Spatie\LaravelData\Contracts\BaseData::class)) {
            $ignoreClasses[] = \Spatie\LaravelData\Contracts\BaseData::class;
        }

        if (class_exists(\Lorisleiva\Actions\ActionRequest::class)) {
            $ignoreClasses[] = \Lorisleiva\Actions\ActionRequest::class;
        }

        if ($ignoreClasses) {
            FormRequestParametersExtractor::ignoreInstanceOf($ignoreClasses);
        }
    }
}
