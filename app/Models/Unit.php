<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use softDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'owner_type',
        'owner_id',
        'status'
    ];

    protected $casts = [
        'owner_id'  => 'integer',
        'parent_id' => 'integer',
        'status'    => 'string',
    ];

    protected $appends = [
        'is_master',
        'is_override',
        'is_custom',
    ];

        // 🔗 Lab override → Master unit
    public function parent()
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    // 🔁 Master unit → Lab overrides
    public function overrides()
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }
    public function lab()
    {
        return $this->hasOneThrough(
            Lab::class,      // Final Model
            Unit::class, // Intermediate (Lab Unit)

            'id',            // Intermediate PK (units.id)
            'id',            // Final PK (labs.id)

            'parent_id',     // FK on SuperAdmin Unit
            'owner_id'       // FK on Lab Unit
        )->where('units.owner_type', 'lab')  // 🔥 VERY IMPORTANT
        ->select('labs.id', 'labs.name');
    }
    public function appendedMaster()
    {
        return $this->hasOne(Unit::class, 'parent_id')
            ->where('owner_type', 'super_admin');
    }

    public function scopeSuperAdmin($query)
    {
        return $query->where('owner_type', 'super_admin');
    }

    public function scopeForLab($query, int $labId)
    {
        return $query->where('owner_type', 'lab')
                     ->where('owner_id', $labId);
    }

    public function getIsMasterAttribute(): bool
    {
        return $this->owner_type === 'super_admin';
    }

    /**
     * Is lab override of a master unit
     */
    public function getIsOverrideAttribute(): bool
    {
        return $this->owner_type === 'lab' && $this->parent_id !== null;
    }

    /**
     * Is lab-only custom unit
     */
    public function getIsCustomAttribute(): bool
    {
        return $this->owner_type === 'lab' && $this->parent_id === null;
    }

    public function scopeAccessible($query, $labId)
    {
        return $query->where(function ($q) use ($labId) {

            // 1️⃣ All lab units (custom + overrides)
            $q->where(function ($lab) use ($labId) {
                $lab->where('owner_type', 'lab')
                    ->where('owner_id', $labId);
            })

            // 2️⃣ Master units NOT overridden by this lab
            ->orWhere(function ($master) use ($labId) {
                $master->where('owner_type', 'super_admin')
                    ->whereDoesntHave('overrides', function ($override) use ($labId) {
                        $override->where('owner_type', 'lab')
                                ->where('owner_id', $labId);
                    });
            });

        });
    }
}
