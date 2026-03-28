<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabTaskAssign extends Model
{
    protected $fillable = [
        'lab_id',
        'clause_id',
        'document_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }
}
