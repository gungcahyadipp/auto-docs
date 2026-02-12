<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\OpenApiDocumentTransformers;

use Dedoc\Scramble\Attributes\SchemaName;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\ScramblePro\Extensions\LaravelData\DataTransformConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use WeakMap;

class LaravelDataContextualNamesTransformer implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $laravelDataSchemas = collect($context->references->schemas->items)
            ->flatten()
            ->filter(fn (Reference $ref) => $ref->getAttribute('laravelDataType')->name ?? null) // @phpstan-ignore property.nonObject
            ->groupBy(fn (Reference $ref): string => $ref->getAttribute('laravelDataType')->name); // @phpstan-ignore argument.type, property.nonObject

        foreach ($laravelDataSchemas as $laravelDataClass => $laravelDataSchemaReferences) {
            /**
             * @var Collection<int, Reference> $outputReferences
             * @var Collection<int, Reference> $inputReferences
             */
            [$outputReferences, $inputReferences] = $laravelDataSchemaReferences->partition(fn (Reference $ref) => $ref->getAttribute('laravelDataContext') === DataTransformConfig::OUTPUT);

            if ($inputReferences->isEmpty()) {
                continue;
            }

            /** @var Reference|null $output */
            $output = $outputReferences->values()->get(0);
            /** @var Reference $input */
            $input = $inputReferences->values()->get(0);

            /** @var non-empty-array<Reference> $inputReferencesItems */
            $inputReferencesItems = $inputReferences->values()->all();

            if (! $input->getAttribute('laravelDataDifferentInputAndOutput')) {
                // Input and output context of the object is the same hence everything is kept as is.
                continue;
            }

            // At this point we know that there is no usages of the data object as output and we can simply
            // remove the `Request` suffix.
            if (! $output) {
                $inputReference = $inputReferences->first();
                $this->renameReferencesOfDocument(
                    $document,
                    $inputReferencesItems,
                    Str::replaceEnd('Request', '', $inputReference->fullName),
                    $inputReference->shortName ? Str::replaceEnd('Request', '', $inputReference->shortName) : null,
                );

                continue;
            }

            // At this point we can assign explicit input name if schema name is used.
            $class = $output->getAttribute('laravelDataType')?->name ?? null; // @phpstan-ignore property.nonObject
            if ($explicitInputName = $this->getExplicitInputName($class)) {
                $this->renameReferencesOfDocument(
                    $document,
                    $inputReferencesItems,
                    $explicitInputName,
                    $explicitInputName,
                );
            }
        }
    }

    /**
     * @param  non-empty-array<Reference>  $referencesToRename
     */
    private function renameReferencesOfDocument(OpenApi $openApi, array $referencesToRename, string $newFullName, ?string $newShortName): void
    {
        static $cache = new WeakMap;
        if (! isset($cache[$openApi])) {
            /** @var array<array-key, mixed> $objects */
            $objects = data_get($openApi, 'paths.*.operations.*.requestBodyObject');

            $cache[$openApi] = [
                'requestBodyObjects' => collect($objects)
                    ->filter(fn ($object) => $object instanceof RequestBodyObject)
                    ->values(),
            ];
        }

        /** @var Collection<int, RequestBodyObject> $requestBodyObjects */
        $requestBodyObjects = $cache[$openApi]['requestBodyObjects'];

        $oldSchemaKey = $referencesToRename[0]->getUniqueName();

        foreach ($referencesToRename as $reference) {
            $reference->fullName = $newFullName;
            $reference->shortName = $newShortName;
        }

        $newSchemaKey = $newShortName ?: $newFullName;

        if (isset($openApi->components->schemas[$oldSchemaKey])) {
            $schema = $openApi->components->schemas[$oldSchemaKey];
            unset($openApi->components->schemas[$oldSchemaKey]);
            $openApi->components->schemas[$newSchemaKey] = $schema;
        }

        foreach ($requestBodyObjects as $requestBodyObject) {
            $requestBodyObject->description(
                Str::replace("`{$oldSchemaKey}`", "`{$newSchemaKey}`", $requestBodyObject->description)
            );
        }
    }

    private function getExplicitInputName(?string $class): ?string
    {
        if (! $class) {
            return null;
        }

        try {
            $reflectionClass = new ReflectionClass($class); // @phpstan-ignore argument.type
        } catch (ReflectionException) {
            return null;
        }

        /** @var SchemaName $schemaName */
        $attrs = $reflectionClass->getAttributes(SchemaName::class);
        $schemaName = $attrs ? $attrs[0]->newInstance() : null;
        if (! $schemaName) {
            return null;
        }

        return $schemaName->input;
    }
}
