<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\MarkImport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use App\Models\LG_MARK;

class MarkController extends Controller
{
    public function brands(Request $request){
        $code = $request->header("citycode");
        $type = $request->header("type");
        $markName = str_replace('{code}', $code, (new LG_MARK)->getTable());
        $brand = DB::table("{$markName}")
        ->select("{$markName}.logicalref as id","{$markName}.code as name","{$markName}.descr as image")
        ->where("{$markName}.specode",$type)
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Brands list',
            'data' => $brand,
        ], 200);
     }
}
