<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
     use softDeletes;

     protected $fillable = [
        'parent_id',
        'cluster_id',
        'name',
        'identifier',
        'short_name',
        'owner_type',
        'owner_id',
        'status'
    ];

    protected $casts = [
        'cluster_id' => 'integer',
        'owner_id'   => 'integer',
        'parent_id'  => 'integer',
        'status'     => 'string',
    ];

    protected $appends = [
        'is_master',
        'is_override',
        'is_custom',
        'is_appended',
        'zone_id'
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function cluster()
    {
        return $this->belongsTo(Cluster::class, 'cluster_id');
    }

    public function zone()
    {
        return $this->hasOneThrough(
            Zone::class,
            Cluster::class,
            'id',
            'id',
            'cluster_id',
            'zone_id'
        );
    }

    public function locations()
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function overrides()
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function lab()
    {
        return $this->hasOneThrough(
            Lab::class,
            Location::class,
            'id',
            'id',
            'parent_id',
            'owner_id'
        )->where('locations.owner_type', 'lab')
            ->select('labs.id', 'labs.name');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeSuperAdmin($query)
    {
        return $query->where('owner_type', 'super_admin');
    }

    public function scopeForLab($query, int $labId)
    {
        return $query->where('owner_type', 'lab')
            ->where('owner_id', $labId);
    }

    public function scopeAccessible($query, $labId)
    {
        return $query->where(function ($q) use ($labId) {

            $q->where(function ($lab) use ($labId) {
                $lab->where('owner_type', 'lab')
                    ->where('owner_id', $labId);
            })

                ->orWhere(function ($master) use ($labId) {
                    $master->where('owner_type', 'super_admin')
                        ->whereDoesntHave('overrides', function ($override) use ($labId) {
                            $override->where('owner_type', 'lab')
                                ->where('owner_id', $labId);
                        });
                });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Attributes
    |--------------------------------------------------------------------------
    */

    public function getZoneIdAttribute()
    {
        return $this->cluster->zone->id ?? null;
    }

    public function getIsMasterAttribute(): bool
    {
        return $this->owner_type === 'super_admin'
            && $this->parent_id === null;
    }

    public function appendedMaster()
    {
        return $this->hasOne(Location::class, 'parent_id')
            ->where('owner_type', 'super_admin');
    }

    public function getIsOverrideAttribute(): bool
    {
        return $this->owner_type === 'lab'
            && $this->parent_id !== null;
    }

    public function getIsCustomAttribute(): bool
    {
        if ($this->owner_type !== 'lab' || $this->parent_id !== null) {
            return false;
        }

        return $this->appendedMaster === null;
    }

    public function getIsAppendedAttribute(): bool
    {
        return $this->owner_type === 'lab'
            && $this->parent_id === null
            && $this->appendedMaster !== null;
    }
}
