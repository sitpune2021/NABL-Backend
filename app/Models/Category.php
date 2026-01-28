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
     * Is lab override of a master category
     */
    public function getIsOverrideAttribute(): bool
    {
        return $this->owner_type === 'lab' && $this->parent_id !== null;
    }

    /**
     * Is lab-only custom category
     */
    public function getIsCustomAttribute(): bool
    {
        return $this->owner_type === 'lab' && $this->parent_id === null;
    }
}
