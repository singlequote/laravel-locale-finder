# Laravel Locale Finder
Find and auto translate translations in your laravel application

[![Latest Version on Packagist](https://img.shields.io/packagist/v/singlequote/laravel-locale-finder.svg?style=flat-square)](https://packagist.org/packages/singlequote/laravel-locale-finder)
[![Total Downloads](https://img.shields.io/packagist/dt/singlequote/laravel-locale-finder.svg?style=flat-square)](https://packagist.org/packages/singlequote/laravel-locale-finder)


### Installation
```bash
composer require singlequote/laravel-locale-finder
```

### Usage
The package searches for translations key in your blade files. For example `{{ __("My translation") }}`. Or `@lang('My Translation')`.
After searching the package will try to translate the keys using the google [translate package](https://github.com/Stichoza/google-translate-php).

> When removing translations from your view, the package will also remove the keys from the files.

The command can be used from the commandline

For example, find and translate all dutch translation keys
```bash
php artisan language:find-and-add --locales=nl
```

or find and translate the dutch and german translations keys
```bash
php artisan language:find-and-add --locales=nl,de
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
