<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WareHouseController extends Controller
{
    protected $code;
    protected $type;
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
    protected $unitsTable;
    protected $stocksTable;

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->type = $request->header('type');
        $this->lang = $request->header('lang', 'ar');
        $this->category = $request->header('category');
        $this->subcategory = $request->header('subcategory');
        $this->brand = $request->header('brand');
        $this->perpage = $request->input('per_page', 50);
        $this->page = $request->input('page', 1);
        $this->itemsTable = 'LG_' . $this->code . '_ITEMS';
        $this->specialCodesTable = 'LG_' . $this->code . '_SPECODES';
        $this->brandsTable = 'LG_' . $this->code . '_MARK';
        $this->weightsTable = 'LG_' . $this->code . '_ITMUNITA';
        $this->unitsTable = 'LG_' . $this->code . '_UNITSETF';
        $this->stocksTable = 'LV_' . $this->code . '_01_STINVTOT';
    }
    // retrieve items by stock (0 => merkez, 9 => wastage, 10 => cashvan)
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
                // $items->where("$itemsTable.markerf", $brand);
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
            'message' => 'Customers list',
            'data' => $result->items(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'next_page' => $result->nextPageUrl($this->page),
            'previous_page' => $result->previousPageUrl($this->page),
            'last_page' => $result->lastPage(),
            'total' => $result->total(),
        ], 200);
    }

    // retrieve cashvan items
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
                // $items->where("item.markerf", $brand);
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
    //retrieve wastage items
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
                // $items->where("item.markerf", $brand);
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
}
