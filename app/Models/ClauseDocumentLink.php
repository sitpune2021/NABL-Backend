<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClauseDocumentLink extends Model
{
    protected $fillable = [
        'parent_id',
        'standard_id',
        'clause_id',
        'document_id',
        'document_version_id',
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

    public function standard()
    {
        return $this->belongsTo(Standard::class);
    }

    public function clause()
    {
        return $this->belongsTo(Clause::class);
    }

    public function documentVersion()
    {
        return $this->belongsTo(DocumentVersion::class);
    }


    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

        // 🔗 Lab override → Master ClauseDocumentLink
    public function parent()
    {
        return $this->belongsTo(ClauseDocumentLink::class, 'parent_id');
    }

    // 🔁 Master ClauseDocumentLink → Lab overrides
    public function overrides()
    {
        return $this->hasMany(ClauseDocumentLink::class, 'parent_id');
    }

    public function lab()
    {
        return $this->hasOneThrough(
            Lab::class,      // Final Model
            ClauseDocumentLink::class, // Intermediate (Lab ClauseDocumentLink)

            'id',            // Intermediate PK (clause_document_links.id)
            'id',            // Final PK (labs.id)

            'parent_id',     // FK on SuperAdmin ClauseDocumentLink
            'owner_id'       // FK on Lab ClauseDocumentLink
        )->where('clause_document_links.owner_type', 'lab')  // 🔥 VERY IMPORTANT
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
        return $this->hasOne(ClauseDocumentLink::class, 'parent_id')
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

    private function applyOwnerScope($query, $ownerType, $ownerId)
    {
        return $ownerType === 'super_admin'
            ? $query->superAdmin()
            : $query->forLab($ownerId);
    }

}
