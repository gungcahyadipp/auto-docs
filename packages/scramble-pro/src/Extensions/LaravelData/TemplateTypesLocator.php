<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\TemplateType;
use LogicException;

class TemplateTypesLocator
{
    public function findDataContextTemplateType(ClassDefinition $definition, Generic $type, string $templateName)
    {
        if (! $type->isInstanceOf($definition->name)) {
            throw new LogicException("Type [$definition->name] is not instance of provided definition [$definition->name]");
        }

        $dataContextTemplateTypeIndex = collect($definition->templateTypes)->search(fn (TemplateType $tt) => $tt->name === $templateName);

        if ($dataContextTemplateTypeIndex !== false) {
            return $type->templateTypes[$dataContextTemplateTypeIndex];
        }

        return null;
    }
}
