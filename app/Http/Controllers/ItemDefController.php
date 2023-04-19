<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\ItemDefImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\LG_SPECODES;
use DB;

class ItemDefController extends Controller
{
    public function categories(Request $request)
    {
        $code = $request->header("citycode");
        $lang = $request->header("lang");
        $type = $request->header("type");
        $catName = str_replace('{code}', $code, (new LG_SPECODES)->getTable());
        $category = DB::table("{$catName}")
       ->select("{$catName}.logicalref as id",DB::raw("CASE WHEN '{$lang}' = 'ar' THEN $catName.definition_  WHEN '{$lang}' = 'en' THEN $catName.definition2 
       WHEN '{$lang}' = 'tr' THEN $catName.definition3 ELSE $catName.definition_ END as name"))
       ->where("{$catName}.codetype",$type)
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'categories list',
            'data' => $category,
        ], 200);
    }
    public function subcategories(Request $request)
    {
        $code = $request->header("citycode");
        $lang = $request->header("lang");
        $category = $request->header("category");
        $catName = str_replace('{code}', $code, (new LG_SPECODES)->getTable());
        $category = DB::table("{$catName}")
       ->select("{$catName}.logicalref as id",DB::raw("CASE WHEN '{$lang}' = 'ar' THEN $catName.definition_  WHEN '{$lang}' = 'en' THEN $catName.definition2 
       WHEN '{$lang}' = 'tr' THEN $catName.definition3 ELSE $catName.definition_ END as name"))
       ->where("{$catName}.codetype",$category)
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'sub categories list',
            'data' => $category,
        ], 200);
    }
    // public function test (Request $request)
    // {
    //     $category = $request->header("category");
    //     $test = DB::table('lg_325_specodes as cat')
    //     ->join('lg_325_specodes as sub','cat.logicalref','=','sub.logicalref')
    //     ->select('cat.logicalref','cat.definition_','sub.logicalref','sub.definition_')
    //     ->where('cat.logicalref',$category)
    //     ->orwhere('sub.codetype' , $category)
    //     ->get();
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'categories list',
    //         'data' => $test,
    //     ], 200);
    // }
}
