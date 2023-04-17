<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesMan extends Model
{
    use HasFactory;

    protected $table='LG_SLSMAN';
    protected $guarded = [];
    protected $primaryKey = 'LOGICALREF';
    public $timestamps = false;
}
