<?php

namespace Dedoc\ScramblePro\Extensions\LaravelJsonApiPaginate\Infer;

use Dedoc\Scramble\Infer\AutoResolvingArgumentTypeBag;
use Dedoc\Scramble\Infer\Extensions\AnyMethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\Event\AnyMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use InvalidArgumentException;
use Spatie\QueryBuilder\QueryBuilder;

class PaginateMethodExtension implements AnyMethodReturnTypeExtension, StaticMethodReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $this->isQueryLikeClass($name) || is_a($name, Model::class, true);
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        if (! config('json-api-paginate.method_name')) {
            return null;
        }

        if ($event->name !== config('json-api-paginate.method_name')) {
            return null;
        }

        return $this->markTypeAsJsonApiPaginated(match ($this->getPaginationMethod()) {
            'cursorPaginate', 'simpleFastPaginate', 'simplePaginate', 'fastPaginate', 'paginate' => ReferenceTypeResolver::getInstance()->resolve(
                $event->scope,
                new StaticMethodCallReferenceType(
                    $event->name,
                    $this->getPaginationMethod(),
                    $event->arguments instanceof AutoResolvingArgumentTypeBag ? $event->arguments->allUnresolved() : $event->arguments->all(),
                ),
            ),
            default => throw new InvalidArgumentException('Unknown pagination method '.$this->getPaginationMethod()),
        }, $event, $this->getPaginationMethod());
    }

    public function getMethodReturnType(AnyMethodCallEvent $event): ?Type
    {
        if (! config('json-api-paginate.method_name')) {
            return null;
        }

        if ($event->name !== config('json-api-paginate.method_name')) {
            return null;
        }

        /*
         * Due to Scramble currently does not infer 100% of types (especially the ones coming from a vendor),
         * the simplest approach to prevent false positive match of paginate method call is to treat it as
         * valid in case it is called on `Query`-like objects or on the `unknown` type. This way in case
         * there is a method named similarly to 'json-api-paginate.method_name' in the app codebase, it
         * won't be false-positively matched.
         */

        $shouldBeHandled = $event->getInstance() instanceof UnknownType || $this->isQueryLike($event->getInstance());
        if (! $shouldBeHandled) {
            return null;
        }

        return $this->markTypeAsJsonApiPaginated(match ($this->getPaginationMethod()) {
            'cursorPaginate', 'simpleFastPaginate', 'simplePaginate', 'fastPaginate', 'paginate' => ReferenceTypeResolver::getInstance()->resolve(
                $event->scope,
                new MethodCallReferenceType(
                    $event->getInstance(),
                    $this->getPaginationMethod(),
                    $event->arguments instanceof AutoResolvingArgumentTypeBag ? $event->arguments->allUnresolved() : $event->arguments->all(),
                ),
            ),
            default => throw new InvalidArgumentException('Unknown pagination method '.$this->getPaginationMethod()),
        }, $event, $this->getPaginationMethod());
    }

    private function getPaginationMethod(): string
    {
        return config('json-api-paginate.use_cursor_pagination')
            ? 'cursorPaginate'
            : (
                config('json-api-paginate.use_simple_pagination')
                    ? (config('json-api-paginate.use_fast_pagination') ? 'simpleFastPaginate' : 'simplePaginate')
                    : (config('json-api-paginate.use_fast_pagination') ? 'fastPaginate' : 'paginate')
            );
    }

    private function isQueryLike(Type $instance): bool
    {
        if (! $instance instanceof ObjectType) {
            return false;
        }

        return $this->isQueryLikeClass($instance->name);
    }

    private function isQueryLikeClass(string $class): bool
    {
        return collect([
            EloquentBuilder::class,
            BaseBuilder::class,
            BelongsToMany::class,
            HasManyThrough::class,
            QueryBuilder::class,
        ])->some(fn (string $queryClass) => is_a($class, $queryClass, true));
    }

    private function markTypeAsJsonApiPaginated(Type $type, StaticMethodCallEvent|AnyMethodCallEvent $event, string $paginationMethod): Type
    {
        $type->setAttribute('jsonApiPaginator', match ($paginationMethod) {
            'cursorPaginate' => \Illuminate\Contracts\Pagination\CursorPaginator::class,
            'simpleFastPaginate', 'simplePaginate' => \Illuminate\Contracts\Pagination\Paginator::class,
            'fastPaginate', 'paginate' => \Illuminate\Contracts\Pagination\LengthAwarePaginator::class,
            default => throw new InvalidArgumentException('Unknown pagination method '.$this->getPaginationMethod()),
        });

        $perPage = $event->getArg('defaultSize', 1)->value ?? config('json-api-paginate.default_size');

        $type->setAttribute('jsonApiPageSize', $perPage);

        return $type;
    }
}
