<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Cluster;

class Location extends Model
{
     use softDeletes;

     protected $fillable = [
        'cluster_id',
        'name',
        'identifier',
        'short_name'
    ];

    public function cluster()
    {
        return $this->belongsTo(Cluster::class, 'cluster_id');
    }
}
