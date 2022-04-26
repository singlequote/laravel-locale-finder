# Laravel Locale Finder
This package finds all translations in your application and auto translates the keys to every language.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/singlequote/laravel-locale-finder.svg?style=flat-square)](https://packagist.org/packages/singlequote/laravel-locale-finder)
[![Total Downloads](https://img.shields.io/packagist/dt/singlequote/laravel-locale-finder.svg?style=flat-square)](https://packagist.org/packages/singlequote/laravel-locale-finder)


### Installation
```console
composer require singlequote/laravel-locale-finder
```

### Publish config
You can change the behaviour of the package by editing the config file. Publish the config file with the command below.

```console
php artisan vendor:publish --tag=locale-finder
```

### Usage
The package searches for translations key in your blade files. For example `{{ __("My translation") }}`. Or `@lang('My Translation')`.
After searching the package will try to translate the keys using the google [translate package](https://github.com/Stichoza/google-translate-php).

> When removing translations from your view, the package will also remove the keys from the files.

The command can be used from the commandline

For example, find and translate all dutch translation keys
```console
php artisan locale:find --locales=nl
```

or find and translate the dutch and german translations keys
```console
php artisan locale:find --locales=nl,de
```

#### Translate all available locales
The `all` option will scan your lang folder and select the available .json files.

```console
php artisan locale:find --locales=all
```
#### Change source
If you develop your application in a different language you can change the defualt source from `en` to something else.

```console
php artisan locale:find --locales=nl --source=de
```

#### Disabling translation
If you would like to just get the keys from your views, you can use the `--notranslate` option.
This will fill the values with the default keys.

```console
php artisan locale:find --locales=nl --notranslate
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
