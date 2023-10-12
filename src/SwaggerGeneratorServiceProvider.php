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
            __DIR__.'/config/config.php' => config_path('model-seeder.php')
        ], 'model-seeder');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        //config
        $this->mergeConfigFrom(
            __DIR__ . '/config/config.php',
            'model-seeder'
        );

        app()->config["filesystems.disks.SwaggerGenerator"] = [
            'driver' => 'local',
            'root' => config('model-seeder.paths.lang_folder'),
        ];
                
        $this->commands($this->commands);
    }
}
