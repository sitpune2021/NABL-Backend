<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabClauseDocument extends Model
{
        protected $fillable = [
        'lab_id',
        'standard_id',
        'clause_id',
        'document_id',
    ];

    // Relationships
    public function lab() {
        return $this->belongsTo(Lab::class);
    }

    public function standard() {
        return $this->belongsTo(Standard::class);
    }

    public function clause() {
        return $this->belongsTo(Clause::class);
    }

    public function document() {
        return $this->belongsTo(Document::class);
    }
}
