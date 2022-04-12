<?php

namespace SingleQuote\LocaleFinder;

use Illuminate\Support\Facades\Facade;

class LocaleFinderFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'LocaleFinder';
    }
}
