<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersionAmendment extends Model
{
    protected $fillable = [
        'document_version_id',
        'amendment_type',
        'amendment_number',
        'amendment_version',
        'amendment_reason',
        'amended_by',
        'amendment_date'
    ];

    protected $casts = [
        'amendment_date' => 'datetime'
    ];

    public function version()
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'amended_by');
    }
}
