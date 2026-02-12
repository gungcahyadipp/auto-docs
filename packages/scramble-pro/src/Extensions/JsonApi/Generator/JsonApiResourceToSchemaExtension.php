<?php

namespace Dedoc\ScramblePro\Extensions\JsonApi\Generator;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Types as OpenApiType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;
use Dedoc\Scramble\Support\Type as InferType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\FlattensMergeValues;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\ScramblePro\Extensions\JsonApi\Utils\JsonApiResourceReflection;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use TiMacDonald\JsonApi\JsonApiResource;
use TiMacDonald\JsonApi\JsonApiResourceCollection;
use TiMacDonald\JsonApi\Link;

class JsonApiResourceToSchemaExtension extends TypeToSchemaExtension
{
    use FlattensMergeValues;
    use ResourceTypeAware;

    public function __construct(
        Infer $infer,
        TypeTransformer $openApiTransformer,
        Components $components,
        protected OpenApiContext $openApiContext
    ) {
        parent::__construct($infer, $openApiTransformer, $components);
    }

    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(JsonApiResource::class);
    }

    /**
     * @param  InferType\ObjectType  $type
     */
    public function toSchema(Type $type): ?OpenApiType\Type
    {
        $reflection = JsonApiResourceReflection::createForClass($type->name);

        $schema = (new OpenApiType\ObjectType)
            ->addProperty('id', new OpenApiType\StringType)
            ->addProperty('type', $this->getTypeOfResourceType($type))
            /* @todo meta */
            ->setRequired(['id', 'type']);

        if ($attributesType = $reflection->getAttributesType()) {
            $schema->addProperty('attributes', $this->openApiTransformer->transform($attributesType));
        }

        if (
            ($relationshipsType = $this->getRelationshipsType($reflection))
            && count($relationshipsType->items)
        ) {
            $schema->addProperty('relationships', $this->openApiTransformer->transform($relationshipsType));
        }

        if ($linksType = $reflection->getLinksType()) {
            $schema
                ->addProperty('links', $this->transformLinks($linksType))
                ->addRequired(['links']);
        }

        return $schema;

    }

    /**
     * @param  InferType\ObjectType  $type
     */
    public function toResponse(Type $type): ?Response
    {
        $jsonResourceExtension = new JsonResourceTypeToSchema(
            $this->infer,
            $this->openApiTransformer,
            $this->components,
            $this->openApiContext,
        );

        if (! $response = $jsonResourceExtension->toResponse($type)) {
            return null;
        }

        if (! $firstMediaType = array_keys($response->content)[0] ?? null) {
            return $response;
        }

        $mediaType = $response->getContent($firstMediaType);

        unset($response->content[$firstMediaType]);
        $response->content['application/vnd.api+json'] = $mediaType;

        return $response;
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference('schemas', $type->name, $this->components);
    }

    private function getRelationshipsType(JsonApiResourceReflection $reflection): ?InferType\KeyedArrayType
    {
        if (! $relationshipsType = $reflection->getRelationshipsType()) {
            return null;
        }

        return (new InferType\TypeWalker) // @phpstan-ignore return.type
            ->map(
                $relationshipsType,
                function (Type $type) {
                    if ($type->isInstanceOf(JsonApiResource::class)) {
                        return InferType\Union::wrap([
                            new InferType\Generic(ResourceIdentifierToSchemaExtension::SYNTHETIC_IDENTIFIER_CLASS, [$type]),
                            new InferType\NullType,
                        ]);
                    }

                    if ($type instanceof ObjectType && $type->isInstanceOf(JsonApiResourceCollection::class)) {
                        $collectedType = ResourceCollectionTypeManager::make($type)->getCollectedType();

                        return new InferType\ArrayType(
                            new InferType\Generic(ResourceIdentifierToSchemaExtension::SYNTHETIC_IDENTIFIER_CLASS, [$collectedType])
                        );
                    }

                    return $type;
                },
                fn (Type $t) => $t instanceof InferType\Generic && $t->name === ResourceIdentifierToSchemaExtension::SYNTHETIC_IDENTIFIER_CLASS ? [] : $t->nodes(),
            );
    }

    private function transformLinks(InferType\KeyedArrayType $linksType): OpenApiType\ObjectType
    {
        $linksObject = new OpenApiType\ObjectType;

        foreach ($linksType->items as $arrayItemType) {
            $linkType = $arrayItemType->value;

            if (
                ! $linkType instanceof InferType\Generic
                || ! $linkType->isInstanceOf(Link::class)
                || count($linkType->templateTypes) < 3
            ) {
                continue;
            }

            $keyType = $linkType->templateTypes[0/* TKey */];

            if (! $keyType instanceof InferType\Literal\LiteralStringType) {
                continue;
            }

            $linksObject
                ->addProperty($keyType->value, $propertyType = $this->openApiTransformer->transform($linkType))
                ->addRequired([$keyType->value]);

            $linksObject->properties[$keyType->value] = $this->makeCompoundLink($propertyType, $arrayItemType->getAttribute('docNode')); // @phpstan-ignore argument.type
        }

        return $linksObject;
    }

    private function makeCompoundLink(OpenApiType\Type $propertyType, ?PhpDocNode $phpDocNode): OpenApiType\Type
    {
        $description = $phpDocNode ? $this->makeDescriptionFromComments($phpDocNode) : '';
        $example = ExamplesExtractor::make($phpDocNode)->extract()[0] ?? new MissingValue;
        $default = ExamplesExtractor::make($phpDocNode, '@default')->extract()[0] ?? new MissingValue;

        if (! $description && $example instanceof MissingValue && $default instanceof MissingValue) {
            return $propertyType;
        }

        return $propertyType
            ->default($default)
            ->example($example)
            ->setDescription($description);
    }

    private function makeDescriptionFromComments(PhpDocNode $phpDocNode): string
    {
        return trim($phpDocNode->getAttribute('summary').' '.$phpDocNode->getAttribute('description')); // @phpstan-ignore-line
    }
}
