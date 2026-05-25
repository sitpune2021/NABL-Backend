<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrefixConfig extends Model
{
    protected $fillable = [
        'prefix_master',
        'type',
        'min_length',
        'max_length',
    ];
}
