<?php

declare(strict_types=1);

namespace Bladeberg\Facades;

use Illuminate\Support\Facades\Facade;

class Bladeberg extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'bladeberg';
    }
}
