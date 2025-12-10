<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Template;
use App\Models\TemplateChangeHistory;

class TemplateVersion extends Model
{
    use SoftDeletes;

    protected $fillable = ['template_id','major','minor','css','html','json_data','is_current','change_type','message', 'changed_by'];

    protected $casts = [
        'json_data' => 'array'
    ];

    public function template() {
        return $this->belongsTo(Template::class);
    }

    public function histories() {
        return $this->hasMany(TemplateChangeHistory::class);
    }
}
