<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LG_ITEMS;
use App\Models\LG_PRCLIST;
use App\Models\LG_CLCARD;
use App\Models\LG_PAYLINES;
use App\Models\LG_PAYPLANS;
use App\Models\LG_UNITSETF;
use App\Models\LG_ITMUNITA;
use App\Models\LG_01_CLRNUMS;
use App\Models\LG_01_INVOICE;
use App\Models\LG_SLSCLREL;
use App\Models\LV_01_CLCARD;
use App\Models\LG_01_STLINE;
use App\Imports\ItemDefImport;
use App\Imports\ItemImport;
use Maatwebsite\Excel\Facades\Excel;



class TestController extends Controller
{
  
}