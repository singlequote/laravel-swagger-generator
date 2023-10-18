<?php

namespace SingleQuote\SwaggerGenerator;

use Illuminate\Support\ServiceProvider;
use SingleQuote\SwaggerGenerator\Commands\Make;

class SwaggerGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Commands.
     *
     * @var array
     */
    protected $commands = [
        Make::class,
    ];


    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/config.php' => config_path('laravel-swagger-generator.php')
        ], 'laravel-swagger-generator');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        //config
        $this->mergeConfigFrom(
            __DIR__ . '/config/config.php',
            'laravel-swagger-generator'
        );

        $this->commands($this->commands);
    }
}
