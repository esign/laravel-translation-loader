<?php

namespace Esign\TranslationLoader;

use Esign\TranslationLoader\Commands\ClearTranslationsCacheCommand;
use Esign\TranslationLoader\Commands\ImportFileTranslationsToDatabaseCommand;
use Esign\TranslationLoader\Exceptions\InvalidConfiguration;
use Esign\TranslationLoader\Loaders\AggregateLoader;
use Esign\TranslationLoader\Models\Translation;
use Esign\UnderscoreTranslatable\UnderscoreTranslatable;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Translation\TranslationServiceProvider as BaseTranslationServiceProvider;

class TranslationLoaderServiceProvider extends BaseTranslationServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearTranslationsCacheCommand::class,
                ImportFileTranslationsToDatabaseCommand::class,
            ]);

            $this->publishes([
                $this->configPath() => config_path('translation-loader.php'),
            ], 'config');

            $this->publishes([
                $this->migrationPath() => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_translations_table.php'),
            ], 'migrations');
        }
    }

    public function register()
    {
        $this->registerLoader();
        $this->registerTranslator();
        $this->registerTranslationsCache();
        $this->mergeConfigFrom($this->configPath(), 'translation-loader');
    }

    protected function registerLoader(): void
    {
        $this->app->singleton('translation.loader', function ($app) {
            $aggregateLoader = config('translation-loader.aggregate_loader') ?? AggregateLoader::class;

            return new $aggregateLoader($app['files'], [
                base_path('vendor/laravel/framework/src/Illuminate/Translation/lang'),
                $app['path.lang'],
            ]);
        });
    }

    protected function registerTranslationsCache(): void
    {
        $this->app->bind(TranslationsCache::class, function (Application $app) {
            $cacheManager = $app->make(CacheManager::class);

            return new TranslationsCache(
                $cacheManager->store(config('translation-loader.cache.store')),
                config('translation-loader.cache.key'),
                config('translation-loader.cache.ttl'),
            );
        });
    }

    protected function registerTranslator(): void
    {
        $this->app->singleton('translator', function ($app) {
            $translator = config('translation-loader.translator') ?? Translator::class;
            $loader = $app['translation.loader'];
            $locale = $app['config']['app.locale'];

            $trans = new $translator($loader, $locale);
            $trans->setFallback($app['config']['app.fallback_locale']);

            return $trans;
        });
    }

    protected function configPath(): string
    {
        return __DIR__ . '/../config/translation-loader.php';
    }

    protected function migrationPath(): string
    {
        return __DIR__ . '/../database/migrations/create_translations_table.php.stub';
    }

    public static function getConfiguredModel(): string
    {
        $model = config('translation-loader.model') ?? Translation::class;

        if (! is_a($model, Model::class, true) || ! in_array(UnderscoreTranslatable::class, class_uses_recursive($model))) {
            throw InvalidConfiguration::invalidModel($model);
        }

        return $model;
    }
}
