<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClauseDocumentLink extends Model
{
    protected $fillable = [
        'standard_id',
        'clause_id',
        'document_id',
        'document_version_id',
        'category_id'
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

    // public function clause()
    // {
    //     return $this->belongsTo(Clause::class, 'clause_id', 'id');
    // }
}
