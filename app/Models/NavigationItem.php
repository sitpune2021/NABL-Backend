<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NavigationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'path', 'title', 'translate_key', 'icon', 'type', 'for',
        'is_external_link', 'authority', 'description', 'description_key',
        'parent_id', 'layout', 'show_column_title', 'columns', 'order'
    ];

    protected $casts = [
        'authority' => 'array',
        'is_external_link' => 'boolean',
        'show_column_title' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Unified children relation
     * Decides lab/master dynamically
     */
    public function children()
    {
        $isLabUser = request()->attributes->get('isLabUser', false);
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('order')
            ->when(
                $isLabUser,
                fn ($q) => $q->forLab(),
                fn ($q) => $q->forMaster()
            )
            ->with('children');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function scopeForMaster($query)
    {
        return $query->whereIn('for', ['both', 'master']);
    }

    public function scopeForLab($query)
    {
        return $query->whereIn('for', ['both', 'lab']);
    }
}
