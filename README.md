# Filament Media Manager

[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/moh/media-manager/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/moh/media-manager/actions/workflows/run-tests.yml)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/moh/media-manager/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/moh/media-manager/actions/workflows/phpstan.yml)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/moh/media-manager/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/moh/media-manager/actions/workflows/fix-php-code-style-issues.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/moh/media-manager.svg?style=flat-square)][link-downloads]
[![License](https://img.shields.io/packagist/l/moh/media-manager.svg?style=flat-square)][link-license]

A comprehensive media manager plugin for Filament v5.

## Installation

You can install the package via composer:

```bash
composer require moh/media-manager
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="media-manager-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="media-manager-config"
```

## Usage

Register the plugin in your Panel Provider:

```php
use Moh\MediaManager\MediaManagerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(MediaManagerPlugin::make());
}
```

## Features

- **Folder-based organization**: Organize your media into folders.
- **Taggable media**: Add tags to your files for easier searching.
- **Support for multiple disks**: Configure which disk to use for storage.
- **Native Filament integration**: Works seamlessly with Filament v5 resources and actions.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Moh](https://github.com/moh)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
