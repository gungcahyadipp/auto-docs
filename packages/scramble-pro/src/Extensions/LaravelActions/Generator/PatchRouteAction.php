<?php

namespace Dedoc\ScramblePro\Extensions\LaravelActions\Generator;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class PatchRouteAction extends OperationExtension
{
    use ChecksIsLaravelActions;

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! $actionClass = $this->getLaravelActionController($routeInfo)) {
            return;
        }

        $this->updateRouteAction($actionClass, $routeInfo);

        $this->updateRouteInfoPhpDoc($actionClass, $routeInfo);

        $this->attachExceptions($actionClass, $routeInfo);
    }

    private function getLaravelActionController(RouteInfo $routeInfo): ?string
    {
        return $this->isLaravelActionController($routeInfo) ? $routeInfo->className() : null;
    }

    private function updateRouteAction(string $actionClass, RouteInfo $routeInfo): void
    {
        $currentMethod = Str::afterLast($routeInfo->route->action['uses'], '@');
        $newMethod = $this->getDefaultRouteMethod($actionClass);

        if ($currentMethod !== '__invoke' || $currentMethod === $newMethod) {
            return;
        }

        $routeInfo->route->action['uses'] = (string) Str::of($routeInfo->route->action['uses'])
            ->beforeLast('@')
            ->append('@'.$newMethod);
    }

    /**
     * For cases when an action class has both `asController` and `handle` methods, but only `handle` has some PHPDoc,
     * we'd like to use whatever `handle` has for documenting action endpoints.
     */
    private function updateRouteInfoPhpDoc(string $actionClass, RouteInfo $routeInfo): void
    {
        if ($this->getDefaultRouteMethod($actionClass) !== 'asController') {
            return;
        }

        $actionClassReflection = new \ReflectionClass($actionClass); // @phpstan-ignore argument.type

        $asControllerPhpDoc = method_exists($actionClass, 'asController') ? $actionClassReflection->getMethod('asController')->getDocComment() : null;
        if ($asControllerPhpDoc) {
            return;
        }

        $handlePhpDoc = method_exists($actionClass, 'handle') ? $actionClassReflection->getMethod('handle')->getDocComment() : null;
        if (! $handlePhpDoc) {
            return;
        }

        $handlePhpDocNode = PhpDoc::parse($handlePhpDoc, $routeInfo->getScope()->nameResolver);

        $routeInfo->setPhpDoc($handlePhpDocNode);
    }

    private function getDefaultRouteMethod(string $actionClass): string
    {
        if (method_exists($actionClass, 'asController')) {
            return 'asController';
        }

        return method_exists($actionClass, 'handle') ? 'handle' : '__invoke';
    }

    private function attachExceptions(string $actionClass, RouteInfo $routeInfo): void
    {
        if (! $actionMethod = $routeInfo->getActionType()) {
            return;
        }

        $actionDefinition = $this->infer->analyzeClass($actionClass);

        $this->attachAuthorizeException($actionDefinition, $actionMethod);
    }

    private function attachAuthorizeException(ClassDefinition $actionDefinition, FunctionType $actionMethod): void
    {
        if (! $actionDefinition->hasMethodDefinition('authorize')) {
            return;
        }

        $authorizeReturnType = $actionDefinition->getMethodCallType('authorize');
        if ($authorizeReturnType instanceof LiteralBooleanType && $authorizeReturnType->value === true) {
            return;
        }

        $actionMethod->exceptions = [
            ...$actionMethod->exceptions,
            new ObjectType(AuthorizationException::class),
        ];
    }
}
