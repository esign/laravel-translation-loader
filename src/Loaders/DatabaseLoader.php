<?php

namespace Esign\TranslationLoader\Loaders;

use Esign\TranslationLoader\Contracts\TranslationLoaderContract;
use Esign\TranslationLoader\TranslationLoaderServiceProvider;
use Esign\TranslationLoader\TranslationsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class DatabaseLoader implements TranslationLoaderContract
{
    public function __construct(protected TranslationsCache $translationsCache)
    {
    }

    public function loadTranslations(string $locale, string $group, ?string $namespace = null): array
    {
        $configuredModel = TranslationLoaderServiceProvider::getConfiguredModel();
        $cachedData = $this->translationsCache->remember(fn () => $configuredModel::get()->toArray());
        // Handle both new array caches and old Collection caches for backward compatibility
        $data = is_array($cachedData) ? $cachedData : $cachedData->toArray();
        $translations = $configuredModel::hydrate($data);

        return $translations
            ->where('group', $group)
            ->reduce(function ($translationsArray, Model $model) use ($locale, $group) {
                $translation = $model->getTranslationWithFallback('value', $locale);

                if ($group === '*') {
                    $translationsArray[$model->key] = $translation;
                } elseif ($group !== '*') {
                    Arr::set($translationsArray, $model->key, $translation);
                }

                return $translationsArray;
            }) ?? [];
    }
}
