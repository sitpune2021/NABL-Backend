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
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function accesses()
    {
        return $this->hasMany(LabUserAccess::class, 'lab_location_department_id');
    }

}
