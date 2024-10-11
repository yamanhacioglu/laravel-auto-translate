<?php

namespace NorthLab\AutoTranslate;

use Illuminate\Support\ServiceProvider;
use NorthLab\AutoTranslate\Services\TranslationService;

class AutoTranslateServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/auto-translate.php' => config_path('auto-translate.php'),
        ], 'config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations/create_deepls_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_deepls_table.php'),
        ], 'migrations');
    }

    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../config/auto-translate.php', 'auto-translate');

        // Register the main service
        $this->app->singleton(TranslationService::class, function ($app) {
            return new TranslationService();
        });

        // Register the facade
        $this->app->bind('auto-translate', function($app) {
            return $app->make(TranslationService::class);
        });
    }
}
