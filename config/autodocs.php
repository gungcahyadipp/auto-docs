<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
    |--------------------------------------------------------------------------
    | API Path
    |--------------------------------------------------------------------------
    |
    | Your API path. By default, all routes starting with this path will be
    | added to the docs. If you need to change this behavior, you can add
    | your custom routes resolver using `Scramble::routes()`.
    |
    */
    'api_path' => env('AUTODOCS_API_PATH', 'api'),

    /*
    |--------------------------------------------------------------------------
    | API Domain
    |--------------------------------------------------------------------------
    |
    | Your API domain. By default, app domain is used. This is also a part
    | of the default API routes matcher.
    |
    */
    'api_domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Export Path
    |--------------------------------------------------------------------------
    |
    | The path where your OpenAPI specification will be exported.
    |
    */
    'export_path' => 'api.json',

    /*
    |--------------------------------------------------------------------------
    | API Info
    |--------------------------------------------------------------------------
    */
    'info' => [
        'version' => env('API_VERSION', '1.0.0'),

        /*
         * Description rendered on the home page of the API documentation.
         * By default, this reads from the published markdown file.
         * You can override with a plain string or null to disable.
         */
        'description' => null, // Will be loaded from description.md file
    ],

    /*
    |--------------------------------------------------------------------------
    | Description File
    |--------------------------------------------------------------------------
    |
    | Path to the markdown file used as the API description on the docs homepage.
    | Publish this file with: php artisan vendor:publish --tag=autodocs-description
    |
    */
    'description_file' => resource_path('docs/autodocs-description.md'),

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Customize Stoplight Elements UI.
    |
    */
    'ui' => [
        /*
         * Define the title of the documentation's website.
         * Uses the application name from config('app.name') by default.
         */
        'title' => env('APP_NAME', 'API Documentation'),

        /*
         * Define the theme of the documentation.
         * Available options: 'light', 'dark', 'system'.
         */
        'theme' => 'dark',

        /*
         * Hide the `Try It` feature. Enabled by default.
         */
        'hide_try_it' => false,

        /*
         * Hide the schemas in the Table of Contents.
         */
        'hide_schemas' => false,

        /*
         * URL to an image that displays as a small square logo next to the title.
         */
        'logo' => '',

        /*
         * Credential policy for the Try It feature.
         * Options: 'omit', 'include' (default), 'same-origin'
         */
        'try_it_credentials_policy' => 'include',

        /*
         * Layout for Elements:
         * - 'sidebar' - Three-column design with a sidebar.
         * - 'responsive' - Like sidebar, collapses at small screens.
         * - 'stacked' - Single column layout.
         */
        'layout' => 'responsive',
    ],

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    |
    | The list of servers of the API. By default, when `null`, server URL
    | will be created from `api_path` and `api_domain` config variables.
    |
    | Example:
    | 'servers' => [
    |     'Live' => 'api',
    |     'Prod' => 'https://example.com/api',
    | ],
    |
    */
    'servers' => null,

    /*
    |--------------------------------------------------------------------------
    | Enum Strategies
    |--------------------------------------------------------------------------
    */
    'enum_cases_description_strategy' => 'description',
    'enum_cases_names_strategy' => false,

    /*
    |--------------------------------------------------------------------------
    | Query Parameters
    |--------------------------------------------------------------------------
    |
    | When true, deep objects in query parameters are flattened (e.g. foo[bar]).
    | When false, they are documented as nested objects.
    |
    */
    'flatten_deep_query_parameters' => true,

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to the documentation routes.
    |
    */
    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Scheme
    |--------------------------------------------------------------------------
    |
    | The default security scheme applied globally to all endpoints.
    | Endpoints annotated with @unauthenticated will be excluded.
    |
    | Supported types: 'bearer', 'apiKey', 'oauth2', 'openIdConnect', null
    |
    | For 'bearer':
    |   'security' => ['type' => 'bearer', 'format' => 'JWT']
    |
    | For 'apiKey':
    |   'security' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key']
    |
    | Set to null to disable global security.
    |
    */
    'security' => [
        'type' => 'bearer',
        'format' => 'JWT',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    |
    | Additional Scramble extensions to register.
    |
    */
    'extensions' => [],

    /*
    |--------------------------------------------------------------------------
    | External Docs (Auto-Docs Feature)
    |--------------------------------------------------------------------------
    |
    | Path to external documentation files. These markdown files will be used
    | to provide descriptions for your API endpoints, separated from your
    | controller code.
    |
    | Structure: {docs_path}/{ControllerName}/{methodName}.md
    |
    | Example: docs/api/UserController/index.md
    |
    */
    'docs_path' => base_path('docs/api'),

    /*
    |--------------------------------------------------------------------------
    | Description Strategy
    |--------------------------------------------------------------------------
    |
    | How external doc files interact with PHPDoc descriptions:
    | - 'file'     : File content overrides PHPDoc description
    | - 'merge'    : File content is appended after PHPDoc description
    | - 'fallback' : File content is used only when PHPDoc description is empty
    |
    */
    'description_strategy' => 'file',
];
