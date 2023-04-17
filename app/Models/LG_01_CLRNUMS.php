<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LG_01_CLRNUMS extends Model
{
    use HasFactory;
    protected $table = 'LG_{code}_01_CLRNUMS';
    public $timestamps = false;
    protected $primaryKey = 'LOGICALREF';
    protected $guarded = [];
}
