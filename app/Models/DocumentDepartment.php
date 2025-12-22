<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentDepartment extends Model
{
  

    protected $fillable = [
        'document_id',
        'department_id',
    ];

}
