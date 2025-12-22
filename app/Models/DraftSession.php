<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DraftSession extends Model
{
    use HasFactory;

    protected $fillable = ['uuid','standard_id','section_number','status','created_by'];

    public function standard() {
        return $this->belongsTo(Standard::class);
    }

    public function creator() {
        return $this->belongsTo(User::class,'created_by');
    }
}
