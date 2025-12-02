<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role;

class UserLocationDepartmentRole extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'location_id', 'department_id', 'role_id',
        'status', 'position_type', 'start_at', 'end_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function customPermissions()
    {
        return $this->hasMany(UserCustomPermission::class, 'user_location_department_role_id');
    }
}




