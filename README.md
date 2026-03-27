# Load translations from the database or other sources

[![Latest Version on Packagist](https://img.shields.io/packagist/v/esign/laravel-translation-loader.svg?style=flat-square)](https://packagist.org/packages/esign/laravel-translation-loader)
[![Total Downloads](https://img.shields.io/packagist/dt/esign/laravel-translation-loader.svg?style=flat-square)](https://packagist.org/packages/esign/laravel-translation-loader)
![GitHub Actions](https://github.com/esign/laravel-translation-loader/actions/workflows/main.yml/badge.svg)

This package extends Laravel's default translation functionality, allowing you to load translations from different sources.
It ships with a database loader that comes with automatic creation of missing keys and built-in caching support.

## Installation
You can install the package via composer:

```bash
composer require esign/laravel-translation-loader
```

The package will automatically register a service provider.

This package comes with a migration to store translations in the database. You can publish the migration file with the following command:
```bash
php artisan vendor:publish --provider="Esign\TranslationLoader\TranslationLoaderServiceProvider" --tag="migrations"
```

This will publish the following migration:
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('group')->default('*');
            $table->text('value_en')->nullable();
            $table->timestamps();

            $table->unique(['key', 'group']);
        });
    }
};
```

Out of the box, it ships with support for the English language and uses our [Underscore Translatable](https://github.com/esign/laravel-underscore-translatable) package to store these languages in different columns. You may add more languages as you wish.

Next up, you can optionally publish the configuration file:
```bash
php artisan vendor:publish --provider="Esign\TranslationLoader\TranslationLoaderServiceProvider" --tag="config"
```

The config file will be published as config/translation-loader.php with the following contents:
```php
return [
    /**
     * These loaders will load translations from different sources.
     * You can use any class that implements the TranslationLoaderContract.
     */
    'loaders' => [
        \Esign\TranslationLoader\Loaders\DatabaseLoader::class,
    ],

    /**
     * This is the loader that combines all of the other loaders together.
     * This class overrides Laravel's default `translation.loader`.
     */
    'aggregate_loader' => \Esign\TranslationLoader\Loaders\AggregateLoader::class,

    /**
     * This is the model that will be used by the DatabaseLoader.
     * You may provide a class that implements the UnderscoreTranslatable trait.
     */
    'model' => \Esign\TranslationLoader\Models\Translation::class,

    'cache' => [
        /**
         * The key that will be used to cache the database translations.
         */
        'key' => 'esign.laravel-translation-loader.translations',

        /**
         * The duration for which database translations will be cached.
         */
        'ttl' => \DateInterval::createFromDateString('24 hours'),

        /**
         * The cache store to be used for database translations.
         * Use null to utilize the default cache store from the cache.php config file.
         * To disable caching, you can use the 'array' store.
         */
        'store' => null,
    ],

    /**
     * Configuration for the custom translator class that handles missing translation keys.
     * This class overrides Laravel's default `translator` binding.
     */
    'translator' => \Esign\TranslationLoader\Translator::class,
];
```

## Usage
To create database translations you may use the `create` method on the `Translation` model:
```php
use Esign\TranslationLoader\Models\Translation;

Translation::create([
    'group' => 'messages',
    'key' => 'welcome',
    'value_en' => 'Hello World!',
    'value_nl' => 'Hallo Wereld!',
]);
```

For a more automated approach, consider [automatic creation of database translations](#automatically-creating-missing-translation-keys), eliminating the need for manual key creation.

Once created, you can retrieve the translations as usual in Laravel:
```php
trans('messages.welcome'); // Hello World!
trans('messages.welcome', [], 'nl'); // Hallo Wereld!
```

For all possibilities, please refer to the [Localization](https://laravel.com/docs/localization) docs from Laravel.
Be aware that database-defined translations can overwrite file translations that may exist.
### Handling missing translation keys
In situations where you request a translation key that doesn't exist, you have the option to provide a callback to the translator.
This callback will be triggered when the requested translation key is not found.
Please note that this callback will not be invoked if the translation key exists but has an empty or null value.

You can also customize the behavior of the translator by returning a specific value from the callback.
This returned value will then be used as the translation for the missing key.

You may provide this callback using the `setMissingKeyCallback` method on the `Esign\TranslationLoader\Facades\Translator` facade:

```php
use Esign\TranslationLoader\Facades\Translator;

Translator::setMissingKeyCallback(function (string $key, string $locale) {
    // Implement your custom logic here

    return "Fallback translation for '$key'";
});
```

In the provided closure, you can implement any custom logic you need to handle the missing translation keys.
This might involve logging, sending notifications, or providing a default translation value based on your application's requirements.

### Automatically creating missing translation keys
This package ships with the ability to automatically create database translations in case the key does not yet exist.
You may activate this functionality by calling the `createMissingTranslations` on the `Esign\TranslationLoader\Facades\Translator` facade.
This is typically done from a service provider within your application:
```php
use Esign\TranslationLoader\Facades\Translator;

Translator::createMissingTranslations();
```

Note that this functionality will create translations under the `*` group.
In case you need to change this behaviour you may do so by [defining your own `setMissingKeyCallback`](#handling-missing-translation-keys).

### Registering a loader
If you need to gather translations from diverse sources, you can achieve this by creating a custom translation loader that implements the `Esign\TranslationLoader\Contracts\TranslationLoaderContract` interface:

```php
use Esign\TranslationLoader\Contracts\TranslationLoaderContract;

class MyTranslationsLoader implements TranslationLoaderContract
{
    public function loadTranslations(string $locale, string $group, ?string $namespace = null): array
    {
        // Your implementation here
    }
}
```

Integrate your custom loader by including it in the `loaders` array within the configuration file of this package.

### Caching database translations
By default, this package ensures efficient performance by caching your database translations for 24 hours.
This caching mechanism uses the default cache driver that you have configured within your Laravel application.

If you wish to modify the cache duration or switch to a different cache store, please refer to the cache settings within the [configuration file](/config/translation-loader.php).

### Clearing the translations cache
The translations cache is automatically maintained when you interact with the `Esign\TranslationLoader\Models\Translation` model.
However, if you make changes outside of these operations, you need to manually clear the cache:
```bash
php artisan translations:clear-cache
```

### Importing file translations to the database
This package ships with an Artisan command that allows you to import file translations into the database.
This can be useful when you want to migrate your translations from file-based to database-based storage.
You should specify the locales you want to import translations for as a comma-separated list:
```bash
php artisan translations:import-files-to-database --locales=en,nl
```

You can optionally specify the `--overwrite` flag to overwrite any existing translations.
```bash
php artisan translations:import-files-to-database --locales=en,nl --overwrite
```

### FAQ
<details>
<summary>Installation conflict with [mcamara/laravel-localization](https://github.com/mcamara/laravel-localization)</summary>

The laravel-localization package offers route translation functionality by leveraging Laravel's translator.
However, conflicts may arise due to our package's override of Laravel's translator behavior.
This can lead to potential database exceptions when querying translations, hindering the installation of our package.

To tackle this problem, you can utilize contextual binding within a service provider of your application.
This instructs Laravel to employ file-based translation solely when registering translated routes.

Include the following code in a serviceprovider within your application:
```php
use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Foundation\Application;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Mcamara\LaravelLocalization\LaravelLocalization;

public function register(): void
{
    $this
        ->app
        ->when(LaravelLocalization::class)
        ->needs(TranslatorContract::class)
        ->give(function (Application $app) {
            $loader = new FileLoader($app['files'], [__DIR__.'/lang', $app['path.lang']]);

            return new Translator($loader, $app->getLocale());
        });
}
```

Note that this solution only works for any versions above `^2.0` of mcamara/laravel-localization.
</details>

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
