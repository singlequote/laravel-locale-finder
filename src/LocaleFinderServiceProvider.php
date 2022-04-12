<?php
namespace SingleQuote\LocaleFinder;

use Illuminate\Support\ServiceProvider;

class LocaleFinderServiceProvider extends ServiceProvider
{

    /**
     * Commands.
     *
     * @var array
     */
    protected $commands = [
        InviteAllEmployees::class,
    ];

    
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->commands($this->commands);
    }
}
