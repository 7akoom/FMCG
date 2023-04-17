<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use App\Models\LG_ITEMS;
use App\Models\LG_PRCLIST;
use App\Models\LG_CLCARD;
use App\Models\LG_PAYLINES;
use App\Models\LG_PAYPLANS;
use App\Models\LG_UNITSETF;
use App\Models\LG_ITMUNITA;
use App\Models\LG_01_CLRNUMS;
use App\Models\LG_SLSCLREL;
use App\Models\LV_01_STINVTOT;

class WareHouseController extends Controller
{
    public function mainWHouse(Request $request)
    {
        $code = $request->header("citycode");
        $group = $request->header("stgrpcode");
        $codesArray = explode(",", $group);
        $itemsTable = (new LG_ITEMS)->getTable();
        $itemName = str_replace('{code}', $code, $itemsTable);
        $priceName = str_replace('{code}', $code, (new LG_PRCLIST)->getTable());
        $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
        $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
        $warehousetName = str_replace('{code}', $code, (new LV_01_STINVTOT)->getTable());
        $result = DB::table("{$itemName}")
        ->leftJoin("$priceName", function($join) use ($itemName, $priceName) {
        $join->on("{$itemName}.logicalref", "=", "{$priceName}.cardref")
            ->where("{$priceName}.clientcode", "=", "")
            ->whereRaw("{$priceName}.priority = (SELECT MAX(priority) FROM {$priceName} WHERE cardref = {$itemName}.logicalref)");
        })
        ->leftJoin("{$unitName}", "{$itemName}.unitsetref", "=", "{$unitName}.logicalref")
        ->leftJoin("{$weightName}", function($join) use ($itemName, $weightName) {
        $join->on("{$itemName}.logicalref", "=", "{$weightName}.itemref")
            ->where("{$weightName}.linenr", "=", 1);
        })
        ->leftJoin("{$warehousetName}", function($join) use ($itemName, $warehousetName) {
        $join->on("{$itemName}.logicalref", "=", "{$warehousetName}.stockref")
            ->where("{$warehousetName}.invenno", "=", 0);
        })
        ->select("{$itemName}.logicalref as id", "{$itemName}.code as code", "{$itemName}.name as arabic_name", 
        "{$itemName}.name3 as english_name","{$itemName}.name4 as turkish_name","{$itemName}.stgrpcode as group", "{$priceName}.price",
        "{$unitName}.code as unit", "{$weightName}.grossweight as weight", DB::raw("COALESCE(SUM({$warehousetName}.onhand), 0) as quantity"))
        ->where("{$itemName}.active",'=',  0) 
        ->whereIn("{$itemName}.stgrpcode", $codesArray)            
        ->groupBy("{$itemName}.logicalref","{$itemName}.code", "{$itemName}.name","{$itemName}.name3","{$itemName}.name4",
        "{$itemName}.stgrpcode", "{$priceName}.price", "{$unitName}.code", "{$weightName}.grossweight")
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $result,
        ], 200);
    }  

    public function cashvanWHouse(Request $request)
    {
        $code = $request->header("citycode");
        $itemsTable = (new LG_ITEMS)->getTable();
        $itemName = str_replace('{code}', $code, $itemsTable);
        $priceName = str_replace('{code}', $code, (new LG_PRCLIST)->getTable());
        $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
        $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
        $warehousetName = str_replace('{code}', $code, (new LV_01_STINVTOT)->getTable());
        $result = DB::table("{$itemName}")
        ->leftJoin("$priceName", function($join) use ($itemName, $priceName) {
            $join->on("{$itemName}.logicalref", "=", "{$priceName}.cardref")
                ->where("{$priceName}.clientcode", "=", "")
                ->whereRaw("{$priceName}.priority = (SELECT MAX(priority) FROM {$priceName} WHERE cardref = {$itemName}.logicalref)");
        })
        ->leftJoin("{$unitName}", "{$itemName}.unitsetref", "=", "{$unitName}.logicalref")
        ->leftJoin("{$weightName}", function($join) use ($itemName, $weightName) {
            $join->on("{$itemName}.logicalref", "=", "{$weightName}.itemref")
                ->where("{$weightName}.linenr", "=", 1);
        })
        ->leftJoin("{$warehousetName}", function($join) use ($itemName, $warehousetName) {
            $join->on("{$itemName}.logicalref", "=", "{$warehousetName}.stockref")
                ->where("{$warehousetName}.invenno", "=", 10);
        })
        ->select("{$itemName}.logicalref as id", "{$itemName}.code as code", "{$itemName}.name as arabic_name", 
            "{$itemName}.name3 as english_name","{$itemName}.name4 as turkish_name","{$itemName}.stgrpcode as group", "{$priceName}.price",
            "{$unitName}.code as unit", "{$weightName}.grossweight as weight", DB::raw("COALESCE(SUM({$warehousetName}.onhand), 0) as quantity"))
        ->where("{$itemName}.active", "=", 0)
        ->groupBy("{$itemName}.logicalref","{$itemName}.code", "{$itemName}.name","{$itemName}.name3","{$itemName}.name4", "{$itemName}.stgrpcode", "{$priceName}.price", "{$unitName}.code", "{$weightName}.grossweight")
        ->havingRaw("SUM({$warehousetName}.onhand) > 0")
        ->orderby("{$itemName}.code",'desc')
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $result,
        ], 200);
    }

    public function wastageWHouse(Request $request)
    {
        $code = $request->header("citycode");
        $itemsTable = (new LG_ITEMS)->getTable();
        $itemName = str_replace('{code}', $code, $itemsTable);
        $priceName = str_replace('{code}', $code, (new LG_PRCLIST)->getTable());
        $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
        $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
        $warehousetName = str_replace('{code}', $code, (new LV_01_STINVTOT)->getTable());
        $result = DB::table("{$itemName}")
        ->leftJoin("$priceName", function($join) use ($itemName, $priceName) {
            $join->on("{$itemName}.logicalref", "=", "{$priceName}.cardref")
                ->where("{$priceName}.clientcode", "=", "")
                ->whereRaw("{$priceName}.priority = (SELECT MAX(priority) FROM {$priceName} WHERE cardref = {$itemName}.logicalref)");
        })
        ->leftJoin("{$unitName}", "{$itemName}.unitsetref", "=", "{$unitName}.logicalref")
        ->leftJoin("{$weightName}", function($join) use ($itemName, $weightName) {
            $join->on("{$itemName}.logicalref", "=", "{$weightName}.itemref")
                ->where("{$weightName}.linenr", "=", 1);
        })
        ->leftJoin("{$warehousetName}", function($join) use ($itemName, $warehousetName) {
            $join->on("{$itemName}.logicalref", "=", "{$warehousetName}.stockref")
                ->where("{$warehousetName}.invenno", "=", 9);
        })
        ->select("{$itemName}.logicalref as id", "{$itemName}.code as code", "{$itemName}.name as arabic_name", 
            "{$itemName}.name3 as english_name","{$itemName}.name4 as turkish_name","{$itemName}.stgrpcode as group", "{$priceName}.price",
            "{$unitName}.code as unit", "{$weightName}.grossweight as weight", DB::raw("COALESCE(SUM({$warehousetName}.onhand), 0) as quantity"))
        ->where("{$itemName}.active", "=", 0)
        ->groupBy("{$itemName}.logicalref","{$itemName}.code", "{$itemName}.name","{$itemName}.name3","{$itemName}.name4", "{$itemName}.stgrpcode", "{$priceName}.price", "{$unitName}.code", "{$weightName}.grossweight")
        ->havingRaw("SUM({$warehousetName}.onhand) > 0")
        ->orderby("{$itemName}.code",'desc')
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $result,
        ], 200);
    }

}

