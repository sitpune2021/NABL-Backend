<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Zone;

class Cluster extends Model
{
    use softDeletes;

     protected $fillable = [
        'zone_id',
        'name',
        'identifier',
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }
}
