<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Clause extends Model
{
        use HasFactory;

    protected $fillable = [
        'standard_id','parent_id','title','message','note','note_message', 'is_child',
        'numbering_type','numbering_value','sort_order'
    ];

    protected $appends = ['children_count'];

    public function standard() {
        return $this->belongsTo(Standard::class);
    }

    public function parent() {
        return $this->belongsTo(Clause::class,'parent_id');
    }

    public function versions() {
        return $this->hasMany(ClauseVersion::class);
    }

    public function children()
    {
        return $this->hasMany(Clause::class, 'parent_id')->orderBy('sort_order')->with(['documents.currentVersion', 'children']);
    }

    public function documents()
    {
        return $this->hasManyThrough(
            Document::class,
            ClauseDocumentLink::class,
            'clause_id', // Foreign key on ClauseDocumentLink
            'id',        // Foreign key on Document
            'id',        // Local key on Clause
            'document_id' // Local key on ClauseDocumentLink
        );
    }

    public function getChildrenCountAttribute()
    {
        return $this->children()->count(); // Only direct children
    }

    public function recursiveChildren()
    {
        return $this->children()->with('recursiveChildren');
    }

    public function documentLinks()
    {
        return $this->hasMany(ClauseDocumentLink::class, 'clause_id');
    }

}
