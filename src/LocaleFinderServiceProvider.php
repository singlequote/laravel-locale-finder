<?php
namespace SingleQuote\LocaleFinder;

use Illuminate\Support\ServiceProvider;
use SingleQuote\LocaleFinder\Commands\FindAndAddLanguageKeysCommand;

class LocaleFinderServiceProvider extends ServiceProvider
{

    /**
     * Commands.
     *
     * @var array
     */
    protected $commands = [
        FindAndAddLanguageKeysCommand::class,
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
