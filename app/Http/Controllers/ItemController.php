<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LG_ITEMS;
use App\Models\LG_PRCLIST;
use App\Models\LG_UNITSETF;
use App\Models\LG_ITMUNITA;
use App\Models\LG_MARK;
use App\Models\LG_SPECODES;
use App\Models\LV_01_STINVTOT;
use App\Models\LG_CLCARD;



class ItemController extends Controller
{
    // retrieve data by subcategory
    public function index(Request $request)
    {
        $code = $request->header("citycode");
        $lang = $request->header("lang","ar");
        $customer = $request->header("customer");
        $subcategory = $request->header("subcategory");
        $itemsTable = (new LG_ITEMS)->getTable();
        $itemName = str_replace('{code}', $code, $itemsTable);
        $priceName = str_replace('{code}', $code, (new LG_PRCLIST)->getTable());
        $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
        $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
        $warehousetName = str_replace('{code}', $code, (new LV_01_STINVTOT)->getTable());
        $markName = str_replace('{code}', $code, (new LG_MARK)->getTable());
        $catName = str_replace('{code}', $code, (new LG_SPECODES)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $last_customer = DB::table($custName)->where('logicalref',$customer)->value('PPGROUPCODE');
        $items = DB::table("{$itemName}")
        ->leftJoin("$priceName", function($join) use ($itemName, $priceName) {
            $join->on("{$itemName}.logicalref", "=", "{$priceName}.cardref")
                ->where([ "{$priceName}.active" => 0]);
            })
            ->leftJoin("$custName", function($join) use ($custName, $priceName) {
                $join->on("{$custName}.PPGROUPCODE", "=", "{$priceName}.GRPCODE");
            })
        ->leftJoin("{$unitName}", "{$itemName}.unitsetref", "=", "{$unitName}.logicalref")
        ->leftJoin("{$markName}", "{$itemName}.markref", "=", "{$markName}.logicalref")
        ->leftJoin("{$catName} as sub", "{$itemName}.specode", "=", "sub.logicalref")
        ->leftJoin("{$weightName} as weights", function($join) use ($itemName, $weightName) {
        $join->on("{$itemName}.logicalref", "=", "weights.itemref")
            ->where("weights.linenr", "=", 1);
        })
        ->leftJoin("{$weightName} as number", function($join) use ($itemName, $weightName) {
        $join->on("{$itemName}.logicalref", "=", "number.itemref")
            ->where("number.linenr", "=", 2);
        })
        ->select("{$itemName}.logicalref as id", "{$itemName}.code as code","{$markName}.code as brand",DB::raw("CASE WHEN '{$lang}' = 'ar' THEN {$itemName}.name
        WHEN '{$lang}' = 'en' THEN {$itemName}.name3 WHEN '{$lang}' = 'tr' THEN {$itemName}.name4 ELSE {$itemName}.name END as name"),
        "{$itemName}.stgrpcode as group",'sub.logicalref as subcategory_id',DB::raw("CASE WHEN '{$lang}' = 'ar' THEN sub.definition_  WHEN '{$lang}' = 'en' THEN sub.definition2
        WHEN '{$lang}' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory_name"),"{$itemName}.stgrpcode as group","{$priceName}.price",
        "{$unitName}.logicalref as unit_id","{$unitName}.code as unit","weights.logicalref as weight_id","weights.grossweight as weight","number.convfact1 as pieces_number")
        ->where(["{$itemName}.active" => 0,"{$itemName}.specode" => $subcategory])
        // ,"{$priceName}.GRPCODE" => $last_customer
        ->groupBy("{$itemName}.logicalref","{$itemName}.code", "{$itemName}.name","{$markName}.code",'sub.logicalref',"{$itemName}.name3","{$itemName}.name4", "{$itemName}.stgrpcode",
        'sub.definition_','sub.definition2','sub.definition3',"{$itemName}.markref", "{$priceName}.price","{$unitName}.logicalref", "{$unitName}.code","weights.grossweight",
        "weights.logicalref", "number.convfact1")
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $items,
            // 'current_page' => $items->currentPage(),
            // 'per_page' => $items->perPage(),
            // 'last_page' => $items->lastPage(),
            // 'total' => $items->total(),
        ], 200);
    }

 // retrieve data by subcategory
 public function finalItem(Request $request)
 {
     $code = $request->header("citycode");
     $lang = $request->header("lang","ar");
     $type = $request->header("type");
     $brand = $request->header("brand");
     $subcategory = $request->header("subcategory");
     $itemsTable = (new LG_ITEMS)->getTable();
     $itemName = str_replace('{code}', $code, $itemsTable);
     $priceName = str_replace('{code}', $code, (new LG_PRCLIST)->getTable());
     $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
     $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
     $warehousetName = str_replace('{code}', $code, (new LV_01_STINVTOT)->getTable());
     $markName = str_replace('{code}', $code, (new LG_MARK)->getTable());
     $catName = str_replace('{code}', $code, (new LG_SPECODES)->getTable());
     $items = DB::table("{$itemName}")
     ->leftJoin("$priceName", function($join) use ($itemName, $priceName) {
     $join->on("{$itemName}.logicalref", "=", "{$priceName}.cardref")
         ->where(["{$priceName}.clientcode" => "" , "{$priceName}.active" => 0])
         ->whereRaw("{$priceName}.priority = (SELECT MAX(priority) FROM {$priceName} WHERE cardref = {$itemName}.logicalref)");
     })
     ->leftJoin("{$unitName}", "{$itemName}.unitsetref", "=", "{$unitName}.logicalref")
     ->leftJoin("{$markName}", "{$itemName}.markref", "=", "{$markName}.logicalref")
     ->leftJoin("{$catName} as sub", "{$itemName}.specode", "=", "sub.logicalref")
     ->leftJoin("{$weightName}", function($join) use ($itemName, $weightName) {
     $join->on("{$itemName}.logicalref", "=", "{$weightName}.itemref")
         ->where("{$weightName}.linenr", "=", 1);
     })
     ->leftJoin("{$warehousetName}", function($join) use ($itemName, $warehousetName) {
     $join->on("{$itemName}.logicalref", "=", "{$warehousetName}.stockref")
         ->where("{$warehousetName}.invenno", "=", 0);
     })

     ->select("{$itemName}.logicalref as id", "{$itemName}.code as code","{$markName}.code as brand",DB::raw("CASE WHEN '{$lang}' = 'ar' THEN {$itemName}.name
     WHEN '{$lang}' = 'en' THEN {$itemName}.name3 WHEN '{$lang}' = 'tr' THEN {$itemName}.name4 ELSE {$itemName}.name END as name"),
     "{$itemName}.stgrpcode as group",'sub.logicalref as subcategory_id',DB::raw("CASE WHEN '{$lang}' = 'ar' THEN sub.definition_  WHEN '{$lang}' = 'en' THEN sub.definition2
     WHEN '{$lang}' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory_name"),"{$itemName}.stgrpcode as group","{$priceName}.price",
     "{$unitName}.code as unit","{$weightName}.grossweight as weight", DB::raw("COALESCE(SUM({$warehousetName}.onhand), 0) as quantity"))
     ->where(["{$itemName}.active" => 0, "{$itemName}.classtype" => $type,"{$itemName}.markref" => $brand,"{$itemName}.specode" => $subcategory]) 
     ->groupBy("{$itemName}.logicalref","{$itemName}.code", "{$itemName}.name","{$markName}.code",'sub.logicalref',"{$itemName}.name3","{$itemName}.name4", "{$itemName}.stgrpcode",
     'sub.definition_','sub.definition2','sub.definition3',"{$itemName}.markref", "{$priceName}.price", "{$unitName}.code", "{$weightName}.grossweight")
     ->get();
     return response()->json([
         'status' => 'success',
         'message' => 'Items list',
         'data' => $items,
         // 'current_page' => $items->currentPage(),
         // 'per_page' => $items->perPage(),
         // 'last_page' => $items->lastPage(),
         // 'total' => $items->total(),
     ], 200);
 }
    public function getItemDetails(Request $request)
    {
        $code = $request->header("citycode");
        $lang = $request->header("lang");
        // $customer = $request->header("customer");
        $itemCode = $request->input('item_code');
        $itemsTable = (new LG_ITEMS)->getTable();
        $itemName = str_replace('{code}', $code, $itemsTable);
        $priceName = str_replace('{code}', $code, (new LG_PRCLIST)->getTable());
        $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
        $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
        $warehousetName = str_replace('{code}', $code, (new LV_01_STINVTOT)->getTable());
        $markName = str_replace('{code}', $code, (new LG_MARK)->getTable());
        $catName = str_replace('{code}', $code, (new LG_SPECODES)->getTable());
        // $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        // $last_customer = DB::table($custName)->where('logicalref',$customer)->value('PPGROUPCODE');
        // dd($last_customer);
        $result = DB::table("{$itemName}")
        ->leftJoin("$priceName", function($join) use ($itemName, $priceName) {
        $join->on("{$itemName}.logicalref", "=", "{$priceName}.cardref")
            ->where([ "{$priceName}.active" => 0]);
        })
        // ->leftJoin("$custName", function($join) use ($custName, $priceName) {
        //     $join->on("{$custName}.PPGROUPCODE", "=", "{$priceName}.GRPCODE");
        //     })
        ->leftJoin("{$unitName}", "{$itemName}.unitsetref", "=", "{$unitName}.logicalref")
        ->leftJoin("{$markName}", "{$itemName}.markref", "=", "{$markName}.logicalref")
        ->leftJoin("{$catName} as cat", "{$itemName}.categoryid", "=", "cat.logicalref")
        ->leftJoin("{$catName} as sub", "{$itemName}.specode", "=", "sub.logicalref")
        ->leftJoin("{$weightName}", function($join) use ($itemName, $weightName) {
        $join->on("{$itemName}.logicalref", "=", "{$weightName}.itemref")
            ->where("{$weightName}.linenr", "=", 1);
        })
        ->select("{$itemName}.logicalref as id", "{$itemName}.code as code", DB::raw("CASE WHEN '{$lang}' = 'ar' THEN {$itemName}.name WHEN '{$lang}' = 'en' THEN {$itemName}.name3 WHEN '{$lang}' = 'tr' THEN {$itemName}.name4 ELSE {$itemName}.name
        END as name"),"{$itemName}.stgrpcode as group",DB::raw("CASE WHEN '{$lang}' = 'ar' THEN cat.definition_  WHEN '{$lang}' = 'en' THEN cat.definition2 WHEN '{$lang}' = 'tr' THEN cat.definition3 ELSE cat.definition_
        END as category"), DB::raw("CASE WHEN '{$lang}' = 'ar' THEN sub.definition_  WHEN '{$lang}' = 'en' THEN sub.definition2
        WHEN '{$lang}' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory"),"{$itemName}.stgrpcode as group", "{$priceName}.price",
        "{$unitName}.code as unit", "{$weightName}.grossweight as weight")
        ->where(["{$itemName}.active" => 0,"{$itemName}.code" => $itemCode])
        // ,"{$priceName}.GRPCODE" => $last_customer
        ->groupBy("{$itemName}.logicalref","{$itemName}.code", "{$itemName}.name",'cat.definition_','cat.definition2','cat.definition3',"{$markName}.code","{$itemName}.name3",
        "{$itemName}.name4", "{$itemName}.stgrpcode","{$itemName}.categoryid",'sub.definition_','sub.definition2','sub.definition3',"{$itemName}.markref","{$itemName}.stgrpcode",
        "{$priceName}.price", "{$unitName}.code", "{$weightName}.grossweight");
        if ($request->hasHeader('type')) {
            $type = $request->header('type');
            $result->where("{$itemName}.classtype", $type);
        }
    
        if ($request->hasHeader('category')) {
            $category = $request->header('category');
            $result->where("{$itemName}.categoryid", $category);
        }
    
        if ($request->hasHeader('subcategory')) {
            $subcategory = $request->header('subcategory');
            $result->where("{$itemName}.specode", $subcategory);
        }
    
        if ($request->hasHeader('brand')) {
            $brand = $request->header('brand');
            $result->where("{$itemName}.markref", $brand);
        }
    
        $items = $result->orderby("{$itemName}.code","asc")->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $items,
        ], 200);
    }

    // retrieve items by brand
    // public function searchbybrand(Request $request)
    // {
    //     $code = $request->header("citycode");
    //     $lang = $request->header("lang","ar");
    //     $subcategory = $request->header("subcategory");
    //     $brand = $request->header("brand");
    //     $itemsTable = (new LG_ITEMS)->getTable();
    //     $itemName = str_replace('{code}', $code, $itemsTable);
    //     $priceName = str_replace('{code}', $code, (new LG_PRCLIST)->getTable());
    //     $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
    //     $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
    //     $warehousetName = str_replace('{code}', $code, (new LV_01_STINVTOT)->getTable());
    //     $markName = str_replace('{code}', $code, (new LG_MARK)->getTable());
    //     $catName = str_replace('{code}', $code, (new LG_SPECODES)->getTable());
    //     $items = DB::table("{$itemName}")
    //     ->leftJoin("$priceName", function($join) use ($itemName, $priceName) {
    //     $join->on("{$itemName}.logicalref", "=", "{$priceName}.cardref")
    //         ->where(["{$priceName}.clientcode" => "" , "{$priceName}.active" => 0])
    //         ->whereRaw("{$priceName}.priority = (SELECT MAX(priority) FROM {$priceName} WHERE cardref = {$itemName}.logicalref)");
    //     })
    //     ->leftJoin("{$unitName}", "{$itemName}.unitsetref", "=", "{$unitName}.logicalref")
    //     ->leftJoin("{$markName}", "{$itemName}.markref", "=", "{$markName}.logicalref")
    //     ->leftJoin("{$catName} as sub", "{$itemName}.specode", "=", "sub.logicalref")
    //     ->leftJoin("{$weightName}", function($join) use ($itemName, $weightName) {
    //     $join->on("{$itemName}.logicalref", "=", "{$weightName}.itemref")
    //         ->where("{$weightName}.linenr", "=", 1);
    //     })
    //     ->leftJoin("{$warehousetName}", function($join) use ($itemName, $warehousetName) {
    //     $join->on("{$itemName}.logicalref", "=", "{$warehousetName}.stockref")
    //         ->where("{$warehousetName}.invenno", "=", 0);
    //     })

    //     ->select("{$itemName}.logicalref as id", "{$itemName}.code as code","{$markName}.code as brand",DB::raw("CASE WHEN '{$lang}' = 'ar' THEN {$itemName}.name
    //     WHEN '{$lang}' = 'en' THEN {$itemName}.name3 WHEN '{$lang}' = 'tr' THEN {$itemName}.name4 ELSE {$itemName}.name END as name"),
    //     "{$itemName}.stgrpcode as group",'sub.logicalref as subcategory_id',DB::raw("CASE WHEN '{$lang}' = 'ar' THEN sub.definition_  WHEN '{$lang}' = 'en' THEN sub.definition2
    //     WHEN '{$lang}' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory_name"),"{$itemName}.stgrpcode as group","{$priceName}.price",
    //     "{$unitName}.code as unit", "{$weightName}.grossweight as weight", DB::raw("COALESCE(SUM({$warehousetName}.onhand), 0) as quantity"))
    //     ->where(["{$itemName}.active" => 0, "{$itemName}.specode" => $subcategory,"{$itemName}.markref" => $brand]) 
    //     ->groupBy("{$itemName}.logicalref","{$itemName}.code", "{$itemName}.name","{$markName}.code",'sub.logicalref',"{$itemName}.name3","{$itemName}.name4", "{$itemName}.stgrpcode",
    //     'sub.definition_','sub.definition2','sub.definition3',"{$itemName}.markref", "{$priceName}.price", "{$unitName}.code", "{$weightName}.grossweight")
    //     ->get();
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Items list',
    //         'data' => $items,
    //         // 'current_page' => $items->currentPage(),
    //         // 'per_page' => $items->perPage(),
    //         // 'last_page' => $items->lastPage(),
    //         // 'total' => $items->total(),
    //     ], 200);
    // }

    // public function showItem(Request $request)
    // {
    //     $code = $request->header('citycode');
    //     $item_code = $request->input('item_code');
    //     $tableName = str_replace('{code}', $code, (new LG_ITEMS)->getTable());
    //     $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
    //     $item = DB::table($tableName)
    //     ->join("{$unitName}","{$tableName}.unitsetref", '=', "{$unitName}.logicalref")
    //     ->select("{$tableName}.logicalref AS id","{$tableName}.code","{$tableName}.name as arabic_name",
    //     "{$tableName}.stgrpcode as group","{$tableName}.specode as brand","{$tableName}.name3 as english_name","{$tableName}.name4 as turkish_name",
    //     "{$unitName}.code AS arabic_unit","{$unitName}.name AS turkish_unit")
    //     ->where("{$tableName}.code", $item_code)
    //     ->get();
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Item retrived successfully',
    //         'data' => $item,
    //     ], 200);
    // }
}
