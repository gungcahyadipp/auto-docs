<?php

namespace GungCahyadiPP\AutoDocs\Tests\Feature;

use Dedoc\Scramble\Scramble;
use GungCahyadiPP\AutoDocs\Tests\TestCase;

class ConditionalExtensionsTest extends TestCase
{
    public function test_boots_without_spatie_laravel_data(): void
    {
        // If spatie/laravel-data is not installed, no LaravelData extensions should be registered
        if (class_exists(\Spatie\LaravelData\Data::class)) {
            $this->markTestSkipped('spatie/laravel-data is installed, skipping absence test');
        }

        // Should not contain any LaravelData extension classes
        $extensions = Scramble::$extensions;

        $laravelDataExtensions = array_filter($extensions, function ($ext) {
            return str_contains($ext, 'LaravelData');
        });

        $this->assertEmpty($laravelDataExtensions, 'LaravelData extensions should not be registered when package is absent');
    }

    public function test_boots_without_spatie_query_builder(): void
    {
        if (class_exists(\Spatie\QueryBuilder\QueryBuilder::class)) {
            $this->markTestSkipped('spatie/laravel-query-builder is installed, skipping absence test');
        }

        $extensions = Scramble::$extensions;

        $queryBuilderExtensions = array_filter($extensions, function ($ext) {
            return str_contains($ext, 'LaravelQueryBuilder');
        });

        $this->assertEmpty($queryBuilderExtensions, 'QueryBuilder extensions should not be registered when package is absent');
    }

    public function test_boots_without_json_api(): void
    {
        if (class_exists(\TiMacDonald\JsonApi\JsonApiResource::class)) {
            $this->markTestSkipped('timacdonald/json-api is installed, skipping absence test');
        }

        $extensions = Scramble::$extensions;

        $jsonApiExtensions = array_filter($extensions, function ($ext) {
            return str_contains($ext, 'JsonApi');
        });

        $this->assertEmpty($jsonApiExtensions, 'JsonApi extensions should not be registered when package is absent');
    }

    public function test_boots_without_laravel_actions(): void
    {
        if (trait_exists(\Lorisleiva\Actions\Concerns\AsAction::class)) {
            $this->markTestSkipped('lorisleiva/laravel-actions is installed, skipping absence test');
        }

        $extensions = Scramble::$extensions;

        $actionsExtensions = array_filter($extensions, function ($ext) {
            return str_contains($ext, 'LaravelActions');
        });

        $this->assertEmpty($actionsExtensions, 'LaravelActions extensions should not be registered when package is absent');
    }

    public function test_boots_without_json_api_paginate(): void
    {
        if (class_exists(\Spatie\JsonApiPaginate\JsonApiPaginateServiceProvider::class)) {
            $this->markTestSkipped('spatie/laravel-json-api-paginate is installed, skipping absence test');
        }

        $extensions = Scramble::$extensions;

        $paginateExtensions = array_filter($extensions, function ($ext) {
            return str_contains($ext, 'LaravelJsonApiPaginate');
        });

        $this->assertEmpty($paginateExtensions, 'JsonApiPaginate extensions should not be registered when package is absent');
    }

    public function test_scramble_extensions_array_is_valid(): void
    {
        // All registered extensions should be valid class strings
        foreach (Scramble::$extensions as $extension) {
            $this->assertIsString($extension, 'Each extension should be a class-string');
            $this->assertTrue(
                class_exists($extension) || interface_exists($extension),
                "Extension class '{$extension}' should exist"
            );
        }
    }
}
