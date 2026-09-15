# In-process vector math for PHP: cosine, dot, L2, top-k, and batch similarity with a pure-PHP API and optional native acceleration.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eloquage/vector.svg?style=flat-square)](https://packagist.org/packages/eloquage/vector)
[![Tests](https://github.com/eloquage/vector/actions/workflows/run-tests.yml/badge.svg)](https://github.com/eloquage/vector/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/eloquage/vector.svg?style=flat-square)](https://packagist.org/packages/eloquage/vector)

## Installation

Install via Composer (pure PHP; always works without a native extension):

```bash
composer require eloquage/vector
```

### Optional native acceleration

When a release includes a TypePHP-built extension (`eloquage_vector`), you can load it for faster paths. The public PHP API is unchanged.

#### shivammathur/setup-php (GitHub Actions)

Once the extension is on PECL:

```yaml
- uses: shivammathur/setup-php@v2
  with:
    php-version: '8.4'
    extensions: eloquage_vector
```

Until then, install from a GitHub Release phpize/PECL tarball or from source (see [setup-php wiki: Add extension from source](https://github.com/shivammathur/setup-php/wiki/Add-extension-from-source)).

#### docker-php-ext-install

Extract the Release phpize tree to an absolute path, then:

```dockerfile
RUN docker-php-ext-configure /tmp/eloquage_vector \
 && docker-php-ext-install /tmp/eloquage_vector \
 && docker-php-ext-enable eloquage_vector
```

#### PECL

```bash
# from a GitHub Release asset URL (canonical until pecl.php.net listing exists)
pecl install https://github.com/eloquage/vector/releases/download/vX.Y.Z/eloquage_vector-X.Y.Z.tgz
# after channel registration:
# pecl install eloquage_vector
```

#### Windows

Download the Release `eloquage_vector.dll`, place it in your PHP extension directory, and enable:

```ini
extension=eloquage_vector
```

On `windows-latest` with setup-php, the same PECL/DLL path applies once a Windows binary is published.

See [TYPEPHP.md](TYPEPHP.md) for building the extension yourself with the shared builder image.

## Usage

```php
use Eloquage\Vector\Vector;

$vector = new Vector();

echo $vector->name(); // vector
```

## Testing

```bash
composer test
vendor/bin/pest --coverage --min=90
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Pull requests and issues are welcome on [GitHub](https://github.com/eloquage/vector).

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Miguel Enes](https://github.com/eloquage)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Development

See [AGENTS.md](AGENTS.md) for agent context, tests, and TypePHP Docker builds.
