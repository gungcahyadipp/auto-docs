<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData;

use Carbon\CarbonInterface;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiSchema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Type as InferType;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType as InferUnknownType;
use Dedoc\ScramblePro\Extensions\LaravelData\Exceptions\InvalidDataNormalizedType;
use Dedoc\ScramblePro\Utils;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\FromRouteParameter;
use Spatie\LaravelData\Attributes\FromRouteParameterProperty;
use Spatie\LaravelData\Attributes\PropertyForMorph;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Contracts\BaseDataCollectable;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\DataContext;
use Spatie\LaravelData\Support\Types\NamedType;
use Spatie\LaravelData\Support\Types\Type as LaravelDataType;
use Spatie\LaravelData\Support\Types\UnionType;
use Spatie\LaravelData\Support\Validation\DataRules;
use Spatie\LaravelData\Support\Validation\ValidationPath;

class DataPropertySchemaTransformer
{
    public function __construct(
        public readonly Generic $type,
        public readonly DataProperty $dataProperty,
        public readonly DataTransformConfig $config,
    ) {}

    public function toSchemaBag(TypeTransformer $openApiTransformer): SchemaBag
    {
        $type = $this->transformLaravelDataTypeToInferType($this->dataProperty->type->type);

        $schemaBag = $type instanceof InferUnknownType
            ? $this->transformRulesToSchemaBag($openApiTransformer)
            : new SchemaBag([$this->getName() => $openApiTransformer->transform($this->wrapToInputIfNeeded($type))]);

        $schemaBagSchemas = $schemaBag->all();
        $firstSchemaKey = array_key_first($schemaBagSchemas); // ??
        if ($firstSchemaKey !== null) {
            $schemaBagSchemas[$firstSchemaKey] = $this->applyPhpDocOverrides($schemaBagSchemas[$firstSchemaKey], $openApiTransformer);
        }

        return new SchemaBag($schemaBagSchemas);
    }

    protected function applyPhpDocOverrides(OpenApiSchema $schema, TypeTransformer $openApiTransformer): OpenApiSchema
    {
        /** @var ReflectionProperty|null $propertyReflection */
        $propertyReflection = rescue(fn () => (new ReflectionClass($this->type->name))->getProperty($this->dataProperty->name));

        $phpDocReflector = SchemaClassDocReflector::createFromDocString($propertyReflection?->getDocComment() ?: '', $this->type->name);

        // @todo ideally should not be needed at all, as the rules still should be applied to type and `integer` will come from transforming type to schema!
        if ($this->dataProperty->type->type instanceof NamedType && $this->dataProperty->type->type->name === 'int') {
            $schema->type = 'integer';
        }

        $schema->nullable(
            $this->config->direction === DataTransformConfig::INPUT
                ? $this->dataProperty->type->isNullable && ! $this->getFirstAttributeOfType(Required::class)
                : $this->dataProperty->type->isNullable
        );

        if ($knownTypeSchema = $this->applyKnownDataPropertyType($schema)) {
            $schema = $knownTypeSchema;
        }

        if ($this->dataProperty->cast) {
            $schema = $this->applyDataPropertyCast($schema);
        }

        if (isset($phpDocReflector->getTagValue('@var')->type) && ($propertyType = $phpDocReflector->getTagValue('@var')->type)) {
            $schema = Schema::fromType($openApiTransformer->transform(PhpDocTypeHelper::toType($propertyType)))->type;
        }

        $isString = $schema instanceof StringType;

        $schema
            ->setDescription(implode('. ', array_filter([
                trim($phpDocReflector->getDescription(), ".\n\r\t\v\0"),
                isset($phpDocReflector->getTagValue('@var')->description) ? $phpDocReflector->getTagValue('@var')->description : '',
            ])))
            ->format(array_values($phpDocReflector->phpDoc->getTagsByName('@format'))[0]->value->value ?? $schema->format)
            ->examples((new ExamplesExtractor($phpDocReflector->phpDoc))->extract(preferString: $isString))
            ->default((new ExamplesExtractor($phpDocReflector->phpDoc, '@default'))->extract(preferString: $isString)[0] ?? ($this->dataProperty->defaultValue ?? new MissingValue));

        $deprecated = $phpDocReflector->getTagValue('@deprecated');
        if ($deprecated instanceof DeprecatedTagValueNode) {
            $schema->deprecated(true);

            if ($deprecated->description) {
                $schema->setDescription($schema->description.$deprecated->description);
            }
        }

        return $schema;
    }

    protected function transformLaravelDataTypeToInferType(LaravelDataType $type): InferType
    {
        if ($this->dataProperty->attributes->has(PropertyForMorph::class)) {
            $morphType = $this->extractConcreteMorphPropertyType();

            if ($morphType) {
                return $morphType;
            }
        }

        if ($type instanceof UnionType) {
            return Union::wrap(
                array_map(fn ($t) => $this->transformLaravelDataTypeToInferType($t), $type->types),
            );
        }

        if ($type instanceof NamedType && $type->dataClass) {
            $dataType = $this->createDataType($type->dataClass);

            if ($type->dataCollectableClass && is_a($type->dataCollectableClass, BaseDataCollectable::class, true)) {
                return new Generic($type->dataCollectableClass, [
                    new InferUnknownType,
                    $dataType,
                    app(DataTypesFactory::class)->makeDataContextType(),
                ]);
            }

            if ($type->dataCollectableClass) {
                return new ArrayType($dataType);
            }

            return $dataType;
        }

        return new InferUnknownType;
    }

    protected function createDataType(string $dataClass): Generic
    {
        /*
         * Here `new *` call is simulated so the type inference system can correctly handle properties and context
         * types on the resulting type.
         */
        $normalizedType = (new ReferenceTypeResolver(app(Infer\Scope\Index::class)))->resolve(new GlobalScope, new NewCallReferenceType($dataClass, []));
        if (! $normalizedType instanceof Generic) {
            throw InvalidDataNormalizedType::expectedGeneric(type: $normalizedType, dataClass: $dataClass);
        }

        $this->preparePropertyDataContext($normalizedType->templateTypes[0 /* TDataContext */]);

        return $normalizedType;
    }

    /**
     * @see DataContext
     */
    protected function preparePropertyDataContext(Generic $propertyDataContext): void
    {
        /*
         * $dataContextType will be null when the original type comes from the annotation so the data context
         * is not relevant here.
         */
        $dataContextType = $this->type->templateTypes[0 /* TDataContext */] ?? null;
        if (! $dataContextType) {
            return;
        }

        $propertyDataContext->templateTypes[0 /* TIncludePartials */] = $this->preparePropertyDataContextPartials(
            $propertyDataContext->templateTypes[0 /* TIncludePartials */],
            $dataContextType->templateTypes[0 /* TIncludePartials */],
        );
        $propertyDataContext->templateTypes[1 /* TExcludePartials */] = $this->preparePropertyDataContextPartials(
            $propertyDataContext->templateTypes[1 /* TExcludePartials */],
            $dataContextType->templateTypes[1 /* TExcludePartials */],
        );
        $propertyDataContext->templateTypes[2 /* TOnlyPartials */] = $this->preparePropertyDataContextPartials(
            $propertyDataContext->templateTypes[2 /* TOnlyPartials */],
            $dataContextType->templateTypes[2 /* TOnlyPartials */],
        );
        $propertyDataContext->templateTypes[3 /* TExceptPartials */] = $this->preparePropertyDataContextPartials(
            $propertyDataContext->templateTypes[3 /* TExceptPartials */],
            $dataContextType->templateTypes[3 /* TExceptPartials */],
        );
    }

    protected function preparePropertyDataContextPartials(Type $propertyPartialsType, Type $rootPartialsType): Type
    {
        if (! $rootPartialsType instanceof KeyedArrayType) {
            return $propertyPartialsType;
        }

        $propertyPartials = array_map(
            fn ($propertyName) => Str::replaceStart($this->dataProperty->name.'.', '', $propertyName),
            array_filter(
                array_map(fn (ArrayItemType_ $item) => $item->value->value ?? null, $rootPartialsType->items),
                fn ($item) => $item && Str::startsWith($item, $this->dataProperty->name.'.'),
            ),
        );

        if (empty($propertyPartials)) {
            return $propertyPartialsType;
        }

        $propertyPartials = collect($propertyPartials)
            ->flatMap(Utils::splitCurlyBracesString(...))
            ->all();

        $baseType = $propertyPartialsType instanceof KeyedArrayType ? $propertyPartialsType : new KeyedArrayType;

        $baseType->items = array_merge(
            $baseType->items,
            array_map(fn ($item) => new ArrayItemType_(null, new LiteralStringType($item)), $propertyPartials),
        );

        $baseType->setAttribute('notOriginal', true);

        return $baseType;
    }

    protected function wrapToInputIfNeeded(InferType $type): InferType
    {
        return $this->config->wrapToInputType($type);
    }

    protected function transformRulesToSchemaBag(TypeTransformer $openApiTransformer): SchemaBag
    {
        $rules = $this->getRules();

        return (new RulesToParameters($rules, [], $openApiTransformer))->toSchemaBag();
    }

    /**
     * @return array<string, string[]> All validation rules related to the given data property.
     *                                 The key is prepared property name (with input/output mapping) and the value
     *                                 is the list of rules.
     */
    public function getRules(): array
    {
        $rules = app(DataValidationRulesResolver::class, [
            'ruleDenormalizer' => new RuleDenormalizer,
        ])->execute(
            $this->type->name,
            [],
            ValidationPath::create(),
            DataRules::create(),
        );

        $inputName = (new DataTransformConfig(DataTransformConfig::INPUT))->getPropertyName($this->dataProperty);

        return collect($rules)
            ->filter(fn ($_, $key) => $key === $inputName || Str::startsWith($key, "$inputName."))
            ->mapWithKeys(function ($rules, $key) use ($inputName) {
                $key = Str::replaceStart($inputName, $this->getName(), $key);

                /*
                 * When in output context, we'd want to skip `confirmed` rule, so the output schema does not have 2
                 * resulting fields.
                 */
                if (is_array($rules)) {
                    $rules = array_filter(
                        $rules,
                        fn ($r) => $this->config->direction === DataTransformConfig::INPUT || $r !== 'confirmed',
                    );
                }

                return [$key => $rules];
            })
            ->toArray();
    }

    public function getName(): string
    {
        return $this->config->getPropertyName($this->dataProperty); // ??? should have implementation here and the config is just enum??
    }

    public function shouldBePresent(): bool
    {
        // remove not validatable attributes
        if ($this->config->direction === DataTransformConfig::INPUT) {
            return $this->shouldKeepInputProperty();
        }

        return $this->shouldKeepOutputProperty();
    }

    private function getFirstAttributeOfType(string $className)
    {
        // The next line is reported as always true by PHPStan, but this is not true when Laravel Data version is < 4.14
        if ($this->dataProperty->attributes instanceof \Spatie\LaravelData\Support\DataAttributesCollection) { // @phpstan-ignore instanceof.alwaysTrue
            return $this->dataProperty->attributes->first($className);
        }

        return $this->dataProperty->attributes->first(fn ($attr) => is_a($attr::class, $className, true));
    }

    public function isRequired(): bool
    {
        if ($this->config->direction === DataTransformConfig::INPUT) {
            if ($this->getPropertyFromRouteAttribute() && ! $this->getPropertyFromRouteAttribute()->replaceWhenPresentInBody) {
                return false;
            }

            if ($this->dataProperty->type->isOptional) {
                return false;
            }

            if (
                $this->dataProperty->type->isNullable
                && ! ($this->getFirstAttributeOfType(Required::class) ?: $this->getFirstAttributeOfType(Present::class))
            ) {
                return false;
            }

            if ($this->dataProperty->hasDefaultValue) {
                return false;
            }

            return true;
        }

        if (
            $this->hasOnlyPartialsDefined(conditional: true)
            && ! $this->isPropertyInOnlyPartials(conditional: true)
        ) {
            return false;
        }

        if (
            $this->hasExceptPartialsDefined(conditional: true)
            && $this->isPropertyInExceptPartials(conditional: true)
        ) {
            return false;
        }

        if ($this->dataProperty->type->isOptional) {
            return false;
        }

        if ($this->dataProperty->type->lazyType) {
            return $this->isPropertyIncluded();
        }

        return true;
    }

    protected function shouldKeepOutputProperty(): bool
    {
        if ($this->dataProperty->hidden) {
            return false;
        }

        if ($this->hasConditionalExceptOrOnlyDefined()) {
            return ! $this->isPropertyExcluded();
        }

        if (
            $this->hasExceptPartialsDefined()
            && $this->isPropertyInExceptPartials()
        ) {
            return false;
        }

        if ($this->hasOnlyPartialsDefined()) {
            return $this->isPropertyInOnlyPartials();
        }

        if (! $this->dataProperty->type->lazyType) {
            return true;
        }

        return ! $this->isPropertyExcluded();
    }

    protected function shouldKeepInputProperty(): bool
    {
        $fromRouteAttribute = $this->getPropertyFromRouteAttribute();

        return $this->dataProperty->validate
            && ! $fromRouteAttribute?->replaceWhenPresentInBody;
    }

    protected function isPropertyExcluded(): bool
    {
        $excludes = $this->type->templateTypes[0/* TDataContext */]->templateTypes[1/* TExcludes */] ?? null;

        if (! $excludes instanceof KeyedArrayType) {
            return false;
        }

        $excludesNames = array_values(array_filter(array_map(
            fn (ArrayItemType_ $item) => $item->value->value ?? null,
            $excludes->items,
        )));

        return in_array($this->dataProperty->name, $excludesNames) || in_array('*', $excludesNames);
    }

    protected function hasOnlyPartialsDefined(?bool $conditional = null): bool
    {
        $only = $this->type->templateTypes[0/* TDataContext */]->templateTypes[2/* TOnlyPartials */] ?? null;

        if (! $only instanceof KeyedArrayType) {
            return false;
        }

        if (! count($only->items)) {
            return false;
        }

        if ($conditional === null) {
            return true;
        }

        return collect($only->items)->contains(fn (ArrayItemType_ $item) => $item->value->getAttribute('conditional', false) === $conditional);
    }

    protected function hasExceptPartialsDefined(?bool $conditional = null): bool
    {
        $except = $this->type->templateTypes[0/* TDataContext */]->templateTypes[3/* TExceptPartials */] ?? null;

        if (! $except instanceof KeyedArrayType) {
            return false;
        }

        if (! count($except->items)) {
            return false;
        }

        if ($conditional === null) {
            return true;
        }

        return collect($except->items)->contains(fn (ArrayItemType_ $item) => $item->value->getAttribute('conditional', false) === $conditional);
    }

    protected function hasConditionalExceptOrOnlyDefined(): bool
    {
        return $this->hasExceptPartialsDefined(conditional: true)
            || $this->hasOnlyPartialsDefined(conditional: true);
    }

    protected function isPropertyInOnlyPartials(?bool $conditional = null): bool
    {
        $only = $this->type->templateTypes[0/* TDataContext */]->templateTypes[2/* TOnlyPartials */] ?? null;

        if (! $only instanceof KeyedArrayType) {
            return false;
        }

        return $this->isPropertyInPartials($only, $conditional);
    }

    protected function isPropertyInExceptPartials(?bool $conditional = null): bool
    {
        $except = $this->type->templateTypes[0/* TDataContext */]->templateTypes[3/* TExceptPartials */] ?? null;

        if (! $except instanceof KeyedArrayType) {
            return false;
        }

        return $this->isPropertyInPartials($except, $conditional);
    }

    protected function isPropertyInPartials(KeyedArrayType $type, ?bool $conditional = null): bool
    {
        $propertyPartial = collect($type->items)
            ->first(fn (ArrayItemType_ $item) => ($item->value->value ?? null) === $this->dataProperty->name);

        if (! $propertyPartial) {
            return false;
        }

        /** @var ArrayItemType_ $propertyPartial */
        if ($conditional !== null) {
            return $propertyPartial->value->getAttribute('conditional', false) === $conditional;
        }

        return true;
    }

    public function getPropertyFromRouteAttribute(): FromRouteParameter|FromRouteParameterProperty|null
    {
        return $this->getFirstAttributeOfType(FromRouteParameter::class);
    }

    protected function isPropertyIncluded(): bool
    {
        return $this->isDefaultIncluded() || $this->isIncludedViaIncludeMethod();
    }

    protected function isDefaultIncluded(): bool
    {
        $propertyName = $this->dataProperty->name;

        $propertyType = (new ReferenceTypeResolver(app(Infer::class)->index))->resolve(new GlobalScope, new PropertyFetchReferenceType($this->type, $propertyName));

        $defaultIncludedType = $propertyType instanceof Generic
            ? ($propertyType->templateTypes[0/* TDefaultIncluded */] ?? null)
            : null;

        if (! $defaultIncludedType instanceof LiteralBooleanType) {
            return false;
        }

        return $defaultIncludedType->value === true;
    }

    protected function isIncludedViaIncludeMethod(): bool
    {
        $includes = $this->type->templateTypes[0/* TDataContext */]->templateTypes[0/* TIncludes */] ?? null;

        if (! $includes instanceof KeyedArrayType) {
            return false;
        }

        $includesNames = array_values(array_filter(array_map(
            fn (ArrayItemType_ $item) => $item->value->value ?? null,
            $includes->items,
        )));

        return in_array($this->dataProperty->name, $includesNames) || in_array('*', $includesNames);
    }

    protected function applyDataPropertyCast(OpenApiSchema $schema): OpenApiSchema
    {
        return match (true) {
            is_a($this->dataProperty->cast::class, DateTimeInterfaceCast::class, true) => (new StringType)->addProperties($schema)->format('date-time'),
            default => $schema,
        };
    }

    protected function applyKnownDataPropertyType(OpenApiSchema $schema): OpenApiSchema
    {
        return match (true) {
            $this->dataProperty->type->type instanceof NamedType
                && is_a($this->dataProperty->type->type->name, CarbonInterface::class, true) => (new StringType)->addProperties($schema)->format('date-time'),
            default => $schema,
        };
    }

    public function inputAndOutputSchemasAreSame(): bool
    {
        $dataPropertyInputTransformer = new static($this->type, $this->dataProperty, new DataTransformConfig(DataTransformConfig::INPUT));
        $dataPropertyOutputTransformer = new static($this->type, $this->dataProperty, new DataTransformConfig(DataTransformConfig::OUTPUT));

        if ($dataPropertyInputTransformer->shouldBePresent() !== $dataPropertyOutputTransformer->shouldBePresent()) {
            return false;
        }

        if ($dataPropertyInputTransformer->isRequired() !== $dataPropertyOutputTransformer->isRequired()) {
            return false;
        }

        if ($dataPropertyInputTransformer->getRules() !== $dataPropertyOutputTransformer->getRules()) {
            return false;
        }

        /**
         * "A data collection inside a data object WILL get wrapped when a wrapping key is set"
         *
         * @see https://spatie.be/docs/laravel-data/v4/as-a-resource/wrapping#content-nested-wrapping
         */
        if ($this->dataProperty->type->dataCollectableClass && $this->getGlobalWrapKey($this->dataProperty->type->dataClass)) {
            return false;
        }

        return true;
    }

    protected function getGlobalWrapKey(string $dataClass)
    {
        $classDefinition = app(Infer::class)->analyzeClass($dataClass);
        $defaultWrapType = $classDefinition->getMethodCallType('defaultWrap');

        return ($defaultWrapType instanceof LiteralStringType ? $defaultWrapType->value : null) ?: config('data.wrap');
    }

    protected function extractConcreteMorphPropertyType(): ?Type
    {
        if ((new \ReflectionClass($this->type->name))->isAbstract()) { // @phpstan-ignore argument.type
            return null;
        }

        return app(MorphPropertyTypeResolver::class)->resolve($this->type, $this->dataProperty);
    }
}
