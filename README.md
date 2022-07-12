# Laravel Translation Finder
Find all translation keys in your blades, scripts, files etc. and translate to the given language.

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

#### Disabling key translations
If you would like to just get the keys from your views, you can use the `--notranslate` option.
This will fill the values with the default keys.

```console
php artisan locale:find --locales=nl --notranslate
```

#### Create missing php key files
When adding new translations keys such as `__("newkey.some text")` the file `newkey.php` should exists in order to add the translation keys.
When using the `--create` option the package will auto generate these files.

```console
php artisan locale:find --create
```

### Beta

#### Modules
When using modules in your laravel package with their own lang folder, you would like to add the keys to the right files in the module folders.

So for example when we have a module called `Horses` and we loaded the translations using `$this->loadTranslationsFrom(PATHTOTRANS, "horses");` in our Service provider. The key should be something like this : `__("horses::")` and with a translation `__("horsed.colors.red")` where `colors.php` is the file.

When using the `--modules` option, the package will auto detect the loaded translation files and adds the keys to the right files.

```console
php artisan locale:find --modules
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
