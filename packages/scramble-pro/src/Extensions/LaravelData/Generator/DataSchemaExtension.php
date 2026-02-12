<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Generator;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelData\DataToSchemaTransformer;
use Dedoc\ScramblePro\Extensions\LaravelData\DataTransformConfig;
use Dedoc\ScramblePro\Extensions\LaravelData\Exceptions\InvalidDataNormalizedType;
use Dedoc\ScramblePro\Extensions\LaravelData\TemplateTypesLocator;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Support\Wrapping\WrapType;

class DataSchemaExtension extends TypeToSchemaExtension
{
    public function __construct(Infer $infer, TypeTransformer $openApiTransformer, Components $components, protected OpenApiContext $openApiContext)
    {
        parent::__construct($infer, $openApiTransformer, $components);
    }

    public function shouldHandle(Type $type)
    {
        return $type->isInstanceOf(BaseData::class);
    }

    private function hasNotReferencedSchemaParts(Type $type)
    {
        $hasDefaultIncluded = $type instanceof Generic && collect($type->templateTypes)
            ->filter(fn ($t) => $t instanceof Generic && $t->isInstanceOf(Lazy::class))
            ->contains(fn ($t) => ($t->templateTypes[0] ?? null) instanceof LiteralBooleanType && $t->templateTypes[0]->value === true);

        return $hasDefaultIncluded
            || ($type->templateTypes[0/* TDataContext */]->templateTypes[0/* TIncludesPartials */] ?? null)?->getAttribute('notOriginal')
            || ($type->templateTypes[0/* TDataContext */]->templateTypes[1/* TExcludesPartials */] ?? null)?->getAttribute('notOriginal')
            || ($type->templateTypes[0/* TDataContext */]->templateTypes[2/* TOnlyPartials */] ?? null)?->getAttribute('notOriginal')
            || ($type->templateTypes[0/* TDataContext */]->templateTypes[3/* TExceptPartials */] ?? null)?->getAttribute('notOriginal');
    }

    public function toSchema(Type $type)
    {
        $config = new DataTransformConfig(DataTransformConfig::OUTPUT);

        return (new DataToSchemaTransformer($this->infer, $this->openApiTransformer))->transform($type, $config);
    }

    public function toResponse(Type $type)
    {
        $type = $this->normalizeType($type);

        $openApiType = $this->openApiTransformer->transform($type);

        $dataContextType = app(TemplateTypesLocator::class)->findDataContextTemplateType(
            $this->infer->analyzeClass($type->name),
            $type,
            'TDataContext',
        );

        $wrapKey = $this->getWrapKey($type->name, $dataContextType?->templateTypes[4 /* TWrap */] ?? null);

        if ($wrapKey) {
            $openApiType = (new \Dedoc\Scramble\Support\Generator\Types\ObjectType)
                ->addProperty($wrapKey, $openApiType)
                ->setRequired([$wrapKey]);
        }

        return Response::make(200)
            ->description('`'.$this->openApiContext->references->schemas->uniqueName($type->name).'`')
            ->setContent(
                'application/json',
                Schema::fromType($openApiType),
            );
    }

    public function reference(ObjectType $type)
    {
        if ($this->hasNotReferencedSchemaParts($type)) {
            return null;
        }

        return $this->attachTypeContextToReference(ClassBasedReference::create('schemas', $type->name, $this->components), $type);
    }

    private function attachTypeContextToReference(Reference $reference, ObjectType $type): Reference
    {
        $reference->setAttribute('laravelDataType', $type);
        $reference->setAttribute('laravelDataContext', DataTransformConfig::OUTPUT);

        return $reference;
    }

    public function getDocReflection(string $className)
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

    private function getWrapKey(string $dataClass, ?Type $wrapType)
    {
        if (! $wrapType) {
            return null;
        }

        $wrapIsDisabled = ($wrapTypeType = $wrapType->templateTypes[0 /* TType */] ?? null) instanceof LiteralStringType
            && $wrapTypeType->value === WrapType::Disabled->value;

        if ($wrapIsDisabled) {
            return null;
        }

        $classDefinition = $this->infer->analyzeClass($dataClass);
        $defaultWrapType = $classDefinition->getMethodCallType('defaultWrap');

        $globalWrapKey = ($defaultWrapType instanceof LiteralStringType ? $defaultWrapType->value : null) ?: config('data.wrap');

        $wrapKeyType = $wrapType->templateTypes[1 /* TKey */] ?? null;

        if (! $wrapKeyType instanceof LiteralStringType) {
            return $globalWrapKey;
        }

        return $wrapKeyType->value;
    }
}
