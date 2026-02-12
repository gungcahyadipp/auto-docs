<?php

namespace Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Infer;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Infer\Types\MapUsing;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\Infer\Types\NormalizedArguments;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers\AllowedFilterManager;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers\AllowedIncludeManager;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers\AllowedSortManager;
use Dedoc\ScramblePro\Extensions\LaravelQueryBuilder\TypeManagers\QueryBuilderManager;
use Spatie\QueryBuilder\QueryBuilder;

class QueryBuilderDefinitionExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === QueryBuilder::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        if (app(Infer::class)->index->getClassDefinition(QueryBuilder::class)) {
            return;
        }

        $classDefinition = $event->classDefinition;

        $classDefinition->templateTypes = [];

        $classDefinition->properties['request'] = new ClassPropertyDefinition(
            $classDefinition->templateTypes[] = new TemplateType('TRequest'),
        );
        $classDefinition->properties['subject'] = new ClassPropertyDefinition(
            $classDefinition->templateTypes[] = new TemplateType('TSubject'),
        );
        $classDefinition->properties['allowedFilters'] = new ClassPropertyDefinition(
            $classDefinition->templateTypes[] = new TemplateType('TAllowedFilters'),
        );
        $classDefinition->properties['allowedSorts'] = new ClassPropertyDefinition(
            $classDefinition->templateTypes[] = new TemplateType('TAllowedSorts'),
        );
        $classDefinition->properties['allowedIncludes'] = new ClassPropertyDefinition(
            $classDefinition->templateTypes[] = new TemplateType('TAllowedIncludes'),
        );
        $classDefinition->properties['allowedFields'] = new ClassPropertyDefinition(
            $classDefinition->templateTypes[] = new TemplateType('TAllowedFields'),
        );
        // This one is not real template.
        $classDefinition->properties['defaultSorts'] = new ClassPropertyDefinition(
            $classDefinition->templateTypes[] = new TemplateType('TDefaultSorts'),
        );

        $classDefinition->methods['for'] = $this->getForStaticMethodDefinition();
        $classDefinition->methods['for']->isFullyAnalyzed = true;

        $classDefinition->methods['allowedFilters'] = $this->getAllowedFiltersMethodDefinition();
        $classDefinition->methods['allowedFilters']->isFullyAnalyzed = true;

        $classDefinition->methods['allowedSorts'] = $this->getAllowedSortsMethodDefinition();
        $classDefinition->methods['allowedSorts']->isFullyAnalyzed = true;

        $classDefinition->methods['defaultSorts'] = $this->getDefaultSortsMethodDefinition();
        $classDefinition->methods['defaultSorts']->isFullyAnalyzed = true;

        $classDefinition->methods['defaultSort'] = $this->getDefaultSortsMethodDefinition();
        $classDefinition->methods['defaultSort']->isFullyAnalyzed = true;

        $classDefinition->methods['allowedIncludes'] = $this->getAllowedIncludesMethodDefinition();
        $classDefinition->methods['allowedIncludes']->isFullyAnalyzed = true;

        $classDefinition->methods['allowedFields'] = $this->getAllowedFieldsMethodDefinition();
        $classDefinition->methods['allowedFields']->isFullyAnalyzed = true;

        app(Infer::class)->index->registerClassDefinition($classDefinition);
    }

    private function getForStaticMethodDefinition()
    {
        return new FunctionLikeDefinition(
            type: new FunctionType(
                name: 'for',
                returnType: app(QueryBuilderManager::class)->createType(),
            ),
            definingClassName: QueryBuilder::class,
            isStatic: true,
        );
    }

    private function getAllowedFiltersMethodDefinition()
    {
        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'allowedFilters',
                arguments: ['allowedFilters' => new MixedType],
                returnType: new SelfType(QueryBuilder::class),
            ),
            definingClassName: QueryBuilder::class,
            selfOutType: app(QueryBuilderManager::class)->withPropertiesTypes($this->createBaseSelfOutType(), [
                'allowedFilters' => MapUsing::forNormalizedArguments(AllowedFilterManager::class),
            ]),
        );
    }

    private function getAllowedSortsMethodDefinition()
    {
        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'allowedSorts',
                arguments: ['allowedSorts' => new MixedType],
                returnType: new SelfType(QueryBuilder::class),
            ),
            definingClassName: QueryBuilder::class,
            selfOutType: app(QueryBuilderManager::class)->withPropertiesTypes($this->createBaseSelfOutType(), [
                'allowedSorts' => MapUsing::forNormalizedArguments(AllowedSortManager::class),
            ]),
        );
    }

    private function getDefaultSortsMethodDefinition()
    {
        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'defaultSorts',
                arguments: ['defaultSorts' => new MixedType],
                returnType: new SelfType(QueryBuilder::class),
            ),
            definingClassName: QueryBuilder::class,
            selfOutType: app(QueryBuilderManager::class)->withPropertiesTypes($this->createBaseSelfOutType(), [
                'defaultSorts' => MapUsing::forNormalizedArguments(AllowedSortManager::class, 'createDefaultSort'),
            ]),
        );
    }

    private function getAllowedIncludesMethodDefinition()
    {
        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'allowedIncludes',
                arguments: ['allowedIncludes' => new MixedType],
                returnType: new SelfType(QueryBuilder::class),
            ),
            definingClassName: QueryBuilder::class,
            selfOutType: app(QueryBuilderManager::class)->withPropertiesTypes($this->createBaseSelfOutType(), [
                'allowedIncludes' => MapUsing::forNormalizedArguments(AllowedIncludeManager::class),
            ]),
        );
    }

    private function getAllowedFieldsMethodDefinition()
    {
        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'allowedFields',
                arguments: ['allowedFields' => new MixedType],
                returnType: new SelfType(QueryBuilder::class),
            ),
            definingClassName: QueryBuilder::class,
            selfOutType: app(QueryBuilderManager::class)->withPropertiesTypes($this->createBaseSelfOutType(), [
                'allowedFields' => new Generic(NormalizedArguments::class, [
                    new TemplateType('Arguments'),
                ]),
            ]),
        );
    }

    private function createBaseSelfOutType(): Generic
    {
        $type = app(QueryBuilderManager::class)->createType(
            new TemplatePlaceholderType,
            new TemplatePlaceholderType,
            new TemplatePlaceholderType,
            new TemplatePlaceholderType,
            new TemplatePlaceholderType,
            new TemplatePlaceholderType,
            new TemplatePlaceholderType,
        );

        $type->name = 'self';

        return $type;
    }
}
