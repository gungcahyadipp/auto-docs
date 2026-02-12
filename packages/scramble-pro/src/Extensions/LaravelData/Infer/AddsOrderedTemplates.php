<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Infer;

use Dedoc\Scramble\Support\Type\TemplateType;

trait AddsOrderedTemplates
{
    /**
     * @param  TemplateType[]  $templateTypes
     * @param  TemplateType[]  $newTemplateTypes
     */
    private function addOrderedTemplateTypes(array &$templateTypes, array $newTemplateTypes): void
    {
        $oldTemplateTypes = $templateTypes;

        $templateTypes = [];

        foreach ($newTemplateTypes as $templateType) {
            if ($existingTemplateType = collect($oldTemplateTypes)->first(fn (TemplateType $t) => $t->name === $templateType->name)) {
                $templateTypes[] = $existingTemplateType;

                continue;
            }

            $templateTypes[] = $templateType;
        }

        foreach ($oldTemplateTypes as $templateType) {
            if (collect($templateTypes)->contains(fn (TemplateType $t) => $t->name === $templateType->name)) {
                continue;
            }

            $templateTypes[] = $templateType;
        }
    }
}
