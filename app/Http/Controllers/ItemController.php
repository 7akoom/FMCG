<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LG_ITEMS;
use App\Models\LG_PRCLIST;
use App\Models\LG_UNITSETF;
use App\Models\LG_ITMUNITA;



class ItemController extends Controller
{
    public function index(Request $request)
    {
        $code = $request->header('citycode');
        $type = $request->header('type');
        $itemName = str_replace('{code}', $code, (new LG_ITEMS)->getTable());
        $priceName = str_replace('{code}', $code, (new LG_PRCLIST)->getTable());
        $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
        $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
        $items = DB::table($itemName)
        ->join("{$priceName}","{$itemName}.logicalref", '=', "{$priceName}.cardref")
        ->join("{$unitName}","{$itemName}.unitsetref", '=', "{$unitName}.logicalref")
        ->join("{$weightName}","{$itemName}.logicalref", '=', "{$weightName}.itemref")
        ->select("{$itemName}.logicalref AS id","{$itemName}.code","{$itemName}.name as arabic_name",
        "{$itemName}.stgrpcode as group","{$itemName}.specode as brand","{$itemName}.name3 as english_name",
        "{$itemName}.name4 as turkish_name","{$priceName}.price","{$unitName}.code AS arabic_unit",
        "{$unitName}.name AS turkish_unit","{$weightName}.grossweight")
        ->where(["{$priceName}.clientcode" => "", "{$priceName}.active" => "0","{$weightName}.linenr" => '1'])
        // ->select("{$itemName}.logicalref","{$itemName}.code","{$itemName}.name",)
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

    public function showItem(Request $request)
    {
        $code = $request->header('citycode');
        $item_code = $request->input('item_code');
        $tableName = str_replace('{code}', $code, (new LG_ITEMS)->getTable());
        $unitName = str_replace('{code}', $code, (new LG_UNITSETF)->getTable());
        $item = DB::table($tableName)
        ->join("{$unitName}","{$tableName}.unitsetref", '=', "{$unitName}.logicalref")
        ->select("{$tableName}.logicalref AS id","{$tableName}.code","{$tableName}.name as arabic_name",
        "{$tableName}.stgrpcode as group","{$tableName}.specode as brand","{$tableName}.name3 as english_name","{$tableName}.name4 as turkish_name",
        "{$unitName}.code AS arabic_unit","{$unitName}.name AS turkish_unit")
        ->where("{$tableName}.code", $item_code)
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Item retrived successfully',
            'data' => $item,
        ], 200);
    }
}
