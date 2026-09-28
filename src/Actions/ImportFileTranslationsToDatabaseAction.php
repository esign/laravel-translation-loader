<?php

namespace Esign\TranslationLoader\Actions;

use Esign\TranslationLoader\TranslationLoaderServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\FileLoader;

class ImportFileTranslationsToDatabaseAction
{
    protected FileLoader $fileLoader;

    public function __construct()
    {
        $this->fileLoader = new FileLoader(app('files'), app('path.lang'));

        foreach (app('translation.loader')->jsonPaths() as $jsonPath) {
            $this->fileLoader->addJsonPath($jsonPath);
        }
    }

    public function handle(array $locales, bool $overwrite): int
    {
        return $this->upsertOrInsertTranslations($this->getTranslations($locales), $overwrite);
    }

    protected function getTranslations(array $locales): array
    {
        $groupedTranslations = [];
        foreach ($locales as $locale) {
            $translations = $this->fileLoader->load($locale, '*', '*');
            foreach ($translations as $key => $value) {
                $groupedTranslations[$key][$locale] = $value;
            }
        }

        return $this->normalizeTranslations($groupedTranslations, $locales);
    }

    protected function normalizeTranslations(array $translations, array $locales): array
    {
        foreach ($translations as &$translation) {
            foreach ($locales as $locale) {
                if (! isset($translation[$locale])) {
                    $translation[$locale] = null;
                }
            }
        }

        return $translations;
    }

    protected function prepareTranslationsForUpsert(array $translations): array
    {
        /** @var \Esign\TranslationLoader\Models\Translation */
        $configuredModelClass = TranslationLoaderServiceProvider::getConfiguredModel();

        $preparedTranslations = [];
        foreach ($translations as $key => $values) {
            $translation = new $configuredModelClass();
            $translation->group = '*';
            $translation->key = $key;
            $translation->created_at = now()->toDateTimeString();
            $translation->updated_at = now()->toDateTimeString();
            $translation->setTranslations('value', $values);
            $preparedTranslations[] = $translation->getAttributes();
        }

        return $preparedTranslations;
    }

    protected function upsertOrInsertTranslations(array $translations, bool $overwrite): int
    {
        /** @var \Esign\TranslationLoader\Models\Translation */
        $configuredModelClass = TranslationLoaderServiceProvider::getConfiguredModel();
        $translations = $this->prepareTranslationsForUpsert($translations);
        $affectedRecords = 0;

        DB::transaction(function () use ($translations, $configuredModelClass, $overwrite, &$affectedRecords) {
            foreach (array_chunk($translations, 500, true) as $chunk) {
                if ($overwrite) {
                    $affectedRecords += $configuredModelClass::query()->upsert($chunk, ['key', 'group']);
                } else {
                    $affectedRecords += $configuredModelClass::query()->insertOrIgnore($chunk);
                }
            }
        });

        return $affectedRecords;
    }
}
