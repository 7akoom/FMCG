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
        $customer = $request->header("customer");


        $last_customer = DB::table($this->customersTable)->where('logicalref', $customer)->value('specode2');

        $items = DB::table("{$this->itemsTable}")
            ->leftJoin("$this->pricesTable", function ($join) use ($last_customer) {
                $join->on("$this->pricesTable.cardref", "=", "$this->itemsTable.logicalref")
                    ->where(["$this->pricesTable.active" => 0, "$this->pricesTable.clspecode2" => $last_customer, "$this->pricesTable.ptype" => 2]);
            })
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
                DB::raw("COALESCE($this->pricesTable.price, '0') as price"),
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
                "$this->pricesTable.price",
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
            // 'current_page' => $result->currentPage(),
            // 'per_page' => $result->perPage(),
            // 'last_page' => $result->lastPage(),
            // 'total' => $result->total(),
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
            // 'current_page' => $items->currentPage(),
            // 'per_page' => $items->perPage(),
            // 'last_page' => $items->lastPage(),
            // 'total' => $items->total(),
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
        $customer = $request->header("customer");
        $last_customer = DB::table($this->customersTable)->where('logicalref', $customer)->value('specode2');
        $result = DB::table("$this->itemsTable")
            ->join("$this->pricesTable", "{$this->pricesTable}.cardref", "=", "{$this->itemsTable}.logicalref")
            ->where(["{$this->pricesTable}.active" => 0, "{$this->pricesTable}.clspecode2" => $last_customer, "$this->pricesTable.ptype" => 2,])
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
                "$this->pricesTable.price",
                "$this->unitsTable.code as unit",
                "$this->weightsTable.grossweight as weight"
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
                "$this->pricesTable.price",
                "$this->unitsTable.code",
                "$this->weightsTable.grossweight"
            );
        if ($request->hasHeader('type')) {
            $result->where("$this->itemsTable.classtype", $this->type);
        }

        if ($request->hasHeader('category')) {
            $result->where("$this->itemsTable.speocde", $this->category);
        }

        if ($request->hasHeader('subcategory')) {
            $result->where("$this->itemsTable.specode2", $this->subcategory);
        }

        if ($request->hasHeader('brand')) {
            $result->where("$this->itemsTable.markref", $this->brand);
        }

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
        foreach ($transactions as $item) {
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "ITEM_CODE" => $item['item_code'],
                "LINE_TYPE" => $item['item_type'],
                "SOURCEINDEX" => $data['SOURCE_WH'],
                "QUANTITY" => $item['item_quantity'],
                "PRICE" => $item['item_price'],
                "TOTAL" => $item['item_total'],
                "NET_TOTAL" => $item['item_total'],
                "RC_XRATE" => 1,
                "UNIT_CONV1" => 1,
                "UNIT_CONV2" => 1,
                "VAT_BASE" => $item['item_total'],
            ];
            $data['TRANSACTIONS']['items'][] = $itemData;
        }
        dd(request()->header('authorization'));
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
                ->post('https://10.27.0.109:32002/api/v1/salesInvoices');
            return response()->json([
                'status' => 'success',
                'message' => 'Invoice saved successfully',
                'invoice' => json_decode($response),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
