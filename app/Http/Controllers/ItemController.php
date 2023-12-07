<?php

namespace App\Http\Controllers;

use Throwable;
use App\Traits\Filterable;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Query\Builder;



class ItemController extends Controller
{
    protected $code;
    protected $username;
    protected $usersTable;
    protected $customersTable;
    protected $specialcodesTable;
    protected $brandsTable;
    protected $weightsTable;
    protected $unitsTable;
    protected $pricesTable;
    protected $itemsTable;
    protected $warehousesView;
    protected $lang;
    protected $category;
    protected $subcategory;
    protected $type;
    protected $brand;
    protected $paginate;

    private function fetchValueFromTable($table, $column, $value1, $value2)
    {
        return DB::table($table)
            ->where($column, $value1)
            ->value($value2);
    }

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->username = $request->header('username');
        $this->usersTable = 'L_CAPIUSER';
        $this->customersTable = 'LG_' . $this->code . '_CLCARD';
        $this->specialcodesTable = 'LG_' . $this->code . '_SPECODES';
        $this->brandsTable = 'LG_' . $this->code . '_MARK';
        $this->weightsTable = 'LG_' . $this->code . '_ITMUNITA';
        $this->unitsTable = 'LG_' . $this->code . '_UNITSETF';
        $this->pricesTable = 'LG_' . $this->code . '_PRCLIST';
        $this->itemsTable = 'LG_' . $this->code . '_ITEMS';
        $this->warehousesView = 'LV_' . $this->code . '_01_STINVTOT';
        $this->lang = $request->header("lang", "ar");
        $this->category = $request->header("category");
        $this->subcategory = $request->header("subcategory");
        $this->type = $request->header("type");
        $this->brand = $request->header("brand");
        $this->paginate = $request->input('perpage', 15);
    }

    public function index(Request $request)
    {
        $items = DB::table("{$this->itemsTable}")
            ->join("$this->unitsTable", "{$this->itemsTable}.unitsetref", "=", "$this->unitsTable.logicalref")
            ->join("$this->brandsTable", "{$this->itemsTable}.markref", "=", "$this->brandsTable.logicalref")
            ->join("$this->specialcodesTable as sub", "{$this->itemsTable}.specode2", "=", "sub.specode")
            ->where(['sub.codetype' => 1, 'sub.specodetype' => 1, 'sub.spetyp2' => 1])
            ->join("$this->weightsTable as weights", "weights.itemref", "=", "{$this->itemsTable}.logicalref")
            ->where("weights.linenr", "=", 1)
            ->join("$this->weightsTable as number", "number.itemref", "=", "{$this->itemsTable}.logicalref")
            ->where("number.linenr", "=", 2)
            ->select(
                "$this->itemsTable.logicalref as id",
                "$this->itemsTable.code as code",
                "$this->brandsTable.logicalref as brand_id",
                "$this->brandsTable.code as brand",
                "$this->brandsTable.descr as brand_image",
                DB::raw("REVERSE(SUBSTRING(REVERSE($this->brandsTable.descr), 1, CHARINDEX('/', REVERSE($this->brandsTable.descr)) - 1)) as brand_image"),
                DB::raw("CASE WHEN '$this->lang' = 'ar' THEN {$this->itemsTable}.name
                WHEN '$this->lang' = 'en' THEN $this->itemsTable.name3 WHEN '$this->lang' = 'tr' THEN {$this->itemsTable}.name4 ELSE $this->itemsTable.name END as name"),
                "{$this->itemsTable}.stgrpcode as group",
                'sub.logicalref as subcategory_id',
                DB::raw("CASE WHEN '$this->lang' = 'ar' THEN sub.definition_  WHEN '$this->lang' = 'en' THEN sub.definition2
                WHEN '$this->lang' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory_name"),
                "$this->unitsTable.logicalref as unit_id",
                "$this->unitsTable.code as unit",
                "weights.logicalref as weight_id",
                "weights.grossweight as weight",
                "number.convfact1 as pieces_number"
            )
            ->where([
                "$this->itemsTable.active" => 0, "$this->itemsTable.specode2" => $this->subcategory
            ])
            ->groupBy(
                "$this->itemsTable.logicalref",
                "$this->itemsTable.code",
                "$this->itemsTable.name",
                "$this->itemsTable.code",
                'sub.logicalref',
                "$this->itemsTable.name3",
                "$this->itemsTable.name4",
                "$this->itemsTable.stgrpcode",
                "$this->brandsTable.descr",
                "$this->brandsTable.logicalref",
                'sub.definition_',
                'sub.definition2',
                'sub.definition3',
                "$this->itemsTable.markref",
                "$this->unitsTable.logicalref",
                "$this->unitsTable.code",
                "$this->brandsTable.code",
                "weights.grossweight",
                "weights.logicalref",
                "number.convfact1"
            );
        if ($request->hasHeader('brand')) {
            if ($this->brand == -1) {
                $items->get();
            } else {
                $items->where("$this->itemsTable.markref", $this->brand);
            }
        }
        $result = $items->orderby("$this->itemsTable.logicalref", "asc")->get();
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
        ], 200);
    }

    public function itemMap()
    {
        $categories = DB::table("$this->specialcodesTable as cat")
            ->select("logicalref as id", "definition_ as category_name")
            ->where(["cat.codetype" => 1, "cat.specodetype" => 1, "cat.spetyp1" => 1])
            ->get();

        $categoryIds = $categories->pluck('id');

        $subcategories = DB::table("$this->specialcodesTable as sub")
            ->select(
                "definition_ as subcategory_name",
                "specode",
                'globalid as category_id'
            )
            ->whereIn('globalid', $categoryIds)
            ->where(['codetype' => 1, 'specodetype' => 1, 'spetyp2' => 1])
            ->get();

        $items = DB::table("{$this->itemsTable}")
            ->leftjoin("$this->unitsTable", "{$this->itemsTable}.unitsetref", "=", "$this->unitsTable.logicalref")
            ->leftjoin("$this->brandsTable", "{$this->itemsTable}.markref", "=", "$this->brandsTable.logicalref")
            ->leftjoin("$this->weightsTable as weights", "weights.itemref", "=", "{$this->itemsTable}.logicalref")
            ->where("weights.linenr", "=", 1)
            ->leftjoin("$this->weightsTable as number", "number.itemref", "=", "{$this->itemsTable}.logicalref")
            ->where("number.linenr", "=", 2)
            ->select(
                "$this->itemsTable.logicalref as id",
                "$this->itemsTable.code as code",
                "$this->brandsTable.logicalref as brand_id",
                "$this->brandsTable.code as brand",
                DB::raw("REVERSE(SUBSTRING(REVERSE($this->brandsTable.descr), 1, CHARINDEX('/', REVERSE($this->brandsTable.descr)) - 1)) as brand_image"),
                DB::raw("CASE WHEN '$this->lang' = 'ar' THEN {$this->itemsTable}.name
            WHEN '$this->lang' = 'en' THEN $this->itemsTable.name3 WHEN '$this->lang' = 'tr' THEN {$this->itemsTable}.name4 ELSE $this->itemsTable.name END as name"),
                "$this->itemsTable.stgrpcode as group",
                "$this->itemsTable.classtype as type",
                "$this->unitsTable.logicalref as unit_id",
                "$this->unitsTable.code as unit",
                "weights.logicalref as weight_id",
                "weights.grossweight as weight",
                "number.convfact1 as pieces_number",
                "$this->itemsTable.specode2 as subcategory_code"
            )
            ->where([
                "$this->itemsTable.active" => 0
            ])
            ->distinct()
            ->get();

        $itemIds = $items->pluck('id');
        $prices = DB::table("$this->pricesTable")
            ->select(
                "cardref as item_id",
                "clspecode2 as group_number",
                'price'
            )
            ->whereIn('cardref', $itemIds)
            ->where(['active' => 0, 'ptype' => 2])
            ->get();

        $categoriesWithSubcategories = [];
        foreach ($categories as $category) {
            $categoryData = [
                'category_name' => $category->category_name,
                'subcategories' => $subcategories
                    ->where('category_id', $category->id)
                    ->map(function ($subcategory) use ($items, $prices) {
                        $subcategoryItems = $items
                            ->where('subcategory_code', $subcategory->specode)
                            ->map(function ($item) use ($prices) {
                                $itemPrices = $prices
                                    ->where('item_id', $item->id)
                                    ->mapWithKeys(function ($price) {
                                        return [
                                            $price->group_number => $price->price ?: 0,
                                        ];
                                    })
                                    ->all();

                                for ($i = 1; $i <= 3; $i++) {
                                    if (!isset($itemPrices[$i])) {
                                        $itemPrices[$i] = 0;
                                    }
                                }

                                return [
                                    'id' => $item->id,
                                    'code' => $item->code,
                                    'name' => $item->name,
                                    'brand_id' => $item->brand_id,
                                    'brand_name' => $item->brand,
                                    'brand_image' => $item->brand_image,
                                    'type' => $item->type,
                                    'group' => $item->group,
                                    'weight' => $item->weight,
                                    'pieces_number' => $item->pieces_number,
                                    'prices' => $itemPrices,
                                ];
                            })
                            ->values()
                            ->toArray();

                        unset($subcategory->specode);

                        return [
                            'category_id' => $subcategory->category_id,
                            'subcategory_name' => $subcategory->subcategory_name,
                            'items' => $subcategoryItems,
                        ];
                    })
                    ->values()
                    ->toArray(),
            ];

            $categoriesWithSubcategories[$category->category_name] = $categoryData['subcategories'];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Categories with subcategories and items list',
            'data' => $categoriesWithSubcategories,
        ], 200);
    }


    public function finalItem(Request $request)
    {

        $items = DB::table("$this->itemsTable")
            ->join("$this->pricesTable", "$this->pricesTable.cardref", "=", "$this->itemsTable.logicalref")
            ->where(["$this->pricesTable.active" => 0, "$this->pricesTable.ptype" => 2, "$this->pricesTable.clspecode2" => 2])
            ->join("$this->unitsTable", "$this->itemsTable.unitsetref", "=", "$this->unitsTable.logicalref")
            ->join("$this->brandsTable", "$this->itemsTable.markref", "=", "$this->brandsTable.logicalref")
            ->join("$this->specialcodesTable as sub", "$this->itemsTable.specode2", "=", "sub.specode")
            ->where(['sub.codetype' => 1, 'sub.specodetype' => 1, 'sub.spetyp2' => 1])
            ->join("$this->weightsTable as weights", "weights.itemref", "=", "$this->itemsTable.logicalref")
            ->where("weights.linenr", "=", 1)
            ->leftJoin("$this->warehousesView as stock", function ($join) {
                $join->on("stock.stockref", "=", "$this->itemsTable.logicalref")
                    ->where("stock.invenno", "=", 0);
            })
            ->select(
                "$this->itemsTable.logicalref as id",
                "$this->itemsTable.code as code",
                DB::raw("CASE WHEN '$this->lang' = 'ar' THEN $this->itemsTable.name
                WHEN '$this->lang' = 'en' THEN $this->itemsTable.name3 WHEN '$this->lang' = 'tr' THEN $this->itemsTable.name4 ELSE $this->itemsTable.name END as name"),
                "$this->itemsTable.stgrpcode as group",
                "$this->brandsTable.code as brand",
                'sub.logicalref as subcategory_id',
                DB::raw("CASE WHEN '$this->lang' = 'ar' THEN sub.definition_  WHEN '$this->lang' = 'en' THEN sub.definition2
                WHEN '$this->lang' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory_name"),
                "$this->pricesTable.price",
                "$this->unitsTable.code as unit",
                "weights.grossweight as weight",
                DB::raw("COALESCE(SUM(stock.onhand), 0) as quantity")
            )
            ->where(["$this->itemsTable.active" => 0, "$this->itemsTable.classtype" => $this->type, "$this->itemsTable.markref" => $this->brand, "$this->itemsTable.specode2" => $this->subcategory])
            ->groupBy(
                "$this->itemsTable.logicalref",
                "$this->itemsTable.code",
                "$this->brandsTable.code",
                "$this->itemsTable.name",
                "$this->itemsTable.name3",
                "$this->itemsTable.name4",
                'sub.logicalref',
                'sub.definition_',
                'sub.definition2',
                'sub.definition3',
                "$this->itemsTable.stgrpcode",
                "$this->pricesTable.price",
                "$this->unitsTable.code",
                "weights.grossweight",
            )
            ->get();
        if ($items->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $items,
        ], 200);
    }

    public function getUnitWithPrice()
    {


        $itemId = request()->header('itemid') ?? 0;

        $customer = request()->header("customer") ?? 0;

        $last_customer = DB::table($this->customersTable)->where('logicalref', $customer)->value('specode2');

        if (!$last_customer || !$itemId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found',
                'data' => []
            ], 422);
        }

        $data = DB::select("
            select
                LG_888_UNITSETL.LOGICALREF as id,
                LG_888_UNITSETL.name,
                lg_888_itmunita.convfact1 as per,
                (
                    select
                        price
                    from
                        lg_888_prclist
                    where
                        clspecode2 = $last_customer
                        and cardref = $itemId
                        and active = 0
                        and ptype = 2
                    ) / lg_888_itmunita.convfact1 as price
            from
                lg_888_itmunita
                join LG_888_UNITSETL on lg_888_itmunita.unitlineref = LG_888_UNITSETL.LOGICALREF
            where
                lg_888_itmunita.itemref=$itemId
        ");

        return response()->json([
            'data' => $data
        ]);
    }

    public function getItemDetails(Request $request)
    {
        $itemCode = $request->input('item_code');
        $result = DB::table("$this->itemsTable")
            ->join("$this->unitsTable", "$this->itemsTable.unitsetref", "=", "$this->unitsTable.logicalref")
            ->join("$this->brandsTable", "$this->itemsTable.markref", "=", "$this->brandsTable.logicalref")
            ->join("$this->specialcodesTable as cat", "$this->itemsTable.specode", "=", "cat.specode")
            ->where(['cat.codetype' => 1, 'cat.specodetype' => 1, 'cat.spetyp1' => 1])
            ->join("$this->specialcodesTable as sub", "$this->itemsTable.specode2", "=", "sub.specode")
            ->where(['sub.codetype' => 1, 'sub.specodetype' => 1, 'sub.spetyp2' => 1])
            ->join("$this->weightsTable", "$this->itemsTable.logicalref", "=", "$this->weightsTable.itemref")
            ->where("$this->weightsTable.linenr", 1)
            ->select(
                "$this->itemsTable.logicalref as id",
                "$this->itemsTable.code",
                DB::raw("CASE WHEN '{$this->lang}' = 'ar' THEN $this->itemsTable.name WHEN '{$this->lang}' = 'en' THEN $this->itemsTable.name3 WHEN '{$this->lang}' = 'tr' THEN $this->itemsTable.name4 ELSE $this->itemsTable.name END as name"),
                DB::raw("CASE WHEN '{$this->lang}' = 'ar' THEN cat.definition_  WHEN '{$this->lang}' = 'en' THEN cat.definition2 WHEN '{$this->lang}' = 'tr' THEN cat.definition3 ELSE cat.definition_ END as category"),
                DB::raw("CASE WHEN '{$this->lang}' = 'ar' THEN sub.definition_  WHEN '{$this->lang}' = 'en' THEN sub.definition2 WHEN '{$this->lang}' = 'tr' THEN sub.definition3 ELSE sub.definition_ END as subcategory"),
                "$this->itemsTable.stgrpcode as group",
                "$this->weightsTable.grossweight as weight",
                "$this->brandsTable.code as brand"
            )
            ->where(["$this->itemsTable.active" => 0, "$this->itemsTable.code" => $itemCode])
            ->groupBy(
                "$this->itemsTable.logicalref",
                "$this->itemsTable.code",
                "$this->itemsTable.name",
                "$this->itemsTable.name3",
                "$this->itemsTable.name4",
                'cat.definition_',
                'cat.definition2',
                'cat.definition3',
                "$this->brandsTable.code",
                "$this->itemsTable.stgrpcode",
                'sub.definition_',
                'sub.definition2',
                'sub.definition3',
                "$this->itemsTable.markref",
                "$this->unitsTable.code",
                "$this->weightsTable.grossweight"
            );
        $items = $result->get();
        if ($items->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Items list',
            'data' => $items,
        ], 200);
    }
    public function scrapSlip()
    {
        $user_nr = $this->fetchValueFromTable($this->usersTable, 'name', $this->username, 'nr');
        $data = [
            "LOGICALREF" => 0,
            "GROUP" => 3,
            "TYPE" => 11,
            "NUMBER" => "~",
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TIME" => TimeHelper::calculateTime(),
            "DOC_NUMBER" => request()->documnet_number,
            "SOURCE_WH" => request()->source_warehouse,
            "TOTAL_DISCOUNTED" => request()->amount,
            "TOTAL_GROSS" => request()->amount,
            "TOTAL_NET" => request()->amount,
            "RC_RATE" => 1,
            "RC_NET" => request()->amount,
            "CREATED_BY" => $user_nr,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "CURRSEL_TOTALS" => 1,
            "SHIP_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SHIP_TIME" => TimeHelper::calculateTime(),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "DOC_TIME" => TimeHelper::calculateTime(),
        ];
        $transactions = request()->input('TRANSACTIONS.items');
        $i = 1;
        foreach ($transactions as $item) {
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "ITEM_CODE" => $item['item_code'],
                "LINE_TYPE" => $item['item_type'],
                "LINE_NUMBER" => $i,
                "SOURCEINDEX" => $data['SOURCE_WH'],
                "QUANTITY" => $item['item_quantity'],
                "PRICE" => $item['item_price'],
                "TOTAL" => $item['item_total'],
                "NET_TOTAL" => $item['item_total'],
                "RC_XRATE" => 1,
                "UNIT_CODE" => $item['item_unit'],
                "UNIT_CONV1" => 1,
                "UNIT_CONV2" => 1,
                "VAT_BASE" => $item['item_total'],
                "EU_VAT_STATUS" => 4,
                "EDT_CURR" => 30,
            ];
            $data['TRANSACTIONS']['items'][] = $itemData;
            $i++;
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
                ->post('https://10.27.0.109:32002/api/v1/itemSlips');
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function wHouseTransferNoticSlip()
    {
        $user_nr = $this->fetchValueFromTable($this->usersTable, 'name', $this->username, 'nr');
        $data = [
            "GROUP" => 3,
            "TYPE" => 25,
            "NUMBER" => "~",
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TIME" => TimeHelper::calculateTime(),
            "DOC_NUMBER" => request()->document_number,
            "SOURCE_WH" => request()->warehouse_source,
            "DEST_WH" => request()->warehouse_destination,
            "RC_RATE" => 1,
            "PRINT_COUNTER" => 1,
            "PRINT_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "CREATED_BY" => $user_nr,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "CURRSEL_TOTALS" => 1,
            "SHIP_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SHIP_TIME" => TimeHelper::calculateTime(),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "DOC_TIME" => TimeHelper::calculateTime(),
        ];
        $transactions = request()->input('TRANSACTIONS.items');
        $i = 1;
        foreach ($transactions as $item) {
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "ITEM_CODE" => $item['item_code'],
                "LINE_TYPE" => $item['item_type'],
                "SOURCEINDEX" => $data['SOURCE_WH'],
                "DESTINDEX" => $data['DEST_WH'],
                "LINE_NUMBER" => $i,
                "QUANTITY" => $item['item_quantity'],
                "RC_XRATE" => 1,
                "UNIT_CODE" => $item['item_unit'],
                "UNIT_CONV1" => 1,
                "UNIT_CONV2" => 1,
                "EU_VAT_STATUS" => 4,
                "EDT_CURR" => 30,
            ];
            $data['TRANSACTIONS']['items'][] = $itemData;
            $i++;
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
                ->post('https://10.27.0.109:32002/api/v1/itemSlips');
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function noticOfUseSlip()
    {
        $user_nr = $this->fetchValueFromTable($this->usersTable, 'name', $this->username, 'nr');
        $data = [
            "GROUP" => 3,
            "TYPE" => 12,
            "NUMBER" => "~",
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TIME" => TimeHelper::calculateTime(),
            "DOC_NUMBER" => request()->document_number,
            "SOURCE_WH" => request()->source_warehouse,
            "TOTAL_DISCOUNTED" => request()->total,
            "TOTAL_GROSS" => request()->total,
            "TOTAL_NET" => request()->total,
            "RC_RATE" => 1,
            "RC_NET" => request()->total,
            "PRINT_COUNTER" => 1,
            "PRINT_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "CREATED_BY" => $user_nr,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "CURRSEL_TOTALS" => 1,
            "SHIP_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SHIP_TIME" => TimeHelper::calculateTime(),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "DOC_TIME" => TimeHelper::calculateTime(),
        ];
        $transactions = request()->input('TRANSACTIONS.items');
        $i = 1;
        foreach ($transactions as $item) {
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "ITEM_CODE" => $item['item_code'],
                "LINE_TYPE" => $item['item_type'],
                "SOURCEINDEX" => $data['SOURCE_WH'],
                "LINE_NUMBER" => $i,
                "QUANTITY" => $item['item_quantity'],
                "PRICE" => $item['item_price'],
                "TOTAL" => $item['item_total'],
                "NET_TOTAL" => $item['item_total'],
                "RC_XRATE" => 1,
                "UNIT_CODE" => $item['item_unit'],
                "UNIT_CONV1" => 1,
                "UNIT_CONV2" => 1,
                "EU_VAT_STATUS" => 4,
                "EDT_CURR" => 30,
            ];
            $data['TRANSACTIONS']['items'][] = $itemData;
            $i++;
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
                ->post('https://10.27.0.109:32002/api/v1/itemSlips');
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function beginningBalanceNoteSlip()
    {
        $user_nr = $this->fetchValueFromTable($this->usersTable, 'name', $this->username, 'nr');
        $data = [
            "GROUP" => 3,
            "TYPE" => 14,
            "NUMBER" => "~",
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TIME" => TimeHelper::calculateTime(),
            "DOC_NUMBER" => request()->document_number,
            "SOURCE_WH" => request()->warehouse_source,
            "RC_RATE" => 1,
            "CREATED_BY" => $user_nr,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "CURRSEL_TOTALS" => 1,
            "SHIP_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SHIP_TIME" => TimeHelper::calculateTime(),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "DOC_TIME" => TimeHelper::calculateTime(),
        ];
        $transactions = request()->input('TRANSACTIONS.items');
        $i = 1;
        foreach ($transactions as $item) {
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "ITEM_CODE" => $item['item_code'],
                "LINE_TYPE" => $item['item_type'],
                "SOURCEINDEX" => $data['SOURCE_WH'],
                "LINE_NUMBER" => $i,
                "QUANTITY" => $item['item_quantity'],
                "RC_XRATE" => 1,
                "UNIT_CODE" => $item['item_unit'],
                "UNIT_CONV1" => 1,
                "UNIT_CONV2" => 1,
                "EU_VAT_STATUS" => 4,
                "EDT_CURR" => 30,
            ];
            $data['TRANSACTIONS']['items'][] = $itemData;
            $i++;
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
                ->post('https://10.27.0.109:32002/api/v1/itemSlips');
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function inventoryExcessVoucherSlip()
    {
        $user_nr = $this->fetchValueFromTable($this->usersTable, 'name', $this->username, 'nr');
        $data = [
            "GROUP" => 3,
            "TYPE" => 50,
            "NUMBER" => "~",
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TIME" => TimeHelper::calculateTime(),
            "DOC_NUMBER" => request()->document_number,
            "SOURCE_WH" => request()->warehouse_source,
            "RC_RATE" => 1,
            "CREATED_BY" => $user_nr,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "CURRSEL_TOTALS" => 1,
            "SHIP_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SHIP_TIME" => TimeHelper::calculateTime(),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "DOC_TIME" => TimeHelper::calculateTime(),
        ];
        $transactions = request()->input('TRANSACTIONS.items');
        $i = 1;
        foreach ($transactions as $item) {
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "ITEM_CODE" => $item['item_code'],
                "LINE_TYPE" => $item['item_type'],
                "SOURCEINDEX" => $data['SOURCE_WH'],
                "LINE_NUMBER" => $i,
                "QUANTITY" => $item['item_quantity'],
                "RC_XRATE" => 1,
                "UNIT_CODE" => $item['item_unit'],
                "UNIT_CONV1" => 1,
                "UNIT_CONV2" => 1,
                "EU_VAT_STATUS" => 4,
                "EDT_CURR" => 30,
            ];
            $data['TRANSACTIONS']['items'][] = $itemData;
            $i++;
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
                ->post('https://10.27.0.109:32002/api/v1/itemSlips');
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function inventoryDeficiencyVoucherSlip()
    {
        $user_nr = $this->fetchValueFromTable($this->usersTable, 'name', $this->username, 'nr');
        $data = [
            "GROUP" => 3,
            "TYPE" => 51,
            "NUMBER" => "~",
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TIME" => TimeHelper::calculateTime(),
            "DOC_NUMBER" => request()->document_number,
            "RC_RATE" => 1,
            "CREATED_BY" => $user_nr,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "CURRSEL_TOTALS" => 1,
            "SHIP_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SHIP_TIME" => TimeHelper::calculateTime(),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "DOC_TIME" => TimeHelper::calculateTime(),
        ];
        $transactions = request()->input('TRANSACTIONS.items');
        $i = 1;
        foreach ($transactions as $item) {
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "ITEM_CODE" => $item['item_code'],
                "LINE_TYPE" => $item['item_type'],
                "LINE_NUMBER" => $i,
                "QUANTITY" => $item['item_quantity'],
                "RC_XRATE" => 1,
                "UNIT_CODE" => $item['item_unit'],
                "UNIT_CONV1" => 1,
                "UNIT_CONV2" => 1,
                "EU_VAT_STATUS" => 4,
                "EDT_CURR" => 30,
            ];
            $data['TRANSACTIONS']['items'][] = $itemData;
            $i++;
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
                ->post('https://10.27.0.109:32002/api/v1/itemSlips');
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
