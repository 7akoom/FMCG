<?php

namespace App\Http\Controllers;

use App\Models\LG_SPECODES;
use Illuminate\Http\Request;
use App\Imports\ItemDefImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class ItemDefController extends Controller
{
    protected $code;
    protected $lang;
    protected $type;
    protected $category;
    protected $specialcodesTable;
    protected $itemsTable;
    protected $brandsTable;
    protected $weightsTable;
    protected $unitsTable;
    protected $customersTable;
    protected $pricesTable;

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->lang = $request->header("lang", "ar");
        $this->type = $request->header("type");
        $this->category = $request->header("category");
        $this->specialcodesTable = 'LG_' . $this->code . '_SPECODES';
        $this->itemsTable = 'LG_' . $this->code . '_ITEMS';
        $this->brandsTable = 'LG_' . $this->code . '_MARK';
        $this->weightsTable = 'LG_' . $this->code . '_ITMUNITA';
        $this->unitsTable = 'LG_' . $this->code . '_UNITSETF';
        $this->customersTable = 'LG_' . $this->code . '_CLCARD';
        $this->pricesTable = 'LG_' . $this->code . '_PRCLIST';
    }

    public function categories(Request $request)
    {
        $category = DB::table("$this->specialcodesTable")
            ->select("$this->specialcodesTable.logicalref as id", DB::raw("CASE WHEN '{$this->lang}' = 'ar' THEN $this->specialcodesTable.definition_
        WHEN '{$this->lang}' = 'en' THEN $this->specialcodesTable.definition2
        WHEN '{$this->lang}' = 'tr' THEN $this->specialcodesTable.definition3
        ELSE $this->specialcodesTable.definition_ END as name"));
        if ($this->type == -1) {
            $category = $category
                ->where([
                    "$this->specialcodesTable.codetype" => 1, "$this->specialcodesTable.specodetype" => 1, "$this->specialcodesTable.spetyp1" => 1
                ])
                ->whereBetween("$this->specialcodesTable.globalid", ['1', '2'])
                ->get();
        } elseif ($this->type == 1) {
            $category = $category->where([
                "$this->specialcodesTable.codetype" => 1, "$this->specialcodesTable.specodetype" => 1,
                "$this->specialcodesTable.spetyp1" => 1, "$this->specialcodesTable.globalid" => 1
            ])->get();
        } elseif ($this->type == 2) {
            $category = $category->where([
                "$this->specialcodesTable.codetype" => 1, "$this->specialcodesTable.specodetype" => 1,
                "$this->specialcodesTable.spetyp1" => 1, "$this->specialcodesTable.globalid" => 2,
            ])->get();
        }
        if ($category->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'categories list',
            'data' => $category,
        ], 200);
    }

    public function subcategories(Request $request)
    {
        $category = DB::table("$this->specialcodesTable")
            ->select(
                "$this->specialcodesTable.logicalref as id",
                "$this->specialcodesTable.specode as code",
                DB::raw("CASE WHEN '{$this->lang}' = 'ar' THEN $this->specialcodesTable.definition_  WHEN '$this->lang' = 'en' THEN $this->specialcodesTable.definition2
       WHEN '{$this->lang}' = 'tr' THEN $this->specialcodesTable.definition3 ELSE $this->specialcodesTable.definition_ END as name")
            )
            ->where(["$this->specialcodesTable.spetyp2" => 1, "$this->specialcodesTable.globalid" => $this->category])
            ->get();
        if ($category->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'sub categories list',
            'data' => $category,
        ], 200);
    }
    public function catAndSubCategory(Request $request)
    {
        $categories = DB::table("$this->specialcodesTable")
            ->select("$this->specialcodesTable.logicalref as id", DB::raw("CASE WHEN '$this->lang' = 'ar' THEN $this->specialcodesTable.definition_
        WHEN '{$this->lang}' = 'en' THEN $this->specialcodesTable.definition2
                WHEN '{$this->lang}' = 'tr' THEN $this->specialcodesTable.definition3 ELSE $this->specialcodesTable.definition_ END as category_name"))
            ->where(["$this->specialcodesTable.codetype" => 1, "$this->specialcodesTable.specodetype" => 1, "$this->specialcodesTable.spetyp1" => 1]);
        if ($request->hasHeader('type')) {
            $type = $request->header('type');
            if ($type == -1) {
                $categories = $categories->whereBetween("$this->specialcodesTable.globalid", ['1', '2'])
                    ->where(["$this->specialcodesTable.codetype" => 1, "$this->specialcodesTable.specodetype" => 1, "$this->specialcodesTable.spetyp1" => 1])
                    ->get();
            } elseif ($type == 1) {
                $categories = $categories->where(["$this->specialcodesTable.globalid" => 1, "$this->specialcodesTable.codetype" => 1, "$this->specialcodesTable.specodetype" => 1, "$this->specialcodesTable.spetyp1" => 1])->get();
            } elseif ($type == 2) {
                $categories = $categories->where(["$this->specialcodesTable.globalid" => 2, "$this->specialcodesTable.codetype" => 1, "$this->specialcodesTable.specodetype" => 1, "$this->specialcodesTable.spetyp1" => 1])->get();
            }
        }
        $categoryIds = $categories->pluck('id');
        $subcategories = DB::table("$this->specialcodesTable as sub")
            ->select('logicalref as subcategory_id', DB::raw("CASE WHEN '$this->lang' = 'ar' THEN sub.definition_
        WHEN '{$this->lang}' = 'en' THEN sub.definition2
        WHEN '{$this->lang}' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory_name"), 'globalid as category_id')
            ->whereIn('globalid', $categoryIds)
            ->where(['codetype' => 1, 'specodetype' => 1, 'spetyp2' => 1])
            ->get();
        $categoriesWithSubcategories = [];
        foreach ($categories as $category) {
            $category->subcategories = $subcategories->where('category_id', $category->id)->values()->toArray();
            $categoriesWithSubcategories[] = $category;
        }
        if (!$categoriesWithSubcategories) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Categories with subcategories list',
            'data' => $categoriesWithSubcategories,
        ], 200);
    }
}
