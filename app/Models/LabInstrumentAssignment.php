<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabInstrumentAssignment extends Model
{
    protected $fillable = [
        'instrument_id',
        'lab_id',
        'location_id',
        'lab_location_department_id',
        'is_active'
    ];

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    public function instrument()
    {
        return $this->belongsTo(Instrument::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function department()
    {
        return $this->belongsTo(LabLocationDepartment::class, 'lab_location_department_id');
    }
}
