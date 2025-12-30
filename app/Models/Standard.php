<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Standard extends Model
{
       use HasFactory;

    protected $fillable = [
        'uuid','name','description','version_major','version_minor',
        'changes_type','status','is_current','created_by'
    ];

    public function drafts() {
        return $this->hasMany(DraftSession::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function clauses()
    {
        // Only top-level clauses (parent_id = null)
        return $this->hasMany(Clause::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function clauseDocumentLinks()
    {
        return $this->hasMany(ClauseDocumentLink::class, 'standard_id', 'id');
    }

    // Scope to get current standards
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}
