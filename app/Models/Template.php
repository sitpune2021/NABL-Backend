<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TemplateVersion;
use App\Models\TemplateChangeHistory;

class Template extends Model
{
    use softDeletes;
    
    protected $fillable = ['name','type','status'];

    public function versions() {
        return $this->hasMany(TemplateVersion::class);
    }

    public function currentVersion() {
        return $this->hasOne(TemplateVersion::class)->where('is_current', true);
    }

    public function histories() {
        return $this->hasMany(TemplateChangeHistory::class);
    }
}