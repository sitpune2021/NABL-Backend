<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersionWorkflowLog extends Model
{


    protected $fillable = [
        'document_version_id',
        'step_type',
        'step_status',
        'performed_by',
        'comments',
        'created_at'
    ];

    public function version()
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }


}
