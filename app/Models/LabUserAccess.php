<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class LabUserAccess extends Model
{
    protected $fillable = [
        'lab_user_id',
        'location_id',
        'lab_location_department_id',
        'role_id',
        'status',
        'granted_at',
        'expires_at'
    ];

    public function labUser()
    {
        return $this->belongsTo(LabUser::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function department()
    {
        return $this->belongsTo(LabLocationDepartment::class, 'lab_location_department_id');
    }

    // ✅ Helper: check active
    public function isActive()
    {
        return $this->status === 'active'
            && (is_null($this->expires_at) || $this->expires_at >= now());
    }

}
