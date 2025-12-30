<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabDocuments extends Model
{
    protected $fillable = ['document_version_id','user_id','lab_id'];
}
