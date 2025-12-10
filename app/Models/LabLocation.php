<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabLocation extends Model
{
    use SoftDeletes;

    protected $fillable = ['lab_id', 'location_id', 'shortName', 'prefix', 'address'];

    public function contacts()
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    public function lab()
    {
        return $this->belongsTo(Lab::class, 'lab_id');
    }
    public function locationRecord()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function departments()
    {
        return $this->hasMany(LabLocationDepartment::class);
    }
}
