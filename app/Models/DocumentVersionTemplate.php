<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersionTemplate extends Model
{


    protected $fillable = [
        'document_version_id',
        'template_id',
        'template_version_id',
        'type',
    ];

    public function version()
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function templateVersion()
    {
        return $this->belongsTo(TemplateVersion::class, 'template_version_id');
    }

}
