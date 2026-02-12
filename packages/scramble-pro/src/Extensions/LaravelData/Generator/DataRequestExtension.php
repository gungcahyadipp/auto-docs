<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Generator;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelData\DataPropertySchemaTransformer;
use Dedoc\ScramblePro\Extensions\LaravelData\DataTransformConfig;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\DataConfig;

class DataRequestExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        $laravelDataParameters = collect($routeInfo->getActionType()?->arguments)
            ->filter()
            ->filter(fn (Type $argument) => $argument->isInstanceOf(Data::class));

        if ($laravelDataParameters->isEmpty()) {
            return;
        }

        $this->attachValidationErrorToResponses($operation);

        $laravelDataClass = $laravelDataParameters
            ->map(fn (Type $argument) => $argument instanceof TemplateType ? $argument->is->name : $argument->name)
            ->values()
            ->first();

        $this->infer->analyzeClass($laravelDataClass);

        $config = new DataTransformConfig(DataTransformConfig::INPUT);

        // Narrow path parameters if `FromRouteParameter` is used.

        /** @var Reference $schemaReference */
        $schemaReference = $this->openApiTransformer->transform($config->wrapToInputType($type = new Generic($laravelDataClass)));
        $schema = $schemaReference->resolve()->type;

        $this->applyParametersFromRouteParametersAttributes($type, $laravelDataClass, $operation);

        if (in_array($operation->method, RequestBodyExtension::HTTP_METHODS_WITHOUT_REQUEST_BODY)) {
            $laravelDataParameters = collect($schema->properties)
                ->map(fn ($type, $name) => Parameter::make($name, 'query')->setSchema(Schema::fromType($type))->required(in_array($name, $schema->required)))
                ->values()
                ->toArray();

            $operation->addParameters($laravelDataParameters);

            return;
        }

        if (! $operation->requestBodyObject) {
            $operation->requestBodyObject = RequestBodyObject::make()->setContent(
                $this->getMediaType($operation, $routeInfo, []),
                Schema::fromType(new \Dedoc\Scramble\Support\Generator\Types\ObjectType),
            );
        }

        // @todo What should we do when there are non Laravel Data parameters?
        $operation
            ->requestBodyObject
            ->description('`'.$schemaReference->getUniqueName().'`');

        $operation->requestBodyObject->setContent(
            array_keys($operation->requestBodyObject->content)[0] ?? 'application/json',
            $schemaReference,
        )->required($this->isSchemaRequired($schemaReference));
    }

    // @todo: Use it from dedoc/scramble package
    protected function getMediaType(Operation $operation, RouteInfo $routeInfo, array $bodyParams): string
    {
        if (
            ($mediaTags = $routeInfo->phpDoc()->getTagsByName('@requestMediaType'))
            && ($mediaType = trim(Arr::first($mediaTags)?->value?->value))
        ) {
            return $mediaType;
        }

        $jsonMediaType = 'application/json';

        if ($operation->method === 'get') {
            return $jsonMediaType;
        }

        return $this->hasBinary($bodyParams) ? 'multipart/form-data' : $jsonMediaType;
    }

    protected function isSchemaRequired(Reference|Schema $schema): bool
    {
        $schema = $schema instanceof Reference
            ? $schema->resolve()
            : $schema;

        $type = $schema instanceof Schema ? $schema->type : $schema;

        if ($type instanceof \Dedoc\Scramble\Support\Generator\Types\ObjectType) {
            return count($type->required) > 0;
        }

        return false;
    }

    protected function hasBinary($bodyParams): bool
    {
        return collect($bodyParams)->contains(function (Parameter $parameter) {
            // @todo: Use OpenApi document tree walker when ready
            $parameterString = json_encode($parameter->toArray());

            return Str::contains($parameterString, '"contentMediaType":"application\/octet-stream"');
        });
    }

    protected function attachValidationErrorToResponses(Operation $operation)
    {
        if (collect($operation->responses)->where('code', 422)->count()) {
            return;
        }

        if (! $response = $this->openApiTransformer->toResponse(new ObjectType(ValidationException::class))) {
            return;
        }

        $operation->responses[] = $response;
    }

    protected function applyParametersFromRouteParametersAttributes($type, string $laravelDataClass, Operation $operation): void
    {
        $dataProperties = app(DataConfig::class)->getDataClass($laravelDataClass)->properties;

        $propertiesWithFromRouteParametersAttribute = collect();

        foreach ($dataProperties as $dataProperty) {
            $dataPropertySchemaTransformer = new DataPropertySchemaTransformer($type, $dataProperty, new DataTransformConfig(DataTransformConfig::INPUT));

            if (! $propertyFromAttribute = $dataPropertySchemaTransformer->getPropertyFromRouteAttribute()) {
                continue;
            }

            $schema = $dataPropertySchemaTransformer->toSchemaBag($this->openApiTransformer)->firstOrFail();

            $parameter = Parameter::make($propertyFromAttribute->routeParameter, 'path')
                ->description($schema->description)
                ->setSchema(Schema::fromType(tap($schema, fn ($s) => $s->description = '')));

            $propertiesWithFromRouteParametersAttribute->offsetSet($propertyFromAttribute->routeParameter, $parameter);
        }

        foreach ($operation->parameters as &$parameter) {
            $parameter = $propertiesWithFromRouteParametersAttribute->get($parameter->name, $parameter);
        }
    }
}
