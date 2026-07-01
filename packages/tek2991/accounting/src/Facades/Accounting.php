<?php

namespace Tek2991\Accounting\Facades;

use Illuminate\Support\Facades\Facade;

class Accounting extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'accounting';
    }
}
