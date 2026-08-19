<?php

namespace App\Domain\Shared\Models;

class SystemSetting extends DomainModel
{
    protected $table = 'system_settings';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
    ];
}
