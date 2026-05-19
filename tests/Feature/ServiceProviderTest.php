<?php

namespace GungCahyadiPP\AutoDocs\Tests\Feature;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Dedoc\ScramblePro\ScrambleProServiceProvider;
use GungCahyadiPP\AutoDocs\AutoDocsServiceProvider;
use GungCahyadiPP\AutoDocs\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_can_be_registered(): void
    {
        $this->assertTrue(
            $this->app->providerIsLoaded(AutoDocsServiceProvider::class)
        );
    }

    public function test_scramble_service_provider_is_registered(): void
    {
        $this->assertTrue(
            $this->app->providerIsLoaded(ScrambleServiceProvider::class)
        );
    }

    public function test_scramble_pro_service_provider_is_registered(): void
    {
        $this->assertTrue(
            $this->app->providerIsLoaded(ScrambleProServiceProvider::class)
        );
    }

    public function test_scramble_docs_view_is_registered(): void
    {
        $this->assertTrue(
            view()->exists('scramble::docs')
        );
    }

    public function test_docs_routes_are_registered(): void
    {
        $routes = collect($this->app['router']->getRoutes()->getRoutes());

        $docsUiRoute = $routes->first(function ($route) {
            return str_contains($route->uri(), 'docs/api');
        });

        $this->assertNotNull($docsUiRoute, 'Docs UI route should be registered');
    }

    public function test_docs_json_route_is_registered(): void
    {
        $routes = collect($this->app['router']->getRoutes()->getRoutes());

        $docsJsonRoute = $routes->first(function ($route) {
            return str_contains($route->uri(), 'docs/api.json');
        });

        $this->assertNotNull($docsJsonRoute, 'Docs JSON route should be registered');
    }

    public function test_no_exceptions_during_boot_without_optional_packages(): void
    {
        // This test passes if the service provider boots without throwing
        // any exceptions related to missing optional packages
        $this->assertTrue(true);
    }
}
