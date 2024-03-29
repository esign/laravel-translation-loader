<?php

namespace Esign\TranslationLoader\Tests\Feature;

use Esign\TranslationLoader\Tests\Loaders\CustomAggregateLoader;
use Esign\TranslationLoader\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class CustomAggregateLoaderTest extends TestCase
{
    /** @test */
    public function it_can_use_a_custom_aggregate_loader()
    {
        Config::set('translation-loader.aggregate_loader', CustomAggregateLoader::class);

        $this->assertInstanceOf(CustomAggregateLoader::class, $this->app['translation.loader']);
    }
}
