<?php

namespace GungCahyadiPP\AutoDocs\Tests\Feature;

use GungCahyadiPP\AutoDocs\Tests\TestCase;

class ConfigBridgeTest extends TestCase
{
    public function test_autodocs_config_is_loaded(): void
    {
        $this->assertNotNull(config('autodocs'));
        $this->assertIsArray(config('autodocs'));
    }

    public function test_autodocs_config_bridges_to_scramble(): void
    {
        $this->assertNotNull(config('scramble'));
        $this->assertIsArray(config('scramble'));
    }

    public function test_api_path_is_bridged(): void
    {
        $this->assertEquals(
            config('autodocs.api_path'),
            config('scramble.api_path')
        );
    }

    public function test_api_domain_is_bridged(): void
    {
        $this->assertEquals(
            config('autodocs.api_domain'),
            config('scramble.api_domain')
        );
    }

    public function test_middleware_is_bridged(): void
    {
        $this->assertEquals(
            config('autodocs.middleware'),
            config('scramble.middleware')
        );
    }

    public function test_extensions_is_bridged(): void
    {
        $this->assertEquals(
            config('autodocs.extensions'),
            config('scramble.extensions')
        );
    }

    public function test_info_is_bridged(): void
    {
        $this->assertEquals(
            config('autodocs.info'),
            config('scramble.info')
        );
    }

    public function test_ui_config_is_bridged(): void
    {
        $this->assertEquals(
            config('autodocs.ui'),
            config('scramble.ui')
        );
    }

    public function test_custom_api_path_is_respected(): void
    {
        // Override autodocs config
        config(['autodocs.api_path' => 'api/v2']);

        // Re-bridge (simulate fresh registration)
        $provider = new \GungCahyadiPP\AutoDocs\AutoDocsServiceProvider($this->app);
        $provider->register();

        $this->assertEquals('api/v2', config('scramble.api_path'));
    }

    public function test_default_api_path_is_api(): void
    {
        $this->assertEquals('api', config('autodocs.api_path'));
    }

    public function test_autodocs_has_docs_path_config(): void
    {
        $this->assertNotNull(config('autodocs.docs_path'));
    }

    public function test_autodocs_has_description_strategy_config(): void
    {
        $this->assertContains(
            config('autodocs.description_strategy'),
            ['file', 'merge', 'fallback']
        );
    }
}
