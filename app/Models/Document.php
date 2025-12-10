<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\DocumentEditor;

class Document extends Model
{
    protected $fillable = [
        'labName',
        'location',
        'department',
        'header',
        'footer',
        'amendmentNo',
        'amendmentDate',
        'approvedBy',
        'category',
        'copyNo',
        'documentName',
        'documentNo',
        'durationValue',
        'durationUnit',
        'effectiveDate',
        'frequency',
        'issueDate',
        'issuedBy',
        'issuedNo',
        'preparedBy',
        'preparedByDate',
        'quantityPrepared',
        'status',
        'time',
    ];

    protected $casts = [
        'department' => 'array',
        'amendmentDate' => 'datetime',
        'date' => 'datetime',
        'effectiveDate' => 'datetime',
        'issueDate' => 'datetime',
        'preparedByDate' => 'datetime',
        'time' => 'datetime',
    ];

    // Relation to DocumentEditor
    // Document.php
    public function editor()
    {
        return $this->hasOne(DocumentEditor::class);
    }
    public function dataEntrySchedule()
    {
        return $this->hasOne(DocumentEditor::class);
    }


    // Accessor to return nested editor structure
    public function getEditorAttribute()
    {
        $editor = $this->editor()->first();

        if (!$editor) {
            return null;
        }

        return [
            'id' => $editor->id,
            'documentId' => $editor->document_id,
            'document' => $editor->document ?? [
                'html' => '',
                'css' => '',
                'js' => '',
            ],
        ];
    }
    

}
