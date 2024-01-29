<?php

namespace App\Http\Controllers;

use App\Models\LG_SPECODES;
use Illuminate\Http\Request;
use App\Imports\ItemDefImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

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

    private function fetchValueFromTable($table, $column, $value1, $value2)
    {
        return DB::table($table)
            ->where($column, $value1)
            ->value($value2);
    }

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
        $categories = DB::table("$this->specialcodesTable")
            ->leftJoin("$this->itemsTable as items", "$this->specialcodesTable.specode", '=', 'items.specode')
            ->select(
                "$this->specialcodesTable.logicalref as id",
                DB::raw("CASE WHEN '{$this->lang}' = 'ar' THEN $this->specialcodesTable.definition_
            WHEN '{$this->lang}' = 'en' THEN $this->specialcodesTable.definition2
            WHEN '{$this->lang}' = 'tr' THEN $this->specialcodesTable.definition3
            ELSE $this->specialcodesTable.definition_ END as name"),
                DB::raw('COUNT(items.logicalref) as item_count')
            )
            ->groupBy("$this->specialcodesTable.logicalref", 'definition_');

        if ($this->type == -1) {
            $categories = $categories
                ->where([
                    "$this->specialcodesTable.codetype" => 1,
                    "$this->specialcodesTable.specodetype" => 1,
                    "$this->specialcodesTable.spetyp1" => 1
                ])
                ->whereBetween("$this->specialcodesTable.globalid", ['1', '2']);
        } elseif ($this->type == 1) {
            $categories = $categories->where([
                "$this->specialcodesTable.codetype" => 1,
                "$this->specialcodesTable.specodetype" => 1,
                "$this->specialcodesTable.spetyp1" => 1,
                "$this->specialcodesTable.globalid" => 1
            ]);
        } elseif ($this->type == 2) {
            $categories = $categories->where([
                "$this->specialcodesTable.codetype" => 1,
                "$this->specialcodesTable.specodetype" => 1,
                "$this->specialcodesTable.spetyp1" => 1,
                "$this->specialcodesTable.globalid" => 2,
            ]);
        }

        $categoriesData = $categories->get();

        if ($categoriesData->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Categories list with item count',
            'data' => $categoriesData,
        ], 200);
    }


    public function subcategories(Request $request)
    {
        $category = DB::table("$this->specialcodesTable")
            ->leftJoin("$this->itemsTable as items", "$this->specialcodesTable.specode", '=', 'items.specode2')
            ->select(
                "$this->specialcodesTable.logicalref as id",
                "$this->specialcodesTable.specode as code",
                DB::raw("CASE WHEN '{$this->lang}' = 'ar' THEN $this->specialcodesTable.definition_  WHEN '$this->lang' = 'en' THEN $this->specialcodesTable.definition2
       WHEN '{$this->lang}' = 'tr' THEN $this->specialcodesTable.definition3 ELSE $this->specialcodesTable.definition_ END as name"),
                DB::raw('COUNT(items.logicalref) as item_count')
            )
            ->where(["$this->specialcodesTable.spetyp2" => 1, "$this->specialcodesTable.globalid" => $this->category])
            ->groupBy("$this->specialcodesTable.logicalref", "$this->specialcodesTable.definition_", "$this->specialcodesTable.specode")
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

    public function groups()
    {
        $category = DB::table("$this->specialcodesTable")
            ->leftJoin("$this->itemsTable as items", "$this->specialcodesTable.specode", '=', 'items.stgrpcode')
            ->select(
                "$this->specialcodesTable.logicalref as id",
                "$this->specialcodesTable.specode as code",
                DB::raw("CASE WHEN '{$this->lang}' = 'ar' THEN $this->specialcodesTable.definition_  WHEN '$this->lang' = 'en' THEN $this->specialcodesTable.definition2
       WHEN '{$this->lang}' = 'tr' THEN $this->specialcodesTable.definition3 ELSE $this->specialcodesTable.definition_ END as name"),
                DB::raw('COUNT(items.logicalref) as item_count')
            )
            ->where(["$this->specialcodesTable.codetype" => 4, "$this->specialcodesTable.specodetype" => 0])
            ->groupBy("$this->specialcodesTable.logicalref", "$this->specialcodesTable.specode", "$this->specialcodesTable.definition_")
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
            ->select('SPECODE as subcategory_id', DB::raw("CASE WHEN '$this->lang' = 'ar' THEN sub.definition_
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


    public function addCategory()
    {
        $last_cat = DB::table("$this->specialcodesTable")
            ->where(['codetype' => 1, 'specodetype' => 1, 'spetyp1' => 1])
            ->orderby('logicalref', 'desc')
            ->value('specode');
        $category = [
            'CODE_TYPE' => 1,
            'SPE_CODE_TYPE' => 1,
            'CODE' => $last_cat + 1,
            'DEFINITION' => request()->arabic_name,
            'DEFINITION2' => request()->english_name,
            'DEFINITION3' => request()->turkish_name,
            'SPE_CODE_TYPE1' => 1,
            'GLOBAL_ID' => request()->type,
        ];
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($category), 'application/json')
                ->post("https://10.27.0.109:32002/api/v1/specialCodes");
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Process failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function editCategory($id)
    {
        $category = DB::table("$this->specialcodesTable")
            ->where('logicalref', $id)
            ->FIRST();
        if (!$category) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Record not exist',
                'data' => [],
            ], 404);
        }
        $data = [
            'id' => $category->LOGICALREF,
            'code' => $category->SPECODE,
            'arabic_name' => $category->DEFINITION_,
            'english_name' => $category->DEFINITION2,
            'turkish_name' => $category->DEFINITION3,
            'type' => $category->GLOBALID,
        ];
        return response()->json([
            'status' => 'success',
            'message' => 'Category info',
            'data' => $data,
        ], 200);
    }

    public function updateCategory($id)
    {
        $category = DB::table("$this->specialcodesTable")
            ->where('logicalref', $id)
            ->value('specode');
        if (!$category) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Record not exist',
                'data' => [],
            ], 404);
        }
        $data = [
            'CODE_TYPE' => 1,
            'SPE_CODE_TYPE' => 1,
            'CODE' => $category,
            'DEFINITION' => request()->arabic_name,
            'DEFINITION2' => request()->english_name,
            'DEFINITION3' => request()->turkish_name,
            'SPE_CODE_TYPE1' => 1,
            'GLOBAL_ID' => request()->type,
        ];
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->put("https://10.27.0.109:32002/api/v1/specialCodes/{$id}");
            // $responseData = $response->json();
            // $new = [
            //     'arabic_name' => $responseData['DEFINITION'],
            //     'english_name' => $responseData['DEFINITION2'],
            //     'turkish_name' => $responseData['DEFINITION3'],
            // ];
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Process failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function addSubCategory()
    {
        $last_cat = DB::table("$this->specialcodesTable")
            ->where(['codetype' => 1, 'specodetype' => 1, 'spetyp2' => 1])
            ->orderby('logicalref', 'desc')
            ->value('specode');
        $subcategory = [
            'CODE_TYPE' => 1,
            'SPE_CODE_TYPE' => 1,
            'CODE' => $last_cat + 1,
            'DEFINITION' => request()->arabic_name,
            'DEFINITION2' => request()->english_name,
            'DEFINITION3' => request()->turkish_name,
            'SPE_CODE_TYPE2' => 1,
            'GLOBAL_ID' => request()->category_id,
        ];
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($subcategory), 'application/json')
                ->post("https://10.27.0.109:32002/api/v1/specialCodes");
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Process failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function editSubCategory($id)
    {
        $category = DB::table("$this->specialcodesTable as category")
            ->join("$this->specialcodesTable as subcategory", "category.logicalref", "=", "subcategory.globalid")
            ->where('subcategory.logicalref', $id)
            ->select(
                "subcategory.logicalref",
                "subcategory.specode",
                "subcategory.definition_",
                "subcategory.definition2",
                "subcategory.definition3",
                "category.definition_ as category_name"
            )
            ->first();

        if (!$category) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Record not exist',
                'data' => [],
            ], 404);
        }

        $data = [
            'id' => $category->logicalref,
            'code' => $category->specode,
            'arabic_name' => $category->definition_,
            'english_name' => $category->definition2,
            'turkish_name' => $category->definition3,
            'category' => $category->category_name,
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Sub-category info',
            'data' => $data,
        ], 200);
    }

    public function updateSubCategory($id)
    {
        $subcategory = $this->fetchValueFromTable("$this->specialcodesTable", "LOGICALREF", $id, "specode");
        $global_id = $this->fetchValueFromTable("$this->specialcodesTable", "LOGICALREF", $id, "globalid");
        if (!$subcategory) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Record not exist',
                'data' => [],
            ], 404);
        }
        $data = [
            'CODE_TYPE' => 1,
            'SPE_CODE_TYPE' => 1,
            'CODE' => $subcategory,
            'DEFINITION' => request()->arabic_name,
            'DEFINITION2' => request()->english_name,
            'DEFINITION3' => request()->turkish_name,
            'SPE_CODE_TYPE2' => 1,
            'GLOBAL_ID' => request()->category,
        ];
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->put("https://10.27.0.109:32002/api/v1/specialCodes/{$id}");
            // $responseData = $response->json();
            // $new = [
            //     'arabic_name' => $responseData['DEFINITION'],
            //     'english_name' => $responseData['DEFINITION2'],
            //     'turkish_name' => $responseData['DEFINITION3'],
            // ];
            // $cat = $this->fetchValueFromTable("$this->specialcodesTable", "LOGICALREF", $global_id, "DEFINITION_");
            // $new['category'] = $cat;
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Proccess failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function addGroup()
    {
        $last_cat = DB::table("$this->specialcodesTable")
            ->where(['codetype' => 4, 'specodetype' => 0])
            ->orderby('logicalref', 'desc')
            ->value('specode');
        $category = [
            'CODE_TYPE' => 4,
            'SPE_CODE_TYPE' => 0,
            'CODE' => $last_cat + 1,
            'DEFINITION' => request()->arabic_name,
            'DEFINITION2' => request()->english_name,
            'DEFINITION3' => request()->turkish_name,
        ];
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($category), 'application/json')
                ->post("https://10.27.0.109:32002/api/v1/groupCodes");
            $responseData = $response->json();
            $new = [
                'arabic_name' => $responseData['DEFINITION'],
                'english_name' => $responseData['DEFINITION2'],
                'turkish_name' => $responseData['DEFINITION3'],
            ];
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $new,
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Process failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function editGroup($id)
    {
        $category = DB::table("$this->specialcodesTable")
            ->where('logicalref', $id)
            ->first();
        if (!$category) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Record not exist',
                'data' => [],
            ], 404);
        }
        $data = [
            'id' => $category->LOGICALREF,
            'code' => $category->SPECODE,
            'arabic_name' => $category->DEFINITION_,
            'english_name' => $category->DEFINITION2,
            'turkish_name' => $category->DEFINITION3,
        ];
        return response()->json([
            'status' => 'success',
            'message' => 'Group info',
            'data' => $data,
        ], 200);
    }

    public function updateGroup($id)
    {
        $category = DB::table("$this->specialcodesTable")
            ->where('logicalref', $id)
            ->value('specode');
        if (!$category) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Record not exist',
                'data' => [],
            ], 404);
        }
        $data = [
            'CODE_TYPE' => 4,
            'SPE_CODE_TYPE' => 0,
            'CODE' => $category,
            'DEFINITION' => request()->arabic_name,
            'DEFINITION2' => request()->english_name,
            'DEFINITION3' => request()->turkish_name,
        ];
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->put("https://10.27.0.109:32002/api/v1/groupCodes/{$id}");
            // $responseData = $response->json();
            // $new = [
            //     'arabic_name' => $responseData['DEFINITION'],
            //     'english_name' => $responseData['DEFINITION2'],
            //     'turkish_name' => $responseData['DEFINITION3'],
            // ];
            // dd($responseData);
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Process failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        $data = DB::table("$this->specialcodesTable")
            ->where('logicalref', $id)
            ->first();
        if (!$data) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Record not exist',
                'data' => [],
            ], 404);
        }
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->delete("https://10.27.0.109:32002/api/v1/specialCodes/{$id}");
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Process failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
