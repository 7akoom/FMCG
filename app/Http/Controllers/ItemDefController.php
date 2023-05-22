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
            ->where("{$catName}.codetype", $type)
            ->get();
        $categoryIds = $categories->pluck('id');
        $subcategories = DB::table("{$catName} as sub")
        ->select('logicalref as subcategory_id', DB::raw("CASE WHEN '{$lang}' = 'ar' THEN sub.definition_  
        WHEN '{$lang}' = 'en' THEN sub.definition2 
        WHEN '{$lang}' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory_name"), 'codetype as category_id')
        ->whereIn('codetype', $categoryIds)
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
}
   

