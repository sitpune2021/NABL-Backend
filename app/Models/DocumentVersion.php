<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentVersion extends Model
{
    use SoftDeletes; 

    protected $fillable = [
        'document_id',
        'major_version',
        'minor_version',
        'is_current',
        'full_version',
        'copy_no',
        'quantity_prepared',
        'workflow_state',
        'version_status',
        'review_frequency',
        'notification_unit',
        'notification_value',
        'effective_date',
        'editor_schema',
        'form_fields',
        'schedule'
    ];

    protected $casts = [
        'editor_schema' => 'array',
        'form_fields' => 'array',
        'schedule' => 'array',
        'effective_date' => 'datetime',
        'is_current' => 'boolean'
    ];

    // Relationships
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function amendments()
    {
        return $this->hasMany(DocumentVersionAmendment::class);
    }

    public function labs()
    {
        return $this->hasMany(DocumentVersionLabAssignment::class);
    }

    public function workflowLogs()
    {
        return $this->hasMany(DocumentVersionWorkflowLog::class);
    }

    public function history()
    {
        return $this->hasMany(DocumentHistory::class);
    }

    public function templates()
    {
        return $this->hasMany(DocumentVersionTemplate::class);
    }
}


