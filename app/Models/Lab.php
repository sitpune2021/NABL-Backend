<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lab extends Model
{
     use SoftDeletes;

    protected $fillable = [
        'name',
        'lab_type',
        'lab_code',
        'location_limit',
        'user_limit',
        'created_by',
        'standard_id'
    ];

    public function users()
    {
        return $this->hasMany(LabUser::class);
    }

    public function instruments()
    {
        return $this->hasMany(LabInstrumentAssignment::class);
    }

    public function contacts()
    {
        return $this->morphMany(Contact::class,'contactable');
    }

    public function emails()
    {
        return $this->contacts()->where('type', 'email');
    }

    public function phones()
    {
        return $this->contacts()->where('type', 'phone');
    }

    public function location()
    {
        return $this->hasMany(Location::class, 'owner_id');
    }

    public function labClauseDocuments()
    {
        return $this->hasMany(ClauseDocumentLink::class, 'owner_id')->where('owner_type', 'lab');
    }

}
