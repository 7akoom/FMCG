<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LG_PAYLINES extends Model
{
    use HasFactory;
    protected $table = 'LG_{code}_PAYLINES';
    public $timestamps = false;
    protected $primaryKey = 'LOGICALREF';
    protected $guarded = [];
}
