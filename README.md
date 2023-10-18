
# Laravel Swagger generator
Create swagger yaml docs based on laravel API routes and FormRequests

>This package will use your application api routes, form requests and models to generate swagger docs in yaml format

[![Latest Version on Packagist](https://img.shields.io/packagist/v/singlequote/laravel-swagger-generator.svg?style=flat-square)](https://packagist.org/packages/singlequote/laravel-swagger-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/singlequote/laravel-swagger-generator.svg?style=flat-square)](https://packagist.org/packages/singlequote/laravel-swagger-generator)


### Installation
```console
composer require singlequote/laravel-swagger-generator --dev
```

### Publish
Publish the config file
```console
php artisan vendor:publish --tag=laravel-swagger-generator
```

### Usage

```bahs
php artisan swagger:generate
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Postcardware

You're free to use this package, but if it makes it to your production environment we highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.

Our address is: Quotec, Traktieweg 8c 8304 BA, Emmeloord, Netherlands.

## Credits

- [Wim Pruiksma](https://github.com/wimurk)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
