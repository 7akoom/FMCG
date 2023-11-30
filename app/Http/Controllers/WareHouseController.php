<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WareHouseController extends Controller
{
    protected $code;
    protected $type;
    protected $inputType;
    protected $stock_number;
    protected $perpage;
    protected $page;
    protected $lang;
    protected $category;
    protected $subcategory;
    protected $brand;
    protected $itemsTable;
    protected $specialCodesTable;
    protected $brandsTable;
    protected $weightsTable;
    protected $unitGroupsTable;
    protected $unitsTable;
    protected $stocksTable;
    protected $wareHousesTable;

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->type = $request->header('type');
        $this->inputType = $request->input('item_type');
        $this->stock_number = $request->input('stock_number');
        $this->lang = $request->header('lang', 'ar');
        $this->category = $request->header('category');
        $this->subcategory = $request->header('subcategory');
        $this->brand = $request->header('brand');
        $this->perpage = $request->input('per_page', 50);
        $this->page = $request->input('page', 1);
        $this->wareHousesTable = 'L_CAPIWHOUSE';
        $this->itemsTable = 'LG_' . $this->code . '_ITEMS';
        $this->specialCodesTable = 'LG_' . $this->code . '_SPECODES';
        $this->brandsTable = 'LG_' . $this->code . '_MARK';
        $this->weightsTable = 'LG_' . $this->code . '_ITMUNITA';
        $this->unitGroupsTable = 'LG_' . $this->code . '_UNITSETF';
        $this->unitsTable = 'LG_' . $this->code . '_UNITSETL';
        $this->stocksTable = 'LV_' . $this->code . '_01_STINVTOT';
    }

    public function mainWHouse(Request $request)
    {
        $items = DB::table("$this->itemsTable as item")
            ->join("$this->specialCodesTable as cat", "cat.specode", "=", "item.specode")
            ->join("$this->specialCodesTable as sub", "sub.specode", "=", "item.specode2")
            ->join("$this->brandsTable", "$this->brandsTable.logicalref", "=", "item.markref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "item.logicalref")
            ->join("$this->unitsTable", "$this->unitsTable.logicalref", "=", "item.unitsetref")
            ->leftJoin("{$this->stocksTable}", function ($join) {
                $join->on("item.logicalref", "=", "{$this->stocksTable}.stockref")
                    ->where("{$this->stocksTable}.invenno", "=", 0);
            })
            ->select(
                "item.logicalref as item_id",
                "item.code as item_code",
                "item.stgrpcode as item_group",
                DB::raw("CASE
                WHEN '$this->lang' = 'ar' THEN item.name
                WHEN '$this->lang' = 'en' THEN item.name3
                WHEN '$this->lang' = 'tr' THEN item.name4
                ELSE item.name
                END as item_name"),
                DB::raw("CASE
                WHEN '$this->lang' = 'ar' THEN cat.definition_
                WHEN '$this->lang' = 'en' THEN cat.definition2
                WHEN '$this->lang' = 'tr' THEN cat.definition3
                ELSE cat.definition_
                END as category"),
                DB::raw("CASE
                WHEN '$this->lang' = 'ar' THEN sub.definition_
                WHEN '$this->lang' = 'en' THEN sub.definition2
                WHEN '$this->lang' = 'tr' THEN sub.definition3
                ELSE sub.definition_
                END as subcategory"),
                "$this->brandsTable.code as brand",
                "$this->weightsTable.grossweight as weight",
                "$this->unitsTable.code as unit",
                DB::raw("COALESCE(SUM({$this->stocksTable}.onhand), 0) as quantity")
            )
            ->where([
                'item.active' => 0, 'cat.codetype' => 1, 'cat.specodetype' => 1,
                'cat.spetyp1' => 1, 'sub.codetype' => 1, 'sub.specodetype' => 1,
                'sub.spetyp2' => 1, "$this->weightsTable.linenr" => 1
            ])
            ->groupBy(
                "item.logicalref",
                "item.code",
                "item.stgrpcode",
                DB::raw("CASE
        WHEN '$this->lang' = 'ar' THEN item.name
        WHEN '$this->lang' = 'en' THEN item.name3
        WHEN '$this->lang' = 'tr' THEN item.name4
        ELSE item.name
        END"),
                DB::raw("CASE
        WHEN '$this->lang' = 'ar' THEN cat.definition_
        WHEN '$this->lang' = 'en' THEN cat.definition2
        WHEN '$this->lang' = 'tr' THEN cat.definition3
        ELSE cat.definition_
        END"),
                DB::raw("CASE
        WHEN '$this->lang' = 'ar' THEN sub.definition_
        WHEN '$this->lang' = 'en' THEN sub.definition2
        WHEN '$this->lang' = 'tr' THEN sub.definition3
        ELSE sub.definition_
        END"),
                "{$this->brandsTable}.code",
                "{$this->weightsTable}.grossweight",
                "{$this->unitsTable}.code"
            );
        if ($request->hasHeader('type')) {
            if ($this->type == -1) {
                $items->get();
            } else {
                $items->where("item.classtype", $this->type);
            }
        }
        if ($request->hasHeader('category')) {
            if ($this->category == -1) {
                $items->get();
            } else {
                $items->where("item.specode", $this->category);
            }
        }
        if ($request->hasHeader('subcategory')) {
            if ($this->subcategory == -1) {
                $items->get();
            } else {
                $items->where("item.specode2", $this->subcategory);
            }
        }
        if ($request->hasHeader('brand')) {
            if ($this->brand == -1) {
                $items->get();
            } else {
                $items->where("$this->brandsTable.logicalref", $this->brand);
            }
        }
        $result = $items->orderby("item.code", "asc")->paginate($this->perpage);
        if ($result->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $result->items(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'next_page' => $result->nextPageUrl($this->page),
            'previous_page' => $result->previousPageUrl($this->page),
            'last_page' => $result->lastPage(),
            'total' => $result->total(),
        ], 200);
    }

    public function cashvanWHouse(Request $request)
    {
        $items = DB::table("{$this->itemsTable} as item")
            ->join("$this->specialCodesTable as cat", "cat.specode", "=", "item.specode")
            ->join("$this->specialCodesTable as sub", "sub.specode", "=", "item.specode2")
            ->join("$this->brandsTable", "$this->brandsTable.logicalref", "=", "item.markref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "item.logicalref")
            ->join("$this->unitsTable", "$this->unitsTable.logicalref", "=", "item.unitsetref")
            ->join("$this->stocksTable", "$this->stocksTable.stockref", "=", "item.logicalref")
            ->where("$this->stocksTable.invenno", 10)
            ->select(
                "item.logicalref as item_id",
                "item.code as item_code",
                "item.stgrpcode as item_group",
                DB::raw("CASE
                WHEN '$this->lang' = 'ar' THEN item.name
                WHEN '$this->lang' = 'en' THEN item.name3
                WHEN '$this->lang' = 'tr' THEN item.name4
                ELSE item.name
                END as item_name"),
                DB::raw("CASE
                WHEN '$this->lang' = 'ar' THEN cat.definition_
                WHEN '$this->lang' = 'en' THEN cat.definition2
                WHEN '$this->lang' = 'tr' THEN cat.definition3
                ELSE cat.definition_
                END as category"),
                DB::raw("CASE
                WHEN '$this->lang' = 'ar' THEN sub.definition_
                WHEN '$this->lang' = 'en' THEN sub.definition2
                WHEN '$this->lang' = 'tr' THEN sub.definition3
                ELSE sub.definition_
                END as subcategory"),
                "$this->brandsTable.code as brand",
                "$this->weightsTable.grossweight as weight",
                "$this->unitsTable.code as unit",
                DB::raw("SUM($this->stocksTable.onhand) as quantity")
            )
            ->where([
                'item.active' => 0, 'cat.codetype' => 1, 'cat.specodetype' => 1, 'cat.spetyp1' => 1, 'sub.codetype' => 1, 'sub.specodetype' => 1,
                'sub.spetyp2' => 1, "$this->weightsTable.linenr" => 1
            ])
            ->groupBy(
                "item.logicalref",
                "item.code",
                "item.stgrpcode",
                DB::raw("CASE
        WHEN '$this->lang' = 'ar' THEN item.name
        WHEN '$this->lang' = 'en' THEN item.name3
        WHEN '$this->lang' = 'tr' THEN item.name4
        ELSE item.name
        END"),
                DB::raw("CASE
        WHEN '$this->lang' = 'ar' THEN cat.definition_
        WHEN '$this->lang' = 'en' THEN cat.definition2
        WHEN '$this->lang' = 'tr' THEN cat.definition3
        ELSE cat.definition_
        END"),
                DB::raw("CASE
        WHEN '$this->lang' = 'ar' THEN sub.definition_
        WHEN '$this->lang' = 'en' THEN sub.definition2
        WHEN '$this->lang' = 'tr' THEN sub.definition3
        ELSE sub.definition_
        END"),
                "{$this->brandsTable}.code",
                "{$this->weightsTable}.grossweight",
                "{$this->unitsTable}.code"
            );
        if ($request->hasHeader('type')) {
            if ($this->type == -1) {
                $items->get();
            } else {
                $items->where("item.classtype", $this->type);
            }
        }
        if ($request->hasHeader('category')) {
            if ($this->category == -1) {
                $items->get();
            } else {
                $items->where("item.specode", $this->category);
            }
        }
        if ($request->hasHeader('subcategory')) {
            if ($this->subcategory == -1) {
                $items->get();
            } else {
                $items->where("item.specode2", $this->subcategory);
            }
        }
        if ($request->hasHeader('brand')) {
            if ($this->brand == -1) {
                $items->get();
            } else {
                $items->where("$this->brandsTable.logicalref", $this->brand);
            }
        }
        $result = $items->orderby("item.code", "asc")->paginate($this->perpage);
        if ($result->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $result,
            'current_page' => $result->currentPage(),
            'next_page' => $result->nextPageUrl($this->page),
            'previous_page' => $result->previousPageUrl($this->page),
            'per_page' => $result->perPage(),
            'last_page' => $result->lastPage(),
            'total' => $result->total(),
        ], 200);
    }

    public function wastageWHouse(Request $request)
    {
        $items = DB::table("$this->itemsTable as item")
            ->join("$this->specialCodesTable as cat", "cat.specode", "=", "item.specode")
            ->join("$this->specialCodesTable as sub", "sub.specode", "=", "item.specode2")
            ->join("$this->brandsTable", "$this->brandsTable.logicalref", "=", "item.markref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "item.logicalref")
            ->join("$this->unitsTable", "$this->unitsTable.logicalref", "=", "item.unitsetref")
            ->join("$this->stocksTable", "$this->stocksTable.stockref", "=", "item.logicalref")
            ->where("$this->stocksTable.invenno", 10)
            ->select(
                "item.logicalref as item_id",
                "item.code as item_code",
                "item.stgrpcode as item_group",
                DB::raw("CASE
                WHEN '$this->lang' = 'ar' THEN item.name
                WHEN '$this->lang' = 'en' THEN item.name3
                WHEN '$this->lang' = 'tr' THEN item.name4
                ELSE item.name
                END as item_name"),
                DB::raw("CASE
                WHEN '$this->lang' = 'ar' THEN cat.definition_
                WHEN '$this->lang' = 'en' THEN cat.definition2
                WHEN '$this->lang' = 'tr' THEN cat.definition3
                ELSE cat.definition_
                END as category"),
                DB::raw("CASE
                WHEN '$this->lang' = 'ar' THEN sub.definition_
                WHEN '$this->lang' = 'en' THEN sub.definition2
                WHEN '$this->lang' = 'tr' THEN sub.definition3
                ELSE sub.definition_
                END as subcategory"),
                "$this->brandsTable.code as brand",
                "$this->weightsTable.grossweight as weight",
                "$this->unitsTable.code as unit",
                DB::raw("SUM({$this->stocksTable}.onhand) as quantity")
            )
            ->where([
                'item.active' => 0, 'cat.codetype' => 1, 'cat.specodetype' => 1, 'cat.spetyp1' => 1, 'sub.codetype' => 1, 'sub.specodetype' => 1,
                'sub.spetyp2' => 1, "$this->weightsTable.linenr" => 1
            ])
            ->groupBy(
                "item.logicalref",
                "item.code",
                "item.stgrpcode",
                DB::raw("CASE
        WHEN '$this->lang' = 'ar' THEN item.name
        WHEN '$this->lang' = 'en' THEN item.name3
        WHEN '$this->lang' = 'tr' THEN item.name4
        ELSE item.name
        END"),
                DB::raw("CASE
        WHEN '$this->lang' = 'ar' THEN cat.definition_
        WHEN '$this->lang' = 'en' THEN cat.definition2
        WHEN '$this->lang' = 'tr' THEN cat.definition3
        ELSE cat.definition_
        END"),
                DB::raw("CASE
        WHEN '$this->lang' = 'ar' THEN sub.definition_
        WHEN '$this->lang' = 'en' THEN sub.definition2
        WHEN '$this->lang' = 'tr' THEN sub.definition3
        ELSE sub.definition_
        END"),
                "{$this->brandsTable}.code",
                "{$this->weightsTable}.grossweight",
                "{$this->unitsTable}.code"
            );
        if ($request->hasHeader('type')) {
            if ($this->type == -1) {
                $items->get();
            } else {
                $items->where("item.classtype", $this->type);
            }
        }
        if ($request->hasHeader('category')) {
            if ($this->category == -1) {
                $items->get();
            } else {
                $items->where("item.specode", $this->category);
            }
        }
        if ($request->hasHeader('subcategory')) {
            if ($this->subcategory == -1) {
                $items->get();
            } else {
                $items->where("item.specode2", $this->subcategory);
            }
        }
        if ($request->hasHeader('brand')) {
            if ($this->brand == -1) {
                $items->get();
            } else {
                $items->where("$this->brandsTable.logicalref", $this->brand);
            }
        }
        $result = $items->orderby("item.code", "asc")->paginate($this->perpage);
        if ($result->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $result,
            'current_page' => $result->currentPage(),
            'next_page' => $result->nextPageUrl($this->page),
            'previous_page' => $result->previousPageUrl($this->page),
            'per_page' => $result->perPage(),
            'last_page' => $result->lastPage(),
            'total' => $result->total(),
        ], 200);
    }
    public function wHouseList()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Warehoues lists',
            'data' => DB::table($this->wareHousesTable)
                ->where('firmnr', $this->code)
                ->select('logicalref as id', 'nr as number', 'name')
                ->get(),
        ], 200);
    }

    public function wHouse(Request $request)
    {
        $items = DB::table("$this->itemsTable AS item")
            ->leftJoin("$this->unitGroupsTable", "$this->unitGroupsTable.logicalref", '=', 'item.unitsetref')
            ->leftJoin("$this->specialCodesTable AS grp", function ($join) {
                $join->on('grp.specode', '=', "item.stgrpcode")
                    ->where('grp.codetype', '=', 4);
            })
            ->leftJoin("$this->unitsTable AS unit1", function ($join) {
                $join->on('unit1.unitsetref', '=', "$this->unitGroupsTable.logicalref")
                    ->where('unit1.linenr', '=', 1);
            })
            ->leftJoin("$this->unitsTable AS unit2", function ($join) {
                $join->on('unit2.unitsetref', '=', "$this->unitGroupsTable.logicalref")
                    ->where('unit2.linenr', '=', 2);
            })
            ->leftJoin("$this->weightsTable AS weight1", function ($join) {
                $join->on('weight1.itemref', '=', 'item.logicalref')
                    ->where('weight1.linenr', '=', 1);
            })
            ->leftJoin("$this->weightsTable AS weight2", function ($join) {
                $join->on('weight2.itemref', '=', 'item.logicalref')
                    ->where('weight2.linenr', '=', 2);
            })
            ->leftJoin("$this->stocksTable", function ($join) {
                $join->on('item.logicalref', '=', "$this->stocksTable.stockref")
                    ->where("$this->stocksTable.invenno", '=', $this->stock_number);
            })
            ->where(['item.active' => 0, 'item.cardtype' => 1])
            ->groupBy('item.logicalref', 'item.code', 'unit1.name', 'unit2.name', 'weight1.grossweight', 'weight2.grossweight', "grp.definition_");

        $result = $items->select([
            'item.logicalref as item_id',
            'item.code as item_code',
            'unit1.name as unit1',
            DB::raw('COALESCE(grp.definition_, \'0\') as group_name'),
            DB::raw('COALESCE(unit2.name, \'0\') as unit2'),
            'weight1.grossweight as weight1',
            DB::raw('COALESCE(weight2.grossweight, \'0\') as weight2'),
            DB::raw("COALESCE(SUM({$this->stocksTable}.onhand), 0) as quantity")
        ]);
        $data = ($this->inputType == -1) ? $result->paginate($this->perpage) : $result->where('item.classtype', $this->inputType)->paginate($this->perpage);

        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $data->items(),
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'next_page' => $data->nextPageUrl($this->page),
            'previous_page' => $data->previousPageUrl($this->page),
            'first_page' => $data->url(1),
            'last_page' => $data->url($data->lastPage()),
            'total' => $data->total(),
        ], 200);
    }
}
