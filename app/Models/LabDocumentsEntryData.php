<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabDocumentsEntryData extends Model
{
     protected $fillable = [
        'lab_id',
        'user_id',
        'document_id',
        'document_version_id',
        'fields_entry'
    ];

    protected $casts = [
        'fields_entry' => 'array', // optional, Laravel will cast JSON to array automatically
    ];


     public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function documentVersion()
    {
        return $this->belongsTo(DocumentVersion::class);
    }
}


