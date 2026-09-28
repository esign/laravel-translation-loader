<?php

namespace Esign\TranslationLoader\Tests\Feature\Commands;

use PHPUnit\Framework\Attributes\Test;
use Esign\TranslationLoader\Commands\ImportFileTranslationsToDatabaseCommand;
use Esign\TranslationLoader\Models\Translation;
use Esign\TranslationLoader\Tests\TestCase;

final class ImportFileTranslationToDatabaseCommandTest extends TestCase
{
    #[Test]
    public function it_can_import_translations(): void
    {
        $this->artisan(ImportFileTranslationsToDatabaseCommand::class, ['--locales' => 'en,nl']);

        $this->assertDatabaseHas(Translation::class, [
            'group' => '*',
            'key' => 'Hello world',
            'value_en' => 'Hello world',
            'value_nl' => 'Hallo wereld',
        ]);
    }

    #[Test]
    public function it_can_import_translations_for_specific_locales(): void
    {
        $this->artisan(ImportFileTranslationsToDatabaseCommand::class, ['--locales' => 'en']);

        $this->assertDatabaseHas(Translation::class, [
            'group' => '*',
            'key' => 'Hello world',
            'value_en' => 'Hello world',
            'value_nl' => null,
        ]);
    }

    #[Test]
    public function it_wont_overwrite_existing_translations_when_the_overwrite_flag_was_not_given(): void
    {
        Translation::create([
            'group' => '*',
            'key' => 'Hello world',
            'value_en' => 'Goodbye world',
            'value_nl' => 'Tot ziens wereld',
        ]);

        $this->artisan(ImportFileTranslationsToDatabaseCommand::class, ['--locales' => 'en,nl']);

        $this->assertDatabaseHas(Translation::class, [
            'group' => '*',
            'key' => 'Hello world',
            'value_en' => 'Goodbye world',
            'value_nl' => 'Tot ziens wereld',
        ]);
    }

    #[Test]
    public function it_can_overwrite_existing_translations(): void
    {
        Translation::create([
            'group' => '*',
            'key' => 'Hello world',
            'value_en' => 'Goodbye world',
            'value_nl' => 'Tot ziens wereld',
        ]);

        $this->artisan(ImportFileTranslationsToDatabaseCommand::class, ['--locales' => 'en,nl', '--overwrite' => true]);

        $this->assertDatabaseHas(Translation::class, [
            'group' => '*',
            'key' => 'Hello world',
            'value_en' => 'Hello world',
            'value_nl' => 'Hallo wereld',
        ]);
    }

    #[Test]
    public function it_wont_overwrite_existing_translations_for_locales_that_were_not_specified(): void
    {
        Translation::create([
            'group' => '*',
            'key' => 'Hello world',
            'value_en' => 'Goodbye world',
            'value_nl' => 'Tot ziens wereld',
        ]);

        $this->artisan(ImportFileTranslationsToDatabaseCommand::class, ['--locales' => 'en', '--overwrite' => true]);

        $this->assertDatabaseHas(Translation::class, [
            'group' => '*',
            'key' => 'Hello world',
            'value_en' => 'Hello world',
            'value_nl' => 'Tot ziens wereld',
        ]);
    }

    #[Test]
    public function it_can_import_translations_registered_by_packages(): void
    {
        // Arrange
        $this->app['translator']->addJsonPath(__DIR__ . '/../../fixtures/package-lang');

        // Act
        $this->artisan(ImportFileTranslationsToDatabaseCommand::class, ['--locales' => 'en,nl']);

        // Assert
        $this->assertDatabaseHas(Translation::class, [
            'group' => '*',
            'key' => 'Hello package',
            'value_en' => 'Hello package',
            'value_nl' => 'Hallo pakket',
        ]);
    }

    #[Test]
    public function it_prefers_application_translations_over_package_translations(): void
    {
        // Arrange
        $this->app['translator']->addJsonPath(__DIR__ . '/../../fixtures/package-lang');

        // Act
        $this->artisan(ImportFileTranslationsToDatabaseCommand::class, ['--locales' => 'en,nl']);

        // Assert
        $this->assertDatabaseHas(Translation::class, [
            'group' => '*',
            'key' => 'Hello world',
            'value_en' => 'Hello world',
            'value_nl' => 'Hallo wereld',
        ]);
    }

    #[Test]
    public function it_can_report_the_affected_records(): void
    {
        $command = $this->artisan(ImportFileTranslationsToDatabaseCommand::class, ['--locales' => 'en']);

        $command->expectsOutputToContain('Successfully imported translations, affected records: 1.');
        $command->assertSuccessful();
    }

    #[Test]
    public function it_can_report_the_affected_records_when_a_translation_is_already_present(): void
    {
        Translation::create([
            'group' => '*',
            'key' => 'Hello world',
            'value_en' => 'Hello world',
        ]);

        $command = $this->artisan(ImportFileTranslationsToDatabaseCommand::class, ['--locales' => 'en']);

        $command->expectsOutputToContain('Successfully imported translations, affected records: 0.');
        $command->assertSuccessful();
    }

    #[Test]
    public function it_can_report_affected_records_when_the_overwrite_flag_is_given(): void
    {
        $command = $this->artisan(ImportFileTranslationsToDatabaseCommand::class, ['--locales' => 'en', '--overwrite' => true]);

        $command->expectsOutputToContain('Successfully imported translations, affected records: 1.');
        $command->assertSuccessful();
    }

    #[Test]
    public function it_can_report_affected_records_when_the_overwrite_flag_is_given_and_a_translation_is_already_present(): void
    {
        Translation::create([
            'group' => '*',
            'key' => 'Hello world',
            'value_en' => 'Hello world',
        ]);

        $command = $this->artisan(ImportFileTranslationsToDatabaseCommand::class, ['--locales' => 'en', '--overwrite' => true]);

        $command->expectsOutputToContain('Successfully imported translations, affected records: 1.');
        $command->assertSuccessful();
    }
}
