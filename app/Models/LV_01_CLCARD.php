<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lv_01_CLCARD extends Model
{
    use HasFactory;
    protected $table = 'Lv_{code}_01_CLCARD';
    public $timestamps = false;
    protected $primaryKey = 'LOGICALREF';
    protected $guarded = [];
}
