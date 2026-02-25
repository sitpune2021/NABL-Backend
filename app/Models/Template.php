<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TemplateVersion;
use App\Models\TemplateChangeHistory;

class Template extends Model
{
    use softDeletes;
    
    protected $fillable = [
        'parent_id',
        'name',
        'type',
        'status',
        'owner_type',
        'owner_id',
        'appended_from_lab_id',
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

    public function versions() {
        return $this->hasMany(TemplateVersion::class);
    }

    public function currentVersion() {
        return $this->hasOne(TemplateVersion::class)->where('is_current', true);
    }

    public function histories() {
        return $this->hasMany(TemplateChangeHistory::class);
    }
    
    // 🔗 Lab override → Master department
    public function parent()
    {
        return $this->belongsTo(Template::class, 'parent_id');
    }

    // 🔁 Master department → Lab overrides
    public function overrides()
    {
        return $this->hasMany(Template::class, 'parent_id');
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
     * Is lab override of a master department
     */
    public function getIsOverrideAttribute(): bool
    {
        return $this->owner_type === 'lab' && $this->parent_id !== null;
    }

    /**
     * Is lab-only custom department
     */
    public function getIsCustomAttribute(): bool
    {
        return $this->owner_type === 'lab' && $this->parent_id === null;
    }

    
    public function scopeAccessible($query, $labId)
    {
        return $query->where(function ($q) use ($labId) {

            // 1️⃣ All lab templates (custom + overrides)
            $q->where(function ($lab) use ($labId) {
                $lab->where('owner_type', 'lab')
                    ->where('owner_id', $labId);
            })

            // 2️⃣ Master templates NOT overridden by this lab
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