<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'identifier',
        'owner_type',
        'owner_id',
    ];

    protected $casts = [
        'owner_id'  => 'integer',
        'parent_id' => 'integer',
    ];

    protected $appends = [
        'is_master',
        'is_override',
        'is_custom',
        'is_appended', // new
    ];


    // Category → SubCategories
    public function subCategories()
    {
        return $this->hasMany(SubCategory::class, 'cat_id');
    }

    // 🔗 Lab override → Master category
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // 🔁 Master category → Lab overrides
    public function overrides()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function lab()
    {
        return $this->hasOneThrough(
            Lab::class,      // Final Model
            Category::class, // Intermediate (Lab Category)

            'id',            // Intermediate PK (categories.id)
            'id',            // Final PK (labs.id)

            'parent_id',     // FK on SuperAdmin Category
            'owner_id'       // FK on Lab Category
        )->where('categories.owner_type', 'lab')  // 🔥 VERY IMPORTANT
        ->select('labs.id', 'labs.name');
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
        return $this->owner_type === 'super_admin'
            && $this->parent_id === null;
    }

    public function appendedMaster()
    {
        return $this->hasOne(Category::class, 'parent_id')
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

}
