<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubCategory extends Model
{
    use softDeletes;

     protected $fillable = [
        'cat_id',
        'name',
        'identifier',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'cat_id');
    }
}
