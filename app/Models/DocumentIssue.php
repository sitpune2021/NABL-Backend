<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentIssue extends Model
{


    protected $fillable = [
        'document_version_id',
        'issued_by',
        'issue_no',
        'issued_at',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'issued_at' => 'datetime'
    ];

    public function version()
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

}
