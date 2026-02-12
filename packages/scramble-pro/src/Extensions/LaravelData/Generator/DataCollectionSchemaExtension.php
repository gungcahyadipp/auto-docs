<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Generator;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelData\DataTypesFactory;
use Spatie\LaravelData\Contracts\BaseDataCollectable;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Spatie\LaravelData\Support\Transformation\DataContext;
use Spatie\LaravelData\Support\Wrapping\Wrap;
use Spatie\LaravelData\Support\Wrapping\WrapType;

class DataCollectionSchemaExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        if (! $type instanceof Generic) {
            return false;
        }

        return $type->isInstanceOf(BaseDataCollectable::class);
    }

    /**
     * @param  Generic  $type
     */
    public function toSchema(Type $type)
    {
        if (! $dataClass = $this->getDataClass($type)) {
            return null;
        }

        $wrapKey = $this->getWrapKey($dataClass, $type->templateTypes[2 /* TDataContext */]?->templateTypes[4 /* TWrap */] ?? null); // @phpstan-ignore property.notFound

        $itemDataContext = $this->prepareContextForItem($type->templateTypes[2 /* TDataContext */] ?? null);

        $itemsType = new ArrayType(
            value: $itemDataContext
                ? new Generic($dataClass, [$itemDataContext])
                : new ObjectType($dataClass)
        );

        if ($type->isInstanceOf(DataCollection::class)) {
            return $this->openApiTransformer->transform(
                $wrapKey
                    ? new KeyedArrayType([new ArrayItemType_(key: $wrapKey, value: $itemsType)])
                    : $itemsType
            );
        }

        if ($type->isInstanceOf(PaginatedDataCollection::class)) {
            $itemsOpenApiType = $this->openApiTransformer->transform($itemsType);

            $object = new OpenApiType\ObjectType;
            $object->addProperty($wrapKey ?? 'data', $itemsOpenApiType->setDescription('The list of items'));
            $object->addProperty(
                'links',
                (new OpenApiType\ArrayType)
                    ->setItems(
                        (new OpenApiType\ObjectType)
                            ->addProperty('url', (new OpenApiType\StringType)->nullable(true))
                            ->addProperty('label', new OpenApiType\StringType)
                            ->addProperty('active', new OpenApiType\BooleanType)
                            ->setRequired(['url', 'label', 'active'])
                    )
                    ->setDescription('Generated paginator links.')
            );
            $object->addProperty(
                'meta',
                (new OpenApiObjectType)
                    ->addProperty('current_page', new OpenApiType\IntegerType)
                    ->addProperty('first_page_url', new OpenApiType\StringType)
                    ->addProperty('from', (new OpenApiType\IntegerType)->nullable(true))
                    ->addProperty('last_page', new OpenApiType\IntegerType)
                    ->addProperty('last_page_url', new OpenApiType\StringType)
                    ->addProperty('next_page_url', (new OpenApiType\StringType)->nullable(true))
                    ->addProperty('path', (new OpenApiType\StringType)->nullable(true)->setDescription('Base path for paginator generated URLs.'))
                    ->addProperty('per_page', (new OpenApiType\IntegerType)->setDescription('Number of items shown per page.'))
                    ->addProperty('prev_page_url', (new OpenApiType\StringType)->nullable(true))
                    ->addProperty('to', (new OpenApiType\IntegerType)->nullable(true)->setDescription('Number of the last item in the slice.'))
                    ->addProperty('total', (new OpenApiType\IntegerType)->setDescription('Total number of items being paginated.'))
                    ->setRequired(['current_page', 'first_page_url', 'from', 'last_page', 'last_page_url', 'next_page_url', 'path', 'per_page', 'prev_page_url', 'to', 'total'])
            );
            $object->setRequired([$wrapKey ?? 'data', 'links', 'meta']);

            return $object;
        }

        // Cursor paginated resource
        $itemsOpenApiType = $this->openApiTransformer->transform($itemsType);

        $object = new OpenApiType\ObjectType;
        $object->addProperty($wrapKey ?? 'data', $itemsOpenApiType->setDescription('The list of items'));
        $object->addProperty('links', (new OpenApiType\ArrayType));
        $object->addProperty(
            'meta',
            (new OpenApiObjectType)
                ->addProperty('path', (new OpenApiType\StringType)->nullable(true)->setDescription('Base path for paginator generated URLs.'))
                ->addProperty('per_page', (new OpenApiType\IntegerType)->setDescription('Number of items shown per page.'))
                ->addProperty('next_cursor', (new OpenApiType\StringType)->nullable(true))
                ->addProperty('next_cursor_url', (new OpenApiType\StringType)->nullable(true))
                ->addProperty('prev_cursor', (new OpenApiType\StringType)->nullable(true))
                ->addProperty('prev_cursor_url', (new OpenApiType\StringType)->nullable(true))
                ->setRequired(['path', 'per_page', 'next_cursor', 'next_cursor_url', 'prev_cursor', 'prev_cursor_url'])
        );
        $object->setRequired([$wrapKey ?? 'data', 'links', 'meta']);

        return $object;
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type)
    {
        if (! $dataClass = $this->getDataClass($type)) {
            return null;
        }

        $openApiType = $this->openApiTransformer->transform($type);

        $description = match (true) {
            $type->isInstanceOf(PaginatedDataCollection::class) => 'The paginated collection of ',
            $type->isInstanceOf(CursorPaginatedDataCollection::class) => 'The cursor paginated collection of ',
            default => 'The collection of ',
        };

        return Response::make(200)
            ->description($description.'`'.$this->components->uniqueSchemaName($this->getDocReflection($dataClass)->getSchemaName(default: $dataClass)).'`')
            ->setContent(
                'application/json',
                Schema::fromType($openApiType),
            );
    }

    public function getDocReflection(string $className)
    {
        return SchemaClassDocReflector::createFromClassName($className);
    }

    private function getDataClass(Generic $type): ?string
    {
        $dataType = $type->templateTypes[1 /* TValue */] ?? null;

        if (! $dataType instanceof ObjectType) {
            return null;
        }

        return $dataType->name;
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

        $globalWrapKey = config('data.wrap');

        $wrapKeyType = $wrapType->templateTypes[1 /* TKey */] ?? null;

        if (! $wrapKeyType instanceof LiteralStringType) {
            return $globalWrapKey; // @todo default wrap handle
        }

        return $wrapKeyType->value;
    }

    private function prepareContextForItem($type): ?Generic
    {
        if (! $type instanceof Generic) {
            return null;
        }

        if (! $type->isInstanceOf(DataContext::class)) {
            return null;
        }

        if (count($type->templateTypes) !== 5) {
            return null;
        }

        return new Generic(DataContext::class, $type->templateTypes + [
            4 => app(DataTypesFactory::class)->makeDataContextType()->templateTypes[4],
        ]);
    }
}
