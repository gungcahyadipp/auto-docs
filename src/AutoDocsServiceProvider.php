<?php

namespace GungCahyadiPP\AutoDocs;

use Illuminate\Support\ServiceProvider;

class AutoDocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/autodocs.php', 'autodocs'
        );

        // Bridge autodocs config → scramble config immediately so Scramble
        // can read it during its own boot phase.
        $this->bridgeConfigToScramble();

        // Register external docs extension BEFORE Scramble boots,
        // so it's included when Scramble reads Scramble::$extensions
        $this->registerExternalDocsExtension();

        $this->app->register(\Dedoc\Scramble\ScrambleServiceProvider::class);
        $this->app->register(\Dedoc\ScramblePro\ScrambleProServiceProvider::class);
    }

    /**
     * Map autodocs config to scramble config key so Scramble internals
     * read configuration from our unified config file.
     */
    protected function bridgeConfigToScramble(): void
    {
        $autodocs = $this->app['config']->get('autodocs', []);

        // Resolve description: use config value, or load from markdown file
        $description = $autodocs['info']['description'] ?? null;
        if (empty($description)) {
            $description = $this->loadDescriptionFromFile($autodocs['description_file'] ?? null);
        }

        $info = $autodocs['info'] ?? [];
        $info['description'] = $description ?: '';

        $scrambleConfig = [
            'api_path' => $autodocs['api_path'] ?? 'api',
            'api_domain' => $autodocs['api_domain'] ?? null,
            'export_path' => $autodocs['export_path'] ?? 'api.json',
            'info' => $info,
            'ui' => $autodocs['ui'] ?? [],
            'servers' => $autodocs['servers'] ?? null,
            'enum_cases_description_strategy' => $autodocs['enum_cases_description_strategy'] ?? 'description',
            'enum_cases_names_strategy' => $autodocs['enum_cases_names_strategy'] ?? false,
            'flatten_deep_query_parameters' => $autodocs['flatten_deep_query_parameters'] ?? true,
            'middleware' => $autodocs['middleware'] ?? ['web'],
            'extensions' => $autodocs['extensions'] ?? [],
        ];

        $this->app['config']->set('scramble', $scrambleConfig);
    }

    /**
     * Load API description content from a markdown file.
     */
    protected function loadDescriptionFromFile(?string $filePath): string
    {
        if (! $filePath || ! file_exists($filePath)) {
            // Fallback to the bundled default description
            $bundledPath = __DIR__.'/../resources/docs/description.md';

            if (file_exists($bundledPath)) {
                return file_get_contents($bundledPath);
            }

            return '';
        }

        return file_get_contents($filePath);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(
            __DIR__.'/../packages/scramble/resources/views', 'scramble'
        );

        $this->registerSecurityScheme();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \GungCahyadiPP\AutoDocs\Console\GenerateDocsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/autodocs.php' => config_path('autodocs.php'),
            ], 'autodocs-config');

            $this->publishes([
                __DIR__.'/../resources/docs/description.md' => base_path('autodocs/description.md'),
            ], 'autodocs-description');

            // Publish all at once
            $this->publishes([
                __DIR__.'/../config/autodocs.php' => config_path('autodocs.php'),
                __DIR__.'/../resources/docs/description.md' => base_path('autodocs/description.md'),
            ], 'autodocs');
        }
    }

    /**
     * Register the ExternalDocsOperationExtension to load descriptions
     * from separate markdown files.
     *
     * Always registered — the extension itself gracefully handles
     * missing folders by returning early when no doc file is found.
     */
    protected function registerExternalDocsExtension(): void
    {
        \Dedoc\Scramble\Scramble::registerExtension(
            \GungCahyadiPP\AutoDocs\Extensions\ExternalDocsOperationExtension::class
        );
    }

    /**
     * Register the global security scheme based on autodocs config.
     */
    protected function registerSecurityScheme(): void
    {
        $security = $this->app['config']->get('autodocs.security');

        if (empty($security)) {
            return;
        }

        \Dedoc\Scramble\Scramble::configure()->withDocumentTransformers(
            function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) use ($security) {
                $scheme = $this->buildSecurityScheme($security);

                if ($scheme) {
                    $openApi->secure($scheme);
                }
            }
        );
    }

    /**
     * Build a SecurityScheme instance from config array.
     */
    protected function buildSecurityScheme(array $config): ?\Dedoc\Scramble\Support\Generator\SecurityScheme
    {
        $type = $config['type'] ?? null;

        return match ($type) {
            'bearer' => \Dedoc\Scramble\Support\Generator\SecurityScheme::http(
                'bearer',
                $config['format'] ?? null
            ),
            'apiKey' => \Dedoc\Scramble\Support\Generator\SecurityScheme::apiKey(
                $config['in'] ?? 'header',
                $config['name'] ?? 'X-API-Key'
            ),
            default => null,
        };
    }
}
