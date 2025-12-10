<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Instrument extends Model
{
    use softDeletes;
    
    protected $fillable = [
        'name',
        'short_name',
        'manufacturer',
        'serial_no',
        'identifier',
    ];
}