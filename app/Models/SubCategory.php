<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cat_id',
        'parent_id',
        'name',
        'identifier',
        'owner_type',
        'owner_id',
        'status'
    ];

    protected $casts = [
        'cat_id'   => 'integer',
        'parent_id'=> 'integer',
        'owner_id' => 'integer',
        'status' => 'string'
    ];

    protected $appends = [
        'is_master',
        'is_override',
        'is_custom',
        'is_appended',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function category()
    {
        return $this->belongsTo(Category::class, 'cat_id');
    }

    // Parent subcategory (lab → master or master → lab)
    public function parent()
    {
        return $this->belongsTo(SubCategory::class, 'parent_id');
    }

    // Child subcategories (overrides or appended copies)
    public function overrides()
    {
        return $this->hasMany(SubCategory::class, 'parent_id');
    }
    public function lab()
    {
        return $this->hasOneThrough(
            Lab::class,      // Final Model
            SubCategory::class, // Intermediate (Lab Category)

            'id',            // Intermediate PK (categories.id)
            'id',            // Final PK (labs.id)

            'parent_id',     // FK on SuperAdmin Category
            'owner_id'       // FK on Lab Category
        )->where('sub_categories.owner_type', 'lab')  // 🔥 VERY IMPORTANT
        ->select('labs.id', 'labs.name');
    }

    // Master copy of lab subcategory
    public function appendedMaster()
    {
        return $this->hasOne(SubCategory::class, 'parent_id')
            ->where('owner_type', 'super_admin');
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

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getIsMasterAttribute(): bool
    {
        return $this->owner_type === 'super_admin'
            && $this->parent_id === null;
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

    /*
    |--------------------------------------------------------------------------
    | Accessible Scope (Multi-Tenant Safe)
    |--------------------------------------------------------------------------
    */

    public function scopeAccessible($query, $labId)
    {
        return $query->where(function ($q) use ($labId) {

            // Lab-owned (custom + overrides)
            $q->where(function ($lab) use ($labId) {
                $lab->where('owner_type', 'lab')
                    ->where('owner_id', $labId);
            })

            // Master not overridden by this lab
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
