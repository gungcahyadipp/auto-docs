<?php

namespace GungCahyadiPP\AutoDocs\Tests;

use GungCahyadiPP\AutoDocs\AutoDocsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AutoDocsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Set minimal app config for testing
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
