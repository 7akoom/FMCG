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
use App\Models\LG_SPECODES;
use App\Models\LV_01_STINVTOT;
use App\Models\LG_MARK;
use App\Imports\ItemDefImport;
use App\Imports\ItemImport;
use Maatwebsite\Excel\Facades\Excel;


class TestController extends Controller
{
    public function import(Request $request){
        Excel::import(new ItemDefImport, $request->file('file')->store('files'));
        return response()->json();
    }
    public function index()
    {
        $items = DB::table('lg_888_items')
            ->leftjoin('lg_888_specodes as spe1','lg_888_items.specode', '=', 'spe1.specode')->where('spe1.spetyp1',1)
            ->leftjoin('lg_888_specodes as spe2','lg_888_items.specode2', '=', 'spe2.specode')->where('spe2.spetyp2',1)
            ->leftjoin('lg_888_specodes as spe3','lg_888_items.specode3', '=', 'spe3.specode')->where('spe3.spetyp3',1)
            ->leftjoin('lg_888_specodes as spe4','lg_888_items.specode4', '=', 'spe4.specode')->where('spe4.spetyp4',1)
            ->leftjoin('lg_888_specodes as spe5','lg_888_items.specode5', '=', 'spe5.specode')->where('spe5.spetyp5',1)
            ->select('lg_888_items.code','lg_888_items.name','spe1.definition_ as type','spe2.definition_ as F_cat','spe4.definition_ as F_sub',
           )
            ->where('lg_888_items.logicalref',311)
            ->get();
    
        // dd($items);
        return response()->json($items);
    }

     public function catAndSubCategory(Request $request)
    {
        $code = $request->header("citycode");
        $lang = $request->header("lang");
        $type = $request->header("type");
        $catName = str_replace('{code}', $code, (new LG_SPECODES)->getTable()); 
        $categories = DB::table("{$catName}")
            ->select("{$catName}.logicalref as id", DB::raw("CASE WHEN '{$lang}' = 'ar' THEN $catName.definition_  
                WHEN '{$lang}' = 'en' THEN $catName.definition2 
                WHEN '{$lang}' = 'tr' THEN $catName.definition3 ELSE $catName.definition_ END as category_name"))
            ->where("{$catName}.color", $type)
            ->get();
        $categoryIds = $categories->pluck('id');
        $subcategories = DB::table("{$catName} as sub")
        ->select('logicalref as subcategory_id', DB::raw("CASE WHEN '{$lang}' = 'ar' THEN sub.definition_  
        WHEN '{$lang}' = 'en' THEN sub.definition2 
        WHEN '{$lang}' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory_name"), 'globalid as category_id')
        ->whereIn('globalid', $categoryIds)
        ->get();
        $categoriesWithSubcategories = [];
        foreach ($categories as $category) {
            $category->subcategories = $subcategories->where('category_id', $category->id)->values()->toArray();
            $categoriesWithSubcategories[] = $category;
        }
            return response()->json([
            'status' => 'success',
            'message' => 'Categories with subcategories list',
            'data' => $categoriesWithSubcategories,
        ], 200);
    }

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
            ->where(["{$priceName}.clientcode" => "" , "{$priceName}.active" => 0]);
            // ->whereRaw("{$priceName}.priority = (SELECT MAX(priority) FROM {$priceName} WHERE cardref = {$itemName}.logicalref)");
        })
        ->leftJoin("{$unitName}", "{$itemName}.unitsetref", "=", "{$unitName}.logicalref")
        ->leftJoin("{$markName}", "{$itemName}.markref", "=", "{$markName}.logicalref")
        ->leftJoin("{$catName} as cat", "{$itemName}.categoryid", "=", "cat.logicalref")
        ->leftJoin("{$catName} as sub", "{$itemName}.categoryname", "=", "sub.logicalref")
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
            $result->where("{$itemName}.categoryname", $subcategory);
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

    
}