<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $fillable = [
        'country_id',
        'name',
        'code',
        'gst_state_code',
        'is_union_territory',
    ];

    protected $casts = [
        'is_union_territory' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'states';
    }
}
