<?php

namespace GungCahyadiPP\AutoDocs\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateDocsCommand extends Command
{
    protected $signature = 'autodocs:generate
        {--force : Overwrite existing doc files}
        {--path= : Custom output path (default: from config)}
        {--api-path= : Filter routes by API path prefix}';

    protected $description = 'Generate markdown documentation skeleton files for all API endpoints';

    public function handle(Router $router): int
    {
        $docsPath = $this->option('path') ?: config('autodocs.docs_path', base_path('docs/api'));
        $apiPath = $this->option('api-path') ?: config('autodocs.api_path', 'api');
        $force = $this->option('force');

        $this->info('Scanning API routes...');

        $routes = $this->getApiRoutes($router, $apiPath);

        if ($routes->isEmpty()) {
            $this->warn("No API routes found matching path prefix: {$apiPath}");
            $this->line('Make sure your routes are registered and match the api_path config.');

            return self::SUCCESS;
        }

        $this->info("Found {$routes->count()} API endpoint(s).");

        $created = 0;
        $skipped = 0;

        foreach ($routes as $routeData) {
            $filePath = $this->buildFilePath($docsPath, $routeData['controller'], $routeData['method']);

            if (File::exists($filePath) && ! $force) {
                $skipped++;
                continue;
            }

            $this->createDocFile($filePath, $routeData);
            $created++;
        }

        $this->newLine();
        $this->info("✓ Created: {$created} file(s)");

        if ($skipped > 0) {
            $this->comment("  Skipped: {$skipped} file(s) (already exist, use --force to overwrite)");
        }

        $this->newLine();
        $this->info("Docs generated at: {$docsPath}");

        return self::SUCCESS;
    }

    /**
     * Get all API routes with their controller and method info.
     */
    protected function getApiRoutes(Router $router, string $apiPath): \Illuminate\Support\Collection
    {
        return collect($router->getRoutes()->getRoutes())
            ->filter(function (Route $route) use ($apiPath) {
                // Only include routes matching the API path
                if (! Str::startsWith($route->uri(), $apiPath)) {
                    return false;
                }

                // Only class-based routes (controllers)
                if (! is_string($route->getAction('uses'))) {
                    return false;
                }

                // Skip scramble's own routes
                $name = $route->getAction('as');
                if ($name && Str::startsWith($name, 'scramble')) {
                    return false;
                }

                return true;
            })
            ->map(function (Route $route) {
                $uses = $route->getAction('uses');
                [$controller, $method] = explode('@', $uses);

                return [
                    'controller' => $controller,
                    'method' => $method,
                    'http_method' => strtoupper($route->methods()[0]),
                    'uri' => $route->uri(),
                    'name' => $route->getName(),
                    'middleware' => $route->middleware(),
                ];
            })
            ->values();
    }

    /**
     * Build the file path for a doc file based on controller namespace.
     */
    protected function buildFilePath(string $basePath, string $controller, string $method): string
    {
        $relativePath = $this->getRelativeControllerPath($controller);

        return "{$basePath}/{$relativePath}/{$method}.md";
    }

    /**
     * Get relative path from controller class name.
     */
    protected function getRelativeControllerPath(string $className): string
    {
        $normalized = str_replace('\\', '/', $className);

        if (Str::contains($normalized, 'Controllers/')) {
            return Str::afterLast($normalized, 'Controllers/');
        }

        return class_basename($className);
    }

    /**
     * Create the markdown documentation file.
     */
    protected function createDocFile(string $filePath, array $routeData): void
    {
        $directory = dirname($filePath);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $content = $this->generateMarkdownContent($routeData);

        File::put($filePath, $content);

        $relativePath = Str::after($filePath, base_path().'/');
        $this->line("  <info>Created:</info> {$relativePath}");
    }

    /**
     * Generate markdown content for an endpoint.
     */
    protected function generateMarkdownContent(array $routeData): string
    {
        $title = $this->generateTitle($routeData['method'], $routeData['controller']);
        $httpMethod = $routeData['http_method'];
        $uri = $routeData['uri'];
        $isAuthenticated = ! in_array('guest', $routeData['middleware']);

        $content = "# {$title}\n\n";
        $content .= "<!-- {$httpMethod} /{$uri} -->\n\n";
        $content .= "TODO: Add description for this endpoint.\n";

        if ($isAuthenticated) {
            $content .= "\n## Authentication\n\n";
            $content .= "This endpoint requires authentication.\n";
        }

        // Add sections based on HTTP method
        if (in_array($httpMethod, ['POST', 'PUT', 'PATCH'])) {
            $content .= "\n## Request Body\n\n";
            $content .= "TODO: Describe the request body parameters.\n";
        }

        $content .= "\n## Response\n\n";
        $content .= "TODO: Describe the response format.\n";

        return $content;
    }

    /**
     * Generate a human-readable title from method and controller name.
     */
    protected function generateTitle(string $method, string $controller): string
    {
        $resource = class_basename($controller);
        $resource = Str::replaceLast('Controller', '', $resource);

        return match ($method) {
            'index' => "List all " . Str::plural(Str::lower($resource)),
            'show' => "Get " . Str::lower($resource) . " details",
            'store', 'create' => "Create a new " . Str::lower($resource),
            'update' => "Update " . Str::lower($resource),
            'destroy', 'delete' => "Delete " . Str::lower($resource),
            '__invoke' => Str::headline($resource),
            default => Str::headline($method) . " " . Str::lower($resource),
        };
    }
}
