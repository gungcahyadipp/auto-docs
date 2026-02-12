<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Generator;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelData\DataToSchemaTransformer;
use Dedoc\ScramblePro\Extensions\LaravelData\DataTransformConfig;
use Spatie\LaravelData\Contracts\BaseDataCollectable;

class InputDataSchemaExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic && $type->name === DataTransformConfig::SYNTHETIC_INPUT_CLASS;
    }

    private function dataToSchemaTransformer(): DataToSchemaTransformer
    {
        return new DataToSchemaTransformer($this->infer, $this->openApiTransformer);
    }

    /**
     * @param  Generic  $type
     */
    public function toSchema(Type $type)
    {
        $config = new DataTransformConfig(DataTransformConfig::INPUT);

        return $this->dataToSchemaTransformer()->transform(
            $this->getDataType($type),
            $config,
        );
    }

    public function reference(Generic $type): Reference
    {
        $dataType = $this->getDataType($type);
        $dataClass = $dataType->name;

        $reference = $this->attachTypeContextToReference(
            ClassBasedReference::createInput('schemas', $dataClass, $this->components),
            $dataType,
        );

        if (
            class_exists($reference->fullName)
            && ! $this->dataToSchemaTransformer()->inputAndOutputSchemasAreSame($dataType)
        ) {
            if ($reference->shortName) {
                $reference->shortName .= 'Request';
            }
            $reference->fullName .= 'Request';
            $reference->setAttribute('laravelDataDifferentInputAndOutput', true);

            return $reference;
        }

        return $reference;
    }

    private function attachTypeContextToReference(Reference $reference, ObjectType $type): Reference
    {
        $reference->setAttribute('laravelDataType', $type);
        $reference->setAttribute('laravelDataContext', DataTransformConfig::INPUT);

        return $reference;
    }

    private function isDataCollection(Generic $type): bool
    {
        return is_a($type->templateTypes[0]->name, BaseDataCollectable::class, true);
    }

    private function getDataType(Generic $type): ObjectType
    {
        if ($this->isDataCollection($type)) {
            return new ObjectType($type->templateTypes[0]->templateTypes[1]->value);
        }

        return $type->templateTypes[0];
    }
}
