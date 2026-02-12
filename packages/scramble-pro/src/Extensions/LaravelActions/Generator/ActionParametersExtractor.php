<?php

namespace Dedoc\ScramblePro\Extensions\LaravelActions\Generator;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\TypeBasedRulesDocumentationRetriever;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\ComposedFormRequestRulesEvaluator;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\GeneratesParametersFromRules;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use PhpParser\PrettyPrinter;

class ActionParametersExtractor implements ParameterExtractor
{
    use ChecksIsLaravelActions;
    use GeneratesParametersFromRules;

    public function __construct(
        private PrettyPrinter $printer,
        private TypeTransformer $openApiTransformer,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        if (! $this->isLaravelActionController($routeInfo)) {
            return $parameterExtractionResults;
        }

        if (! $actionClass = $routeInfo->className()) {
            return $parameterExtractionResults;
        }

        if (! method_exists($actionClass, 'rules')) {
            return $parameterExtractionResults;
        }

        return [
            ...$parameterExtractionResults,
            $this->extractParametersFromActionRules($routeInfo, $actionClass),
        ];
    }

    private function extractParametersFromActionRules(RouteInfo $routeInfo, string $actionClass): ParametersExtractionResult
    {
        $classReflector = Infer\Reflector\ClassReflector::make($actionClass);

        return new ParametersExtractionResult(
            parameters: $this->makeParameters(
                rules: (new ComposedFormRequestRulesEvaluator($this->printer, $classReflector, $routeInfo->method))->handle(),
                typeTransformer: $this->openApiTransformer,
                rulesDocsRetriever: new TypeBasedRulesDocumentationRetriever(
                    $routeInfo->getScope(),
                    new MethodCallReferenceType(new ObjectType($actionClass), 'rules', []),
                ),
                in: in_array(mb_strtolower($routeInfo->method), RequestBodyExtension::HTTP_METHODS_WITHOUT_REQUEST_BODY)
                    ? 'query'
                    : 'body',
            ),
        );
    }
}
