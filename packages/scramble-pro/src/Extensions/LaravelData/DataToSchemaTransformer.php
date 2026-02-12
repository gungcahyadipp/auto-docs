<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\SchemaBagToParametersTransformer;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelData\Exceptions\InvalidDataNormalizedType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Types\CombinationType as LaravelDataCombinationType;

class DataToSchemaTransformer
{
    use TransformsPropertyMorphableData;

    public function __construct(
        private Infer $infer,
        private TypeTransformer $openApiTransformer,
    ) {}

    public function transform(ObjectType $type, DataTransformConfig $config)
    {
        $type = $this->normalizeType($type);

        $classDefinition = $this->infer->analyzeClass($type->name);

        if ($this->isPropertyMorphableData($classDefinition)) {
            return $this->transformPropertyMorphableData($classDefinition);
        }

        /** @var Collection<int, DataProperty> $dataProperties */
        $dataProperties = app(DataConfig::class)->getDataClass($type->name)->properties;

        $dataSchemaBag = new SchemaBag;

        foreach ($dataProperties as $dataProperty) {
            $dataPropertyTransformer = new DataPropertySchemaTransformer($type, $dataProperty, $config);

            if (! $dataPropertyTransformer->shouldBePresent()) {
                continue;
            }

            $dataPropertySchemas = $dataPropertyTransformer->toSchemaBag($this->openApiTransformer)->all();

            foreach ($dataPropertySchemas as $name => $dataPropertySchema) {
                if (Str::contains($name, '.')) {
                    continue;
                }
                $dataPropertySchema->setAttribute('required', $dataPropertyTransformer->isRequired());
            }

            $dataSchemaBag = new SchemaBag(array_merge(
                $dataSchemaBag->all(),
                $dataPropertySchemas,
            ));
        }

        $parameters = (new SchemaBagToParametersTransformer($this->openApiTransformer))
            ->handle($dataSchemaBag);

        return Schema::createFromParameters($parameters)
            ->type
            ->setDescription($this->getDocReflection($type->name)->getDescription());
    }

    public function inputAndOutputSchemasAreSame(ObjectType $type, array &$checkedTypes = []): bool
    {
        $type = $this->normalizeType($type);

        $checkedTypes[$type->name] = true;

        /** @var Collection<int, DataProperty> $dataProperties */
        $dataProperties = app(DataConfig::class)->getDataClass($type->name)->properties;

        foreach ($dataProperties as $dataProperty) {
            $dataPropertyTransformer = new DataPropertySchemaTransformer($type, $dataProperty, new DataTransformConfig(DataTransformConfig::INPUT));

            if (! $dataPropertyTransformer->inputAndOutputSchemasAreSame()) {
                return false;
            }

            $dataClasses = $dataProperty->type->type instanceof LaravelDataCombinationType
                ? array_map(fn ($t) => $t->dataClass ?? null, $dataProperty->type->type->types)
                : [$dataProperty->type->type->dataClass ?? null];

            $dataClasses = array_values(array_filter($dataClasses));

            foreach ($dataClasses as $dataClass) {
                if (! isset($checkedTypes[$dataClass])) {
                    $areSame = $this->inputAndOutputSchemasAreSame($this->normalizeType(new ObjectType($dataClass)), $checkedTypes);

                    if (! $areSame) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function getDocReflection(string $className)
    {
        return SchemaClassDocReflector::createFromClassName($className);
    }

    private function normalizeType(Type $type): Generic
    {
        /**
         * When type is inferred from a param typehint, it is simplified to object form. We however want to make sure
         * to always keep it as Generic, so we can work with it as such. To keep all the parameters type correctly,
         * we need to use reference type resolve and resolve a new call reference type.
         */
        if (! $type instanceof Generic) {
            // Normalizing template type which can be coming from type annotations.
            $type = $type instanceof TemplateType ? $type->is : $type;

            $normalizedType = (new ReferenceTypeResolver($this->infer->index))->resolve(new GlobalScope, new NewCallReferenceType($type->name, []));

            if (! $normalizedType instanceof Generic) {
                throw InvalidDataNormalizedType::expectedGeneric(type: $normalizedType, dataClass: $type->name);
            }

            return $normalizedType;
        }

        return $type;
    }
}
