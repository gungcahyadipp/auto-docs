<?php

namespace Dedoc\ScramblePro\Extensions\LaravelActions\Generator;

use Dedoc\Scramble\Support\RouteInfo;
use Lorisleiva\Actions\Concerns\AsAction;

trait ChecksIsLaravelActions
{
    private function isLaravelActionController(RouteInfo $routeInfo): bool
    {
        if (! $action = $routeInfo->className()) {
            return false;
        }

        return in_array(AsAction::class, array_values(class_uses_recursive($action)));
    }
}
