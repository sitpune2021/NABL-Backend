<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Template;
use App\Models\TemplateVersion;

class TemplateChangeHistory extends Model
{
    protected $fillable = ['template_id','template_version_id','field_name','old_value','new_value','change_context','changed_by','message'];

    public function template() {
        return $this->belongsTo(Template::class);
    }

    public function version() {
        return $this->belongsTo(TemplateVersion::class,'template_version_id');
    }
}
