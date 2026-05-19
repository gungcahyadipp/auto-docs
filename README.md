# Auto-Docs

All-in-one API documentation package for Laravel. Bundles [Scramble](https://scramble.dedoc.co) and Scramble Pro into a single package with automatic configuration, Bearer token auth, dark theme UI, and external docs support.

## Features

- Zero-config API documentation generation from your Laravel code
- Dark theme UI by default with Stoplight Elements
- Bearer JWT authentication auto-configured
- External markdown docs — separate endpoint descriptions from controller code
- Multi-version API support (V1, V2, etc.) with conflict detection
- Conditional extension loading for optional packages (Laravel Data, Query Builder, JSON:API, Laravel Actions)
- Single unified config file (`autodocs.php`)

## Versions Bundled

- `dedoc/scramble` v0.13.13
- `dedoc/scramble-pro` v0.8.7

## Installation

```bash
composer require gungcahyadipp/auto-docs
```

The package auto-discovers via Laravel's package discovery. No manual provider registration needed.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=autodocs-config
```

Publish the API description markdown:

```bash
php artisan vendor:publish --tag=autodocs-description
```

Publish everything at once:

```bash
php artisan vendor:publish --tag=autodocs
```

## Usage

Visit `/docs` to see the generated API documentation.

The JSON OpenAPI spec is available at `/docs/api.json`.

## Config Overview

All configuration lives in `config/autodocs.php`:

```php
return [
    'api_path' => env('AUTODOCS_API_PATH', 'api'),
    'api_domain' => null,

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => null, // Loaded from markdown file
    ],

    'description_file' => base_path('autodocs/description.md'),

    'ui' => [
        'title' => env('APP_NAME', 'API Documentation'),
        'theme' => 'dark', // 'light', 'dark', 'system'
        'layout' => 'responsive',
    ],

    'security' => [
        'type' => 'bearer',
        'format' => 'JWT',
    ],

    'docs_path' => base_path('autodocs'),
    'description_strategy' => 'file', // 'file', 'merge', 'fallback'
];
```

## Security Scheme

Bearer JWT is enabled by default. All endpoints will show the lock icon in the docs UI.

To exclude an endpoint from authentication, use `@unauthenticated` in the PHPDoc:

```php
/**
 * @unauthenticated
 */
public function login(Request $request) { ... }
```

To switch to API Key authentication:

```php
// config/autodocs.php
'security' => [
    'type' => 'apiKey',
    'in' => 'header',
    'name' => 'X-API-Key',
],
```

To disable security entirely:

```php
'security' => null,
```

## External Docs (Separated Descriptions)

Instead of writing long descriptions in your controller PHPDoc, you can create separate markdown files. **No changes needed in your controller** — the extension automatically matches doc files to endpoints based on controller name and method.

### Quick Start

1. Generate the skeleton:
   ```bash
   php artisan autodocs:generate
   ```

2. Edit the generated `.md` files in `autodocs/`

3. Visit `/docs` — descriptions are loaded automatically

### How It Works (No Controller Changes Needed)

Given this controller:

```php
// app/Http/Controllers/Api/V1/UserController.php

namespace App\Http\Controllers\Api\V1;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        // Your logic here — NO PHPDoc needed for description
        return new UserResource(User::create($request->validated()));
    }
}
```

And this file:

```
autodocs/V1/UserController/store.md
```

The docs UI will automatically show the content from `store.md` as the endpoint description. You don't need to add any annotation, attribute, or PHPDoc to the controller.

### Combining with PHPDoc

If you still want to use PHPDoc for some things (like `@unauthenticated` or `@tags`), that works fine:

```php
/**
 * @unauthenticated
 */
public function login(LoginRequest $request)
{
    // Description comes from autodocs/AuthController/login.md
    // @unauthenticated still works from PHPDoc
}
```

The `description_strategy` config controls how external docs interact with any existing PHPDoc description.


### File Structure

```
autodocs/
├── description.md          ← API homepage description
├── UserController/
│   ├── index.md            → GET /api/users
│   ├── store.md            → POST /api/users
│   └── show.md             → GET /api/users/{id}
├── V1/
│   └── UserController/
│       └── index.md        → For Api\V1\UserController@index
├── V2/
│   └── UserController/
│       └── index.md        → For Api\V2\UserController@index
└── OrderController/
    └── store.md
```

### Markdown Format

```markdown
# Create a new user

Creates a new user account in the system. The user will receive
a verification email after successful registration.

## Business Rules

- Email must be unique
- Password must meet complexity requirements
- Users are created with 'pending' status by default
```

- First line (or `# heading`) becomes the **summary/title**
- Everything after the first blank line becomes the **description**

### Path Resolution

For a controller like `App\Http\Controllers\Api\V1\UserController@store`, the extension searches these paths in order:

1. `autodocs/Api/V1/UserController/store.md` (most specific)
2. `autodocs/V1/UserController/store.md`
3. `autodocs/UserController/store.md` (only if no V1/V2 conflict)
4. `autodocs/Api/V1/User/store.md` (without "Controller" suffix)
5. `autodocs/User/store.md`

### Multi-Version Safety

When both `V1/UserController/` and `V2/UserController/` exist in your docs folder, the short name fallback (`UserController/store.md`) is automatically disabled to prevent cross-version contamination.

### Description Strategy

Control how external docs interact with PHPDoc in your controllers:

| Strategy | Behavior |
|----------|----------|
| `'file'` | Markdown file **overrides** PHPDoc description (default) |
| `'merge'` | Markdown file is **appended** after PHPDoc description |
| `'fallback'` | Markdown file is used **only when** PHPDoc is empty |

## API Description (Homepage)

The API homepage description is loaded from a markdown file:

1. Default: bundled template from the package
2. After publish: `autodocs/description.md`

Edit this file to customize the description shown on the docs homepage.

## Optional Package Support

Auto-Docs conditionally enables extensions for these packages when installed:

| Package | What it enables |
|---------|----------------|
| `spatie/laravel-data` | Data object schema generation |
| `spatie/laravel-query-builder` | Filter/sort/include parameter docs |
| `timacdonald/json-api` | JSON:API resource schema |
| `lorisleiva/laravel-actions` | Actions as controllers |
| `spatie/laravel-json-api-paginate` | JSON:API pagination params |

No configuration needed — just install the package and docs are generated automatically.

## Custom Extensions

Register your own Scramble extensions:

```php
// config/autodocs.php
'extensions' => [
    App\Docs\MyCustomExtension::class,
],
```

Or programmatically in a service provider:

```php
use Dedoc\Scramble\Scramble;

Scramble::registerExtension(MyTypeToSchemaExtension::class);
Scramble::registerExtension(MyOperationExtension::class);
```

## Auto-Generate Docs Skeleton

Automatically scaffold markdown doc files for all your API endpoints:

```bash
php artisan autodocs:generate
```

This scans all registered API routes and creates `.md` files with a ready-to-fill template:

```
autodocs/
├── description.md
├── Api/V1/UserController/
│   ├── index.md
│   ├── store.md
│   ├── show.md
│   ├── update.md
│   └── destroy.md
└── Api/V1/OrderController/
    └── index.md
```

### Options

| Option | Description |
|--------|-------------|
| `--force` | Overwrite existing doc files |
| `--path=` | Custom output path (default: from config `docs_path`) |
| `--api-path=` | Filter routes by API path prefix |

### Example

```bash
# Generate for all API routes
php artisan autodocs:generate

# Only generate for v2 routes
php artisan autodocs:generate --api-path=api/v2

# Regenerate all (overwrite existing)
php artisan autodocs:generate --force
```

Each generated file includes:
- Auto-generated title based on method name (e.g. "List all users", "Create a new order")
- HTTP method and URI as comment
- Placeholder sections for authentication, request body, and response

## Testing

```bash
composer test
```

## License

MIT
