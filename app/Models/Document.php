<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'category_id',
        'status',
        'mode',
        'owner_type',
        'owner_id'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // Relationships
    public function versions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function currentVersion()
    {
        return $this->hasOne(DocumentVersion::class)->where('is_current', true);
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'document_departments');
    }

    public function history()
    {
        return $this->hasMany(DocumentHistory::class);
    }

      public function clauseLinks()
    {
        return $this->hasMany(ClauseDocumentLink::class, 'document_id', 'id');
    }

}
