<?php

namespace Esign\TranslationLoader\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Esign\TranslationLoader\Models\Translation;
use Esign\TranslationLoader\Tests\Concerns\InteractsWithTranslator;
use Esign\TranslationLoader\Tests\Concerns\MakesQueryCountAssertions;
use Esign\TranslationLoader\Tests\TestCase;
use Esign\TranslationLoader\TranslationsCache;

final class TranslationsCacheTest extends TestCase
{
    use InteractsWithTranslator;
    use MakesQueryCountAssertions;

    protected TranslationsCache $translationsCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translationsCache = app(TranslationsCache::class);
        $this->translationsCache->forget();
    }

    #[Test]
    public function it_can_cache_translations(): void
    {
        // Request the translation so the database translations get queried and cached.
        // This causes the first database query.
        trans('key');

        // Reset the internal cache of the translator, so we can make assertions against our own database query cache.
        $this->resetInternalTranslatorCache();

        // Request the translation so the database translations get retrieved from the cache.
        // This should not trigger a database query and leave the query count at 1.
        trans('key');

        $this->assertQueryCount(1);
    }

    #[Test]
    public function it_can_clear_the_cache_when_creating_a_translation(): void
    {
        // Request the translation so the database translations get queried and cached.
        // This causes the first database query.
        trans('translation-key');

        // Create a new database translation so the cache gets busted.
        // This causes the second database query.
        Translation::query()->create(['group' => '*', 'key' => 'translation-key']);

        // Reset the internal cache of the translator, so we can make assertions against our own database query cache.
        $this->resetInternalTranslatorCache();

        // Request the translation so the database translations get queried and cached once again.
        // This causes the third database query.
        trans('translation-key');

        $this->assertQueryCount(3);
    }

    #[Test]
    public function it_can_clear_the_cache_when_updating_a_translation(): void
    {
        // Create the database translation, which causes the first query.
        $translation = Translation::query()->create(['group' => '*', 'key' => 'translation-key']);

        // Request the translation so the database translations get queried and cached.
        // This causes the second database query.
        trans('translation-key');

        // Create a new database translation so the cache gets busted.
        // This causes the third database query.
        $translation->update(['value_en' => 'abc']);

        // Reset the internal cache of the translator, so we can make assertions against our own database query cache.
        $this->resetInternalTranslatorCache();

        // Request the translation so the database translations get queried and cached once again.
        // This causes the fourth database query.
        trans('translation-key');

        $this->assertQueryCount(4);
    }

    #[Test]
    public function it_can_clear_the_cache_when_deleting_a_translation(): void
    {
        // Create the database translation, which causes the first query.
        $translation = Translation::query()->create(['group' => '*', 'key' => 'translation-key']);

        // Request the translation so the database translations get queried and cached.
        // This causes the second database query.
        trans('translation-key');

        // Delete the database translation so the cache gets busted.
        // This causes the third database query.
        $translation->delete();

        // Reset the internal cache of the translator, so we can make assertions against our own database query cache.
        $this->resetInternalTranslatorCache();

        // Request the translation so the database translations get queried and cached once again.
        // This causes the fourth database query.
        trans('translation-key');

        $this->assertQueryCount(4);
    }

    #[Test]
    public function it_can_restore_translations_from_old_cached_collection_objects(): void
    {
        // Create a database translation with a value
        Translation::query()->create(['group' => '*', 'key' => 'test-key', 'value_en' => 'Test Value']);

        // Simulate old cached data as a Collection (the old behavior before array caching)
        // by using remember() which caches and returns the Collection directly
        $this->translationsCache->remember(fn () => Translation::query()->get());

        // Reset the internal translator cache to force re-fetching from the database cache
        $this->resetInternalTranslatorCache();

        // Request the translation - this should work even though the cache contains a Collection instead of an array
        $result = trans('test-key');

        // The translation should still work and return the cached value
        $this->assertEquals('Test Value', $result);
    }
}
