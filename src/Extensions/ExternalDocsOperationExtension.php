<?php

namespace GungCahyadiPP\AutoDocs\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Extension that loads endpoint descriptions from external markdown files,
 * separating documentation content from controller code.
 *
 * File structure: {docs_path}/{ControllerName}/{methodName}.md
 *
 * Example:
 *   docs/api/UserController/index.md
 *   docs/api/UserController/store.md
 *   docs/api/OrderController/show.md
 */
class ExternalDocsOperationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! $routeInfo->isClassBased()) {
            return;
        }

        $className = $routeInfo->className();
        $methodName = $routeInfo->methodName();

        if (! $className || ! $methodName) {
            return;
        }

        $docFile = $this->resolveDocFile($className, $methodName);

        if (! $docFile) {
            return;
        }

        $content = file_get_contents($docFile);
        $parsed = $this->parseDocContent($content);

        $strategy = config('autodocs.description_strategy', 'file');

        $this->applyDescription($operation, $parsed, $strategy);
    }

    /**
     * Resolve the documentation file path for a given controller method.
     *
     * Supports multiple controller namespace patterns:
     * - App\Http\Controllers\UserController → UserController/store.md
     * - App\Http\Controllers\Api\V1\UserController → Api/V1/UserController/store.md
     * - App\Http\Controllers\Api\V1\UserController → V1/UserController/store.md
     */
    protected function resolveDocFile(string $className, string $methodName): ?string
    {
        $basePath = config('autodocs.docs_path');

        if (! $basePath || ! is_dir($basePath)) {
            return null;
        }

        $candidates = $this->buildCandidatePaths($className, $methodName, $basePath);

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Build all possible file path candidates for a controller method.
     *
     * Given: App\Http\Controllers\Api\V1\UserController@store
     * Generates (in priority order):
     *   - docs/api/Api/V1/UserController/store.md  (most specific)
     *   - docs/api/V1/UserController/store.md
     *   - docs/api/UserController/store.md         (only if no sibling versions exist)
     *   - docs/api/Api/V1/User/store.md
     *   - docs/api/V1/User/store.md
     *   - docs/api/User/store.md                   (only if no sibling versions exist)
     *   - docs/api/UserController.md               (invokable)
     *
     * When multiple versions exist (V1, V2), the short name fallback is skipped
     * to prevent cross-version contamination.
     */
    protected function buildCandidatePaths(string $className, string $methodName, string $basePath): array
    {
        // Extract the relative namespace path after "Controllers\" (or full namespace if not found)
        $relativePath = $this->getRelativeControllerPath($className);
        $shortClass = class_basename($className);
        $shortClassNoSuffix = Str::replaceLast('Controller', '', $shortClass);

        $candidates = [];
        $hasNamespace = $relativePath !== $shortClass;

        // 1. Full relative namespace path: Api/V1/UserController/store.md
        if ($hasNamespace) {
            $candidates[] = "{$basePath}/{$relativePath}/{$methodName}.md";
        }

        // 2. Progressive namespace stripping (e.g. V1/UserController/store.md)
        $parts = explode('/', $relativePath);
        while (count($parts) > 1) {
            array_shift($parts);
            $candidates[] = "{$basePath}/".implode('/', $parts)."/{$methodName}.md";
        }

        // 3. Short class name only: UserController/store.md
        //    Only add if there's no namespace ambiguity (no sibling version folders)
        if ($hasNamespace) {
            $shortPath = "{$basePath}/{$shortClass}/{$methodName}.md";
            if (! $this->hasSiblingVersionConflict($basePath, $shortClass, $methodName)) {
                $candidates[] = $shortPath;
            }
        } else {
            $candidates[] = "{$basePath}/{$shortClass}/{$methodName}.md";
        }

        // 4. Without "Controller" suffix variants
        if ($hasNamespace) {
            $relativeNoSuffix = Str::replaceLast('Controller', '', $relativePath);
            $candidates[] = "{$basePath}/{$relativeNoSuffix}/{$methodName}.md";
        }
        if (! $hasNamespace || ! $this->hasSiblingVersionConflict($basePath, $shortClassNoSuffix, $methodName)) {
            $candidates[] = "{$basePath}/{$shortClassNoSuffix}/{$methodName}.md";
        }

        // 5. Invokable controller: UserController.md or Api/V1/UserController.md
        if ($methodName === '__invoke' || $methodName === 'invoke') {
            if ($hasNamespace) {
                $candidates[] = "{$basePath}/{$relativePath}.md";
            }
            $candidates[] = "{$basePath}/{$shortClass}.md";
        }

        // Deduplicate while preserving order
        return array_values(array_unique($candidates));
    }

    /**
     * Check if there are multiple versioned folders that contain the same controller name.
     * This prevents V1/UserController from accidentally matching docs/api/UserController/
     * when V2/UserController also exists.
     *
     * Example: if both docs/api/V1/UserController/ and docs/api/V2/UserController/ exist,
     * then docs/api/UserController/ should NOT be used as fallback.
     */
    protected function hasSiblingVersionConflict(string $basePath, string $controllerDir, string $methodName): bool
    {
        $targetFile = "{$basePath}/{$controllerDir}/{$methodName}.md";

        if (! file_exists($targetFile)) {
            return false;
        }

        // Scan basePath for versioned directories containing the same controller
        $versionPattern = '/^[Vv]\d+$/';
        $matchCount = 0;

        if (! is_dir($basePath)) {
            return false;
        }

        foreach (scandir($basePath) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            // Check if this is a version directory (V1, V2, v1, v2, etc.)
            if (preg_match($versionPattern, $entry) && is_dir("{$basePath}/{$entry}/{$controllerDir}")) {
                $matchCount++;
            }

            // Also check nested Api/V1 pattern
            if ($entry === 'Api' && is_dir("{$basePath}/Api")) {
                foreach (scandir("{$basePath}/Api") as $subEntry) {
                    if (preg_match($versionPattern, $subEntry) && is_dir("{$basePath}/Api/{$subEntry}/{$controllerDir}")) {
                        $matchCount++;
                    }
                }
            }
        }

        // If 2+ versioned folders have this controller, there's a conflict
        return $matchCount >= 2;
    }

    /**
     * Get the relative path of a controller class after the "Controllers" namespace segment.
     *
     * Examples:
     *   App\Http\Controllers\Api\V1\UserController → Api/V1/UserController
     *   App\Http\Controllers\UserController → UserController
     *   App\Modules\Auth\Controllers\LoginController → LoginController
     */
    protected function getRelativeControllerPath(string $className): string
    {
        // Try to find "Controllers\" in the namespace and take everything after it
        $normalized = str_replace('\\', '/', $className);

        if (Str::contains($normalized, 'Controllers/')) {
            $afterControllers = Str::afterLast($normalized, 'Controllers/');

            return $afterControllers;
        }

        // Fallback: just use the class basename
        return class_basename($className);
    }

    /**
     * Parse markdown content into summary and description.
     *
     * Format:
     *   First line (or # heading) = summary (title)
     *   Everything after first blank line = description
     */
    protected function parseDocContent(string $content): array
    {
        $content = trim($content);

        if (empty($content)) {
            return ['summary' => '', 'description' => ''];
        }

        // Split on first blank line
        $parts = preg_split('/\n\s*\n/', $content, 2);

        $summary = trim($parts[0] ?? '');
        $description = trim($parts[1] ?? '');

        // Strip markdown heading prefix from summary
        if (Str::startsWith($summary, '# ')) {
            $summary = Str::after($summary, '# ');
        }

        return [
            'summary' => $summary,
            'description' => $description,
        ];
    }

    /**
     * Apply parsed documentation to the operation based on strategy.
     */
    protected function applyDescription(Operation $operation, array $parsed, string $strategy): void
    {
        switch ($strategy) {
            case 'file':
                // File content overrides any existing PHPDoc description
                if ($parsed['summary']) {
                    $operation->summary($parsed['summary']);
                }
                if ($parsed['description']) {
                    $operation->description($parsed['description']);
                }
                break;

            case 'merge':
                // Append file content after existing PHPDoc description
                if ($parsed['summary'] && ! $operation->summary) {
                    $operation->summary($parsed['summary']);
                }
                $existing = $operation->description ?? '';
                if ($parsed['description']) {
                    $merged = $existing
                        ? $existing."\n\n".$parsed['description']
                        : $parsed['description'];
                    $operation->description($merged);
                }
                break;

            case 'fallback':
                // Only use file content when PHPDoc description is empty
                if (! $operation->summary && $parsed['summary']) {
                    $operation->summary($parsed['summary']);
                }
                if (empty($operation->description) && $parsed['description']) {
                    $operation->description($parsed['description']);
                }
                break;
        }
    }
}
