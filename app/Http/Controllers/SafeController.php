<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use App\Models\LG_KSCARD;
use App\Models\LG_01_KSLINES;

class SafeController extends Controller
{
    //retrieve all safes with balances
    public function index(Request $request)
    {
        $code = $request->header("citycode");
        $safeName = str_replace("{code}", $code, (new LG_KSCARD)->getTable());
        $safeLine = str_replace("{code}", $code, (new LG_01_KSLINES)->getTable());
        $safe = DB::table("{$safeName}")
        ->leftjoin("{$safeLine}", "{$safeName}.logicalref", "=", "{$safeLine}.cardref")
        ->select("{$safeName}.code", "{$safeName}.name", "{$safeName}.explain", 
            DB::raw("SUM(CASE WHEN {$safeLine}.sign = 0 THEN {$safeLine}.amount ELSE 0 END) AS total_collection"), 
            DB::raw("SUM(CASE WHEN {$safeLine}.sign = 1 THEN {$safeLine}.amount ELSE 0 END) AS total_deposit"))
        ->where("{$safeName}.active",0)
        ->groupBy("{$safeName}.code", "{$safeName}.name", "{$safeName}.explain")
        ->orderBy("{$safeName}.code","asc")
        ->get();
        return response()->json([
            "status" => "success",
            "message" => "Safes list",
            "data" => $safe,
        ]);
    }

}
