<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClauseVersion extends Model
{
        use HasFactory;

    protected $fillable = [
        'clause_id','standard_id','title','message',
        'numbering_type','numbering_value','version_major','version_minor','change_type'
    ];

    public function clause() {
        return $this->belongsTo(Clause::class);
    }

    public function standard() {
        return $this->belongsTo(Standard::class);
    }
}
