<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
     use SoftDeletes;

    protected $fillable = [ 
        'contactable_id',
        'contactable_type',
        'type',
        'value',
        'label',
        'is_primary'
    ];

    /**
     * Polymorphic relation: belongs to any model (Lab, Doctor, etc.)
    */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

}
