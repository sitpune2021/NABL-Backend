<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class UserAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'lab_id',
        'location_id',
        'role_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
