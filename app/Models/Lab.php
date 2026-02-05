<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lab extends Model
{
     use SoftDeletes;

    protected $fillable = ['name','lab_type','lab_code','address', 'loaction_count', 'user_count'];

    public function contacts()
    {
        return $this->morphMany(Contact::class,'contactable');
    }

    public function emails()
    {
        return $this->contacts()->where('type', 'email');
    }

    public function phones()
    {
        return $this->contacts()->where('type', 'phone');
    }

    public function location()
    {
        return $this->hasMany(LabLocation::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'lab_users');
    }

    public function labClauseDocuments()
    {
        return $this->hasMany(LabClauseDocument::class, 'lab_id');
    }

}
