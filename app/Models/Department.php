<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use softDeletes;
    
    protected $fillable = [
        'name',
        'identifier',
        'owner_type',
        'owner_id'
    ];
}
