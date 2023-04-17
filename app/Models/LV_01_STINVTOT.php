<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LV_01_STINVTOT extends Model
{
    use HasFactory;
    protected $table = 'LV_{code}_01_STINVTOT';
    public $timestamps = false;
    protected $primaryKey = 'LOGICALREF';
    protected $guarded = [];
}
