<?php

namespace SingleQuote\SwaggerGenerator;

use Illuminate\Support\Facades\Facade;

class SwaggerGeneratorFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return SwaggerGenerator::class;
    }
}
