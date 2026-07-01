<?php

namespace App\Domain\Communication\Models;

use App\Domain\Shared\Models\DomainModel;

class NotificationTemplate extends DomainModel
{
    protected $table = 'notification_templates';

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
