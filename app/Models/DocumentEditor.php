<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentEditor extends Model
{
        protected $fillable = [
        'document_id',
        'data_entry_schedule',
        'frequency',
        'item_configs',
        'selected_items',
        'selected_day',
        'selected_month',
        'type',
        'start_date',
        'document',
        'settings',
    ];

    protected $casts = [
        'data_entry_schedule' => 'array',
        'frequency' => 'array',
        'item_configs' => 'array',
        'selected_items' => 'array',
        'start_date' => 'datetime',
        'document' => 'array',
        'settings' => 'array',
    ];
}
