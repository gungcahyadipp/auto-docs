# Auto-Docs

All-in-one API documentation package for Laravel. Bundles [Scramble](https://scramble.dedoc.co) and Scramble Pro into a single package with locked versions.

## Versions Bundled

- `dedoc/scramble` v0.13.13
- `dedoc/scramble-pro` v0.8.7

## Installation

```bash
composer require gungcahyadipp/auto-docs
```

## Configuration

Publish the Scramble config file:

```bash
php artisan vendor:publish --tag=scramble-config
```

## Usage

Visit `/docs` to see the generated API documentation.

For more details, refer to the [Scramble documentation](https://scramble.dedoc.co/usage).

## License

MIT
