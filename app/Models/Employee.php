<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $table='L_CAPIUSER';
    protected $guarded = [];
    protected $primaryKey = 'LOGICALREF';
    public $timestamps = false;
}
