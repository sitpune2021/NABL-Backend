<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabLocationDepartment extends Model
{
     use SoftDeletes;

    protected $fillable = ['location_id', 'department_id'];

    public function labLocation()
    {
        return $this->belongsTo(Location::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
