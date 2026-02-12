<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Infer;

use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Extensions\PropertyTypeExtension;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelData\Data\Cast;
use Dedoc\ScramblePro\Extensions\LaravelData\DataTypesFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Resource;
use Spatie\LaravelData\Support\Wrapping\WrapType;

class DataModelCastsExtension implements PropertyTypeExtension
{
    private array $cache = [];

    public function __construct(private DataTypesFactory $dataTypesFactory) {}

    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(Model::class);
    }

    public function getPropertyType(PropertyFetchEvent $event): ?Type
    {
        $modelInfo = $this->cache[$modelName = $event->getInstance()->name] ??= (new ModelInfo($modelName))->handle();

        /** @var ?Model $instance */
        $instance = $modelInfo->get('instance');

        if (! $instance) {
            return null;
        }

        if (! $cast = $instance->getCasts()[$event->name] ?? null) {
            return null;
        }

        $cast = Cast::fromString($cast);

        [$dataCollectionClass, $dataClass] = $cast->getArgument(0) && class_exists($cast->getArgument(0))
            ? [$cast->instance, $cast->getArgument(0)]
            : [null, $cast->instance];

        if (! $this->isLaravelDataCast($dataClass)) {
            return null;
        }

        if (! $dataCollectionClass) {
            return (new ReferenceTypeResolver($event->scope->index))->resolve(new GlobalScope, new NewCallReferenceType($dataClass, []));
        }

        $dataContextType = $this->dataTypesFactory->makeDataContextType();
        // @todo LiteralEnumType?
        $dataContextType->templateTypes[4 /* TWrap */]->templateTypes[0 /* TType */] = new LiteralStringType(WrapType::Disabled->value);

        return new Generic($dataCollectionClass, [
            /* TItems */ new MixedType,
            /* TDataClass */ new LiteralStringType($dataClass),
            /* TDataContext */ $dataContextType,
        ]);
    }

    private function getCastClasses(string $cast)
    {
        $data = explode(':', $cast);

        if (count($data) !== 2) {
            return [null, $cast];
        }

        return $data;
    }

    private function isLaravelDataCast(string $cast)
    {
        return is_a($cast, Data::class, true)
            || is_a($cast, Resource::class, true);
    }
}
