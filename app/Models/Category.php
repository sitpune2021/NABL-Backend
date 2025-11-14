<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use softDeletes;
    
    protected $fillable = [
        'name',
        'identifier',
    ];

    public function subCategories()
    {
        return $this->hasMany(SubCategory::class, 'cat_id');
    }


}
