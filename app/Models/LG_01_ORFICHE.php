<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LG_01_ORFICHE extends Model
{
    use HasFactory;
    protected $table = 'LG_{code}_01_ORFICHE';
    public $timestamps = false;
    protected $primaryKey = 'LOGICALREF';
    protected $guarded = [];
}
