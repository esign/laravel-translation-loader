<?php

namespace Esign\TranslationLoader\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Esign\TranslationLoader\Models\Translation;
use Esign\TranslationLoader\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Orchestra\Testbench\Attributes\UsesVendor;

final class TranslationTest extends TestCase
{
    #[Test]
    public function it_can_retrieve_a_file_translation(): void
    {
        $this->assertEquals('en value', trans('file.key'));
        $this->assertEquals('nested key', trans('file.nested-key.title'));
        $this->assertEquals('this is a nested key', trans('file.nested-key.message'));
    }

    #[Test]
    public function it_can_retrieve_a_file_translation_for_a_locale(): void
    {
        $this->assertEquals('nl value', trans('file.key', [], 'nl'));
        $this->assertEquals('geneste key', trans('file.nested-key.title', [], 'nl'));
        $this->assertEquals('dit is een geneste key', trans('file.nested-key.message', [], 'nl'));
    }

    #[Test]
    public function it_can_retrieve_a_database_translation(): void
    {
        $this->createTranslation('*', 'database.key', ['value_en' => 'test en']);

        $this->assertEquals('test en', trans('database.key'));
    }

    #[Test]
    public function it_can_retrieve_a_database_translation_for_a_locale(): void
    {
        $this->createTranslation('*', 'database.key', [
            'value_en' => 'test en',
            'value_nl' => 'test nl',
        ]);

        $this->assertEquals('test nl', trans('database.key', [], 'nl'));
    }

    #[Test]
    public function it_can_prefer_a_database_translation_over_a_file_translation(): void
    {
        $this->createTranslation('*', 'file.key', ['value_en' => 'en value database']);

        $this->assertEquals('en value database', trans('file.key'));
    }

    #[Test]
    public function it_can_retrieve_a_database_translation_using_a_fallback(): void
    {
        Config::set('app.locale', 'nl');
        Config::set('app.fallback_locale', 'en');
        $this->createTranslation('*', 'database.key', [
            'value_en' => 'test en',
            'value_nl' => null,
        ]);

        $this->assertEquals('test en', trans('database.key', [], 'nl'));
    }

    #[Test]
    public function it_can_retrieve_a_database_translation_as_a_string_if_the_full_nested_key_is_given(): void
    {
        $this->createTranslation('errors', 'test', ['value_en' => 'test en']);

        $this->assertEquals('test en', trans('errors.test'));
    }

    #[Test]
    public function it_can_retrieve_a_database_translation_as_an_array_if_a_part_of_the_nested_key_is_given(): void
    {
        $this->createTranslation('errors', 'testA', ['value_en' => 'testA en']);
        $this->createTranslation('errors', 'testB', ['value_en' => 'testB en']);

        $this->assertEquals(
            ['testA' => 'testA en', 'testB' => 'testB en'],
            trans('errors')
        );
    }

    #[Test]
    public function it_can_create_a_translation_entry_when_the_key_does_not_exist(): void
    {
        app('translator')->createMissingTranslations();
        trans('this-key-does-not-exist');

        $this->assertDatabaseHas(Translation::class, [
            'key' => 'this-key-does-not-exist',
            'group' => '*',
        ]);
    }

    #[Test]
    public function it_wont_create_multiple_translation_entries_when_the_translation_was_called_multiple_times(): void
    {
        app('translator')->createMissingTranslations();
        trans('this-key-does-not-exist');
        trans('this-key-does-not-exist');

        $this->assertDatabaseCount(Translation::class, 1);
    }

    /**
     * This can occur if a translation key exists in the database but not in the cache.
     * This might happen if the cache was not cleared properly or if the translation was directly added to the database.
     * In such cases, the database will throw an exception due to the unique constraint on the key.
     * We expect the translator to catch this exception and continue without throwing an error.
     */
    #[Test]
    public function it_wont_throw_exceptions_if_the_translation_key_already_exists(): void
    {
        // We expect this test to not throw any exceptions.
        $this->expectNotToPerformAssertions();

        // Call the translation key to make sure the translations cache is built.
        trans('welcome');

        // Simulate the translation key manually being added to the database.
        Translation::withoutEvents(function () {
            Translation::create([
                'group' => '*',
                'key' => 'welcome',
                'value_en' => 'Hello World!',
                'value_nl' => 'Hallo Wereld!',
            ]);
        });

        // Ensure the translator will create missing translations.
        app('translator')->createMissingTranslations();

        // Call the translation key again to trigger the translation creation.
        // This should not throw an exception.
        trans('welcome');
    }

    #[Test]
    public function it_wont_create_internal_laravel_translation_keys_when_validating(): void
    {
        app('translator')->createMissingTranslations();
        $validator = Validator::make(
            data: ['email' => null],
            rules: ['email' => 'required'],
        );

        try {
            $validator->validated();
        } catch (ValidationException) {
        };

        $this->assertDatabaseCount(Translation::class, 0);
        $this->assertDatabaseMissing(Translation::class, ['key' => 'validation.values.email.']);
        $this->assertDatabaseMissing(Translation::class, ['key' => 'validation.custom']);
        $this->assertDatabaseMissing(Translation::class, ['key' => 'validation.custom.email.required']);
        $this->assertDatabaseMissing(Translation::class, ['key' => 'validation.attributes']);
    }

    #[Test]
    public function it_can_pass_the_app_locale_to_the_missing_key_callback_when_no_locale_is_given(): void
    {
        app()->setLocale('en');
        app('translator')->setMissingKeyCallback(function (string $key, string $locale) {
            return $locale;
        });

        $this->assertEquals('en', trans('translation-key'));
    }

    #[Test]
    public function it_can_pass_the_correct_locale_to_the_missing_key_callback(): void
    {
        app('translator')->setMissingKeyCallback(function (string $key, string $locale) {
            return $locale;
        });

        $this->assertEquals('nl', trans('translation-key', [], 'nl'));
    }

    #[Test]
    public function it_can_pass_the_correct_key_to_the_missing_key_callback(): void
    {
        app('translator')->setMissingKeyCallback(function (string $key, string $locale) {
            return $key;
        });

        $this->assertEquals('translation-key', trans('translation-key'));
    }

    #[Test]
    public function it_can_set_a_missing_key_callback_and_return_a_custom_value(): void
    {
        app('translator')->setMissingKeyCallback(function (string $key, string $locale) {
            return 'Custom value';
        });

        $this->assertEquals('Custom value', trans('this-key-does-not-exist'));
    }

    #[Test]
    public function it_can_set_a_missing_key_callback_and_return_a_custom_value_with_replacements(): void
    {
        app('translator')->setMissingKeyCallback(function (string $key, string $locale) {
            return 'Custom value :value';
        });

        $this->assertEquals('Custom value abc', trans('this-key-does-not-exist', ['value' => 'abc']));
    }

    #[Test]
    public function it_wont_call_the_missing_key_callback_when_the_translation_exists_with_a_null_value(): void
    {
        $this->createTranslation('*', 'translation-key', ['value_en' => null]);
        app('translator')->setMissingKeyCallback(function (string $key, string $locale) {
            return 'Custom';
        });

        $this->assertEquals('translation-key', trans('translation-key'));
    }

    #[Test]
    public function it_wont_call_the_missing_key_callback_when_the_translation_exists_in_a_json_file(): void
    {
        app('translator')->setMissingKeyCallback(function (string $key, string $locale) {
            $this->fail('The missing key callback was called unexpectedly.');
        });

        $this->assertEquals('Hello world', trans('Hello world'));
    }

    #[Test]
    public function it_wont_call_the_missing_key_callback_when_the_translation_exists_in_a_php_file(): void
    {
        app('translator')->setMissingKeyCallback(function (string $key, string $locale) {
            $this->fail('The missing key callback was called unexpectedly.');
        });

        $this->assertEquals('en value', trans('file.key'));
    }

    #[Test]
    #[UsesVendor]
    public function it_can_load_default_file_translations_from_the_framework(): void
    {
        // When using the UsesVendor attribute, refresh the application to prevent container resolution errors,
        // such as "Illuminate\Contracts\Container\BindingResolutionException: Target class [config] does not exist".
        $this->refreshApplication();

        $this->assertEquals('The provided password is incorrect.', trans('auth.password'));
        $this->assertEquals('Next &raquo;', trans('pagination.next'));
        $this->assertEquals('Your password has been reset.', trans('passwords.reset'));
        $this->assertEquals('The :attribute field must be accepted.', trans('validation.accepted'));
    }

    #[Test]
    #[UsesVendor]
    public function it_can_override_default_file_translations_from_the_framework(): void
    {
        // When using the UsesVendor attribute, refresh the application to prevent container resolution errors,
        // such as "Illuminate\Contracts\Container\BindingResolutionException: Target class [config] does not exist".
        $this->refreshApplication();

        $this->assertEquals('Custom - The :attribute field must be a valid URL.', trans('validation.active_url'));
    }
}
