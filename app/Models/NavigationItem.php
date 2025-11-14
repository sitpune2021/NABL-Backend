<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NavigationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'path', 'title', 'translate_key', 'icon', 'type',
        'is_external_link', 'authority', 'description', 'description_key',
        'parent_id', 'layout', 'show_column_title', 'columns', 'order'
    ];

    protected $casts = [
        'authority' => 'array',
        'is_external_link' => 'boolean',
        'show_column_title' => 'boolean',
    ];

    public function children()
    {
        return $this->hasMany(NavigationItem::class, 'parent_id')->orderBy('order')->with('children');
    }

    public function parent()
    {
        return $this->belongsTo(NavigationItem::class, 'parent_id');
    }
}
