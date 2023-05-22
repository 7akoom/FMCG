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
use App\Models\LG_MARK;
use App\Models\LG_SPECODES;

class WareHouseController extends Controller
{
    // retrieve merkez items
    public function mainWHouse(Request $request)
    {
        $code = $request->header("citycode");
        $lang = $request->header("lang");
        $itemsTable = (new LG_ITEMS)->getTable();
        $itemName = str_replace('{code}', $code, $itemsTable);
        $priceName = str_replace('{code}', $code, (new LG_PRCLIST)->getTable());
        $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
        $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
        $warehousetName = str_replace('{code}', $code, (new LV_01_STINVTOT)->getTable());
        $markName = str_replace('{code}', $code, (new LG_MARK)->getTable());
        $catName = str_replace('{code}', $code, (new LG_SPECODES)->getTable());
        $result = DB::table("{$itemName}")
        ->leftJoin("$priceName", function($join) use ($itemName, $priceName) {
        $join->on("{$itemName}.logicalref", "=", "{$priceName}.cardref")
            ->where(["{$priceName}.clientcode" => "" , "{$priceName}.active" => 0])
            ->whereRaw("{$priceName}.priority = (SELECT MAX(priority) FROM {$priceName} WHERE cardref = {$itemName}.logicalref)");
        })
        ->leftJoin("{$unitName}", "{$itemName}.unitsetref", "=", "{$unitName}.logicalref")
        ->leftJoin("{$markName}", "{$itemName}.markref", "=", "{$markName}.logicalref")
        ->leftJoin("{$catName} as cat", "{$itemName}.categoryid", "=", "cat.logicalref")
        ->leftJoin("{$catName} as sub", "{$itemName}.specode", "=", "sub.logicalref")
        ->leftJoin("{$weightName}", function($join) use ($itemName, $weightName) {
        $join->on("{$itemName}.logicalref", "=", "{$weightName}.itemref")
            ->where("{$weightName}.linenr", "=", 1);
        })
        ->leftJoin("{$warehousetName}", function($join) use ($itemName, $warehousetName) {
        $join->on("{$itemName}.logicalref", "=", "{$warehousetName}.stockref")
            ->where("{$warehousetName}.invenno", "=", 0);
        })
        ->select("{$itemName}.logicalref as id", "{$itemName}.code as code", DB::raw("CASE WHEN '{$lang}' = 'ar' THEN {$itemName}.name WHEN '{$lang}' = 'en' THEN {$itemName}.name3 WHEN '{$lang}' = 'tr' THEN {$itemName}.name4 ELSE {$itemName}.name
        END as name"),
        "{$itemName}.stgrpcode as group","{$priceName}.price",
        DB::raw("CASE WHEN '{$lang}' = 'ar' THEN cat.definition_  WHEN '{$lang}' = 'en' THEN cat.definition2 WHEN '{$lang}' = 'tr' THEN cat.definition3 ELSE cat.definition_
        END as category"), DB::raw("CASE WHEN '{$lang}' = 'ar' THEN sub.definition_  WHEN '{$lang}' = 'en' THEN sub.definition2
        WHEN '{$lang}' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory"),
        "{$unitName}.code as unit", "{$weightName}.grossweight as weight", DB::raw("COALESCE(SUM({$warehousetName}.onhand), 0) as quantity"))
        ->where(["{$itemName}.active" => 0])
        ->groupBy("{$itemName}.logicalref","{$itemName}.code", "{$itemName}.name",'cat.definition_','cat.definition2','cat.definition3',"{$markName}.code","{$itemName}.name3","{$itemName}.name4", "{$itemName}.stgrpcode","{$itemName}.categoryid",
        'sub.definition_','sub.definition2','sub.definition3',"{$itemName}.markref",
        "{$itemName}.stgrpcode", "{$priceName}.price", "{$unitName}.code", "{$weightName}.grossweight");
        
        if ($request->hasHeader('type')) {
            $type = $request->header('type');
            if($type == -1){
                $result->get();
            }
            else{
                $result->where("{$itemName}.classtype", $type);
            }
        }
        if ($request->hasHeader('category')) {
            $category = $request->header('category');
            if($category == -1){
                $result->get();
            }
            else{
                $result->where("{$itemName}.categoryid", $category);
            }
        }
        if ($request->hasHeader('subcategory')) {
            $subcategory = $request->header('subcategory');
            if($subcategory == -1){
                $result->get();
            }
            else{
            $result->where("{$itemName}.specode", $subcategory);
        }
    }
        if ($request->hasHeader('brand')) {
            $brand = $request->header('brand');
            if($brand == -1){
                $result->get();
            }
            else{
            $result->where("{$itemName}.markerf", $brand);
        }
    }
        $items = $result->orderby("{$itemName}.code","asc")->get();
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

    // retrieve cashvan items
    public function cashvanWHouse(Request $request)
{
    $code = $request->header("citycode");
    $lang = $request->header("lang");
    $itemsTable = (new LG_ITEMS)->getTable();
    $itemName = str_replace('{code}', $code, $itemsTable);
    $priceName = str_replace('{code}', $code, (new LG_PRCLIST)->getTable());
    $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
    $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
    $warehousetName = str_replace('{code}', $code, (new LV_01_STINVTOT)->getTable());
    $markName = str_replace('{code}', $code, (new LG_MARK)->getTable());
    $catName = str_replace('{code}', $code, (new LG_SPECODES)->getTable());
    $result = DB::table("{$itemName}")
    ->leftJoin("$priceName", function($join) use ($itemName, $priceName) {
    $join->on("{$itemName}.logicalref", "=", "{$priceName}.cardref")
        ->where(["{$priceName}.clientcode" => "180.*" , "{$priceName}.active" => 0])
        ->whereRaw("{$priceName}.priority = (SELECT MAX(priority) FROM {$priceName} WHERE cardref = {$itemName}.logicalref)");
    })
    ->leftJoin("{$unitName}", "{$itemName}.unitsetref", "=", "{$unitName}.logicalref")
    ->leftJoin("{$markName}", "{$itemName}.markref", "=", "{$markName}.logicalref")
    ->leftJoin("{$catName} as cat", "{$itemName}.categoryid", "=", "cat.logicalref")
    ->leftJoin("{$catName} as sub", "{$itemName}.specode", "=", "sub.logicalref")
    ->leftJoin("{$weightName}", function($join) use ($itemName, $weightName) {
    $join->on("{$itemName}.logicalref", "=", "{$weightName}.itemref")
        ->where("{$weightName}.linenr", "=", 1);
    })
    ->leftJoin("{$warehousetName}", function($join) use ($itemName, $warehousetName) {
    $join->on("{$itemName}.logicalref", "=", "{$warehousetName}.stockref")
        ->where("{$warehousetName}.invenno", "=", 10);
    })
    ->select("{$itemName}.logicalref as id", "{$itemName}.code as code","{$itemName}.stgrpcode as group","{$priceName}.price",
    DB::raw("CASE WHEN '{$lang}' = 'ar' THEN {$itemName}.name WHEN '{$lang}' = 'en' THEN {$itemName}.name3 WHEN '{$lang}' = 'tr' THEN {$itemName}.name4 ELSE {$itemName}.name
    END as name"),
    DB::raw("CASE WHEN '{$lang}' = 'ar' THEN cat.definition_  WHEN '{$lang}' = 'en' THEN cat.definition2 WHEN '{$lang}' = 'tr' THEN cat.definition3 ELSE cat.definition_
    END as category"),
    DB::raw("CASE WHEN '{$lang}' = 'ar' THEN sub.definition_  WHEN '{$lang}' = 'en' THEN sub.definition2 WHEN '{$lang}' = 'tr' THEN sub.definition3 ELSE sub.definition_ END 
    as subcategory"),
    DB::raw("COALESCE(SUM({$warehousetName}.onhand), 0) as quantity") ,"{$unitName}.code as unit", "{$weightName}.grossweight as weight")
    ->where(["{$itemName}.active" => 0])
    ->having(DB::raw('COALESCE(SUM('.$warehousetName.'.onhand), 0)'), '>' ,0)
    ->groupBy("{$itemName}.logicalref","{$itemName}.code", "{$itemName}.name",'cat.definition_','cat.definition2','cat.definition3',"{$markName}.code","{$itemName}.name3",
    "{$itemName}.name4", "{$itemName}.stgrpcode","{$itemName}.categoryid",'sub.definition_','sub.definition2','sub.definition3',"{$itemName}.markref","{$itemName}.stgrpcode",
    "{$priceName}.price", "{$unitName}.code", "{$weightName}.grossweight");
    if ($request->hasHeader('type')) {
        $type = $request->header('type');
        if($type == -1){
            $result->get();
        }
        else{
            $result->where("{$itemName}.classtype", $type);
        }
    }
    if ($request->hasHeader('category')) {
        $category = $request->header('category');
        if($category == -1){
            $result->get();
        }
        else{
            $result->where("{$itemName}.categoryid", $category);
        }
    }
    if ($request->hasHeader('subcategory')) {
        $subcategory = $request->header('subcategory');
        if($subcategory == -1){
            $result->get();
        }
        else{
        $result->where("{$itemName}.specode", $subcategory);
    }
}
    if ($request->hasHeader('brand')) {
        $brand = $request->header('brand');
        if($brand == -1){
            $result->get();
        }
        else{
        $result->where("{$itemName}.markerf", $brand);
    }
}
    $items = $result->orderby("{$itemName}.code","asc")->get();
    if ($items){
        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $items,
            // 'current_page' => $items->currentPage(),
            // 'per_page' => $items->perPage(),
            // 'last_page' => $items->lastPage(),
            // 'total' => $items->total(),
        ], 200);
    } else{
        return response()->json([
            'status' => 'success',
            'message' => 'Ther is no items in this stock',
        ], 200);
    }
    
}

    //retrieve wastage items
    public function wastageWHouse(Request $request)
    {
        $code = $request->header("citycode");
        $lang = $request->header("lang");
        $itemsTable = (new LG_ITEMS)->getTable();
        $itemName = str_replace('{code}', $code, $itemsTable);
        $priceName = str_replace('{code}', $code, (new LG_PRCLIST)->getTable());
        $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
        $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
        $warehousetName = str_replace('{code}', $code, (new LV_01_STINVTOT)->getTable());
        $markName = str_replace('{code}', $code, (new LG_MARK)->getTable());
        $catName = str_replace('{code}', $code, (new LG_SPECODES)->getTable());
        $result = DB::table("{$itemName}")
        ->leftJoin("$priceName", function($join) use ($itemName, $priceName) {
        $join->on("{$itemName}.logicalref", "=", "{$priceName}.cardref")
            ->where(["{$priceName}.clientcode" => "180.*" , "{$priceName}.active" => 0])
            ->whereRaw("{$priceName}.priority = (SELECT MAX(priority) FROM {$priceName} WHERE cardref = {$itemName}.logicalref)");
        })
        ->leftJoin("{$unitName}", "{$itemName}.unitsetref", "=", "{$unitName}.logicalref")
        ->leftJoin("{$markName}", "{$itemName}.markref", "=", "{$markName}.logicalref")
        ->leftJoin("{$catName} as cat", "{$itemName}.categoryid", "=", "cat.logicalref")
        ->leftJoin("{$catName} as sub", "{$itemName}.specode", "=", "sub.logicalref")
        ->leftJoin("{$weightName}", function($join) use ($itemName, $weightName) {
        $join->on("{$itemName}.logicalref", "=", "{$weightName}.itemref")
            ->where("{$weightName}.linenr", "=", 1);
        })
        ->leftJoin("{$warehousetName}", function($join) use ($itemName, $warehousetName) {
        $join->on("{$itemName}.logicalref", "=", "{$warehousetName}.stockref")
            ->where("{$warehousetName}.invenno", "=", 9);
        })
        ->select("{$itemName}.logicalref as id", "{$itemName}.code as code", DB::raw("CASE WHEN '{$lang}' = 'ar' THEN {$itemName}.name WHEN '{$lang}' = 'en' THEN {$itemName}.name3 WHEN '{$lang}' = 'tr' THEN {$itemName}.name4 ELSE {$itemName}.name
        END as name"),
        DB::raw("CASE WHEN '{$lang}' = 'ar' THEN cat.definition_  WHEN '{$lang}' = 'en' THEN cat.definition2 WHEN '{$lang}' = 'tr' THEN cat.definition3 ELSE cat.definition_
        END as category"), DB::raw("CASE WHEN '{$lang}' = 'ar' THEN sub.definition_  WHEN '{$lang}' = 'en' THEN sub.definition2
        WHEN '{$lang}' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory"),
        "{$itemName}.stgrpcode as group","{$priceName}.price",
        "{$unitName}.code as unit", "{$weightName}.grossweight as weight", DB::raw("COALESCE(SUM({$warehousetName}.onhand), 0) as quantity"))
        ->where(["{$itemName}.active" => 0]) 
        ->having(DB::raw('COALESCE(SUM('.$warehousetName.'.onhand), 0)'), '>', 0)
        ->groupBy("{$itemName}.logicalref","{$itemName}.code", "{$itemName}.name",'cat.definition_','cat.definition2','cat.definition3',"{$markName}.code","{$itemName}.name3","{$itemName}.name4", "{$itemName}.stgrpcode","{$itemName}.categoryid",
        'sub.definition_','sub.definition2','sub.definition3',"{$itemName}.markref",
        "{$itemName}.stgrpcode", "{$priceName}.price", "{$unitName}.code", "{$weightName}.grossweight");
        if ($request->hasHeader('type')) {
            $type = $request->header('type');
            $result->where("{$itemName}.classtype", $type);
        }
    
        if ($request->hasHeader('category')) {
            $category = $request->header('category');
            if($category == -1){
                $result->get();
            }
            else{
                $result->where("{$itemName}.categoryid", $category);
            }
        }
        if ($request->hasHeader('subcategory')) {
            $subcategory = $request->header('subcategory');
            if($subcategory == -1){
                $result->get();
            }
            else{
            $result->where("{$itemName}.specode", $subcategory);
        }
    }
        if ($request->hasHeader('brand')) {
            $brand = $request->header('brand');
            $result->where("{$itemName}.markref", $brand);
        }
    
        $items = $result->orderby("{$itemName}.code","asc")->get();
        if ($items){
            return response()->json([
                'status' => 'success',
                'message' => 'Items list',
                'data' => $items,
            ], 200);
        } else{
            return response()->json([
                'status' => 'success',
                'message' => 'Ther is no items in this stock',
            ], 200);
        }
    }
}

