<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission;

class UserCustomPermission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_location_department_role_id',
        'permission_id',
        'is_allowed'
    ];

    public function assignment()
    {
        return $this->belongsTo(UserLocationDepartmentRole::class, 'user_location_department_role_id');
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}
