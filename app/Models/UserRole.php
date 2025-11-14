<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserRole extends Model
{
    protected $fillable = [
        'user_id',
        'role_id',
        'start_date',
        'end_date',
    ];

    protected $dates = ['start_date', 'end_date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function isActive(): bool
    {
        if ($this->end_date === null) {
            return true; // Permanent role
        }

        return now()->between($this->start_date, $this->end_date);
    }
}
