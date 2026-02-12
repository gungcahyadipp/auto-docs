<?php

namespace Dedoc\ScramblePro\Extensions\LaravelData\Infer;

use Dedoc\Scramble\Infer\AutoResolvingArgumentTypeBag;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\ScramblePro\Extensions\LaravelData\DataTypesFactory;
use Illuminate\Contracts\Pagination\CursorPaginator as CursorPaginatorContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Resource;

class DataStaticCreationMethodsExtension implements StaticMethodReturnTypeExtension
{
    public function __construct(private DataTypesFactory $dataTypesFactory) {}

    public function shouldHandle(string $name): bool
    {
        return is_a($name, Data::class, true)
            || is_a($name, Resource::class, true);
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'from' => $this->handleFromCall($event),
            'collect' => $this->handleCollectCall($event),
            default => null,
        };
    }

    private function handleFromCall(StaticMethodCallEvent $event): Type
    {
        if (
            $event->arguments->get('payloads', 0)?->isInstanceOf(Model::class)
                && $event->getDefinition()->hasMethodDefinition('fromModel')
        ) {
            return (new ReferenceTypeResolver($event->scope->index))->resolve(
                $event->scope,
                new StaticMethodCallReferenceType($event->callee, 'fromModel', $event->arguments instanceof AutoResolvingArgumentTypeBag ? $event->arguments->allUnresolved() : $event->arguments->all())
            );
        }

        return (new ReferenceTypeResolver($event->scope->index))->resolve($event->scope, new NewCallReferenceType($event->callee, []));
    }

    private function handleCollectCall(StaticMethodCallEvent $event)
    {
        if ($inferredDataCollectionType = $this->getDataCollectionTypeFromItems($event)) {
            return $inferredDataCollectionType;
        }

        /*
         * All Laravel Data collection classes are represented by Scramble as following, when analyzed:
         *
         * DataCollection<TKey, TValue, TDataContext = DataContext<array{}, array{}, array{}, array{}, null>>
         * PaginatedDataCollection<TKey, TValue, TDataContext = DataContext<array{}, array{}, array{}, array{}, null>>
         * CursorPaginatedDataCollection<TKey, TValue, TDataContext = DataContext<array{}, array{}, array{}, array{}, null>>
         *
         * DataContext is represented like: DataContext<TIncludePartials, TExcludePartials, TOnlyPartials, TExceptPartials, TWrap>
         *
         * So any call on collected data can be represented fully.
         */
        if (! $dataCollectionClass = $this->getDataCollectionClass($event)) {
            return null;
        }

        return new Generic($dataCollectionClass, [
            /* TKey */ new IntegerType,
            /* TValue */ new ObjectType($event->callee),
            /* TDataContext */ $this->dataTypesFactory->makeDataContextType(),
        ]);
    }

    private function getDataCollectionTypeFromItems(StaticMethodCallEvent $event): ?Generic
    {
        if (count($event->arguments) !== 1) {
            return null;
        }

        if (! $items = $event->arguments->get('items', 0)) {
            return null;
        }

        $inferredClass = $items instanceof ObjectType ? $items->name : null;

        $paginatorClass = $inferredClass && $this->isPaginatorClass($inferredClass)
            ? $inferredClass
            : $items->getAttribute('jsonApiPaginator');

        if (! $paginatorClass) {
            return null;
        }

        return (new Generic($paginatorClass, [new IntegerType, new ObjectType($event->callee)]))->mergeAttributes($items->attributes());
    }

    private function getDataCollectionClass(StaticMethodCallEvent $event): ?string
    {
        $intoArgument = $event->getArg('into', 1, new GenericClassStringType(new ObjectType(DataCollection::class)));

        if (! $intoArgument instanceof LiteralString) {
            return null;
        }

        return $intoArgument->getValue();
    }

    private function isPaginatorClass(string $paginatorClass): bool
    {
        return is_a($paginatorClass, CursorPaginatorContract::class, true)
            || is_a($paginatorClass, PaginatorContract::class, true)
            || is_a($paginatorClass, LengthAwarePaginatorContract::class, true);
    }
}
