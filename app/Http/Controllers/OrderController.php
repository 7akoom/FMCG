<?php

namespace App\Http\Controllers;

use Throwable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Traits\Filterable;
use App\Helpers\TimeHelper;
use App\Helpers\PaperLimit;
use App\Helpers\InvoiceNumberGenerator;



class OrderController extends Controller
{
    use Filterable;

    protected $code;
    protected $salesman_id;
    protected $type;
    protected $perpage;
    protected $page;
    protected $start_date;
    protected $end_date;
    protected $status;
    protected $customersTable;
    protected $salesmansTable;
    protected $ordersTable;
    protected $ordersTransactionsTable;
    protected $itemsTable;
    protected $weightsTable;
    protected $unitsTable;
    protected $payplansTable;
    protected $cutoemrsView;
    protected $whousesTable;
    protected $invoicesTable;

    private function fetchValueFromTable($table, $column, $value1, $value2)
    {
        return DB::table($table)
            ->where($column, $value1)
            ->value($value2);
    }

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->salesman_id = $request->header('id');
        $this->type = $request->header('type');
        $this->perpage = $request->input('per_page', 50);
        $this->page = $request->input('page', 1);
        $this->start_date = $request->input('start_date');
        $this->end_date = $request->input('end_date');
        $this->status = $request->input('status');
        $this->customersTable = 'LG_' . $this->code . '_CLCARD';
        $this->salesmansTable = 'LG_SLSMAN';
        $this->whousesTable = 'L_CAPIWHOUSE';
        $this->ordersTable = 'LG_' . $this->code . '_01_ORFICHE';
        $this->unitsTable = 'LG_' . $this->code . '_UNITSETL';
        $this->ordersTransactionsTable = 'LG_' . $this->code . '_01_ORFLINE';
        $this->itemsTable = 'LG_' . $this->code . '_ITEMS';
        $this->weightsTable = 'LG_' . $this->code . '_ITMUNITA';
        $this->payplansTable = 'LG_' . $this->code . '_PAYPLANS';
        $this->cutoemrsView = 'LV_' . $this->code . '_01_CLCARD';
        $this->invoicesTable = 'LG_' . $this->code . '_01_INVOICE';
    }

    public function index(Request $request)
    {
        $order = DB::table("$this->salesmansTable")
            ->join("$this->ordersTable", "$this->ordersTable.salesmanref", "=", "lg_slsman.logicalref")
            ->join("$this->customersTable", "$this->customersTable.logicalref", "=", "$this->ordersTable.clientref")
            ->select(
                "$this->ordersTable.capiblock_creadeddate as order_date",
                "$this->ordersTable.ficheno as order_number",
                DB::raw("COALESCE($this->ordersTable.docode, '0') as document_number"),
                "$this->customersTable.definition_ as customer_name",
                "$this->customersTable.addr1 as customer_address",
                "$this->salesmansTable.code as salesman_code",
                "$this->ordersTable.nettotal as order_total_amount",
                "$this->ordersTable.status as order_status"
            )
            ->where(["$this->ordersTable.trcode" => $this->type]);
        if ($request->input('start_date')) {
            $start_date = Carbon::parse($request->input('start_date'))
                ->format('Y-m-d H:i:s');
        }

        if ($request->input('end_date')) {
            $end_date = Carbon::parse($request->input('end_date'))->format('Y-m-d H:i:s');
        }

        $this->applyFilters($order, [
            "$this->customersTable.code" => [
                'value' => '%' . $request->input('customer_code') . '%',
                'operator' => 'LIKE',
            ],
            "$this->customersTable.definition_" => [
                'value' => '%' . $request->input('customer_name') . '%',
                'operator' => 'LIKE',
            ],
            "$this->ordersTable.ficheno" => [
                'value' => '%' . $request->input('order_number') . '%',
                'operator' => 'LIKE',
            ],
            "$this->ordersTable.sourceindex" => [
                'value' => $request->input('warehouse_number'),
                'operator' => '=',
            ],
            "$this->ordersTable.capiblock_createdby" => [
                'value' => $request->input('added_by'),
                'operator' => '=',
            ],
            "$this->ordersTable.capiblock_modifiedby" => [
                'value' => $request->input('modified_by'),
                'operator' => '=',
            ],
            "$this->ordersTable.status" => [
                'value' => $request->input('status'),
                'operator' => '=',
            ],
        ]);

        if ($request->input('start_date') && $request->input('end_date')) {
            if ($this->start_date != '-1' && $this->end_date != '-1') {
                $order->whereBetween(DB::raw("CONVERT(date, $this->ordersTable.CAPIBLOCK_CREADEDDATE)"), [$this->start_date, $this->end_date]);
            }
        }
        $data = $order->orderBy("$this->ordersTable.capiblock_creadeddate", "desc")->paginate($this->perpage);
        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ], 200);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Order list',
            'data' => $data->items(),
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'next_page' => $data->nextPageUrl(),
            'previous_page' => $data->previousPageUrl(),
            'last_page' => $data->lastPage(),
            'total' => $data->total(),
        ], 200);
    }

    public function previousorders(Request $request)
    {
        $orders = DB::table($this->salesmansTable)
            ->join($this->ordersTable, "$this->ordersTable.salesmanref", "=", 'lg_slsman.logicalref')
            ->join($this->customersTable, "$this->ordersTable.clientref", "=", "$this->customersTable.logicalref")
            ->select(
                "$this->ordersTable.logicalref as order_id",
                "$this->ordersTable.ficheno as order_number",
                "$this->ordersTable.capiblock_creadeddate as order_date",
                "$this->ordersTable.status as order_status",
                "$this->ordersTable.nettotal as order_amount",
                "$this->customersTable.definition_ as customer_name"
            )
            ->whereMonth("$this->ordersTable.capiblock_creadeddate", '=', now()->month)
            ->where("$this->ordersTable.salesmanref", $this->salesman_id)
            ->orderby("$this->ordersTable.capiblock_creadeddate", "desc")
            ->get();
        if ($orders->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Salesman list',
            'data' => $orders,
        ]);
    }

    public function orderdetails(Request $request)
    {
        $order = $request->header('order');
        $order_id = $this->fetchValueFromTable("$this->ordersTable", "FICHENO", $order, "LOGICALREF");
        $info = DB::table("$this->ordersTransactionsTable")
            ->join("$this->itemsTable", "$this->ordersTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
            ->join("$this->salesmansTable", "$this->ordersTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
            ->join("$this->ordersTable", "$this->ordersTransactionsTable.ordficheref", "=", "$this->ordersTable.logicalref")
            ->join("$this->customersTable", "$this->ordersTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
            ->join("$this->cutoemrsView", "$this->cutoemrsView.logicalref", "=", "$this->customersTable.logicalref")
            ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->customersTable.paymentref")
            ->select(
                "$this->ordersTable.date_ as date",
                "$this->ordersTable.ficheno as number",
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
                "$this->customersTable.addr1 as customer_address",
                "$this->customersTable.telnrs1 as customer_phone",
                DB::raw("COALESCE($this->cutoemrsView.debit, 0) as debit"),
                DB::raw("COALESCE($this->cutoemrsView.credit, 0) as credit"),
                "$this->payplansTable.code as customer_payment_plan",
                "$this->salesmansTable.definition_ as salesman_name",
                "$this->ordersTable.grosstotal as order_total",
                "$this->ordersTable.totaldiscounts as order_discount",
                "$this->ordersTable.nettotal as order_net",
            )
            ->where(["$this->ordersTable.ficheno" => $order])
            ->distinct()
            ->first();

        $item = DB::table("$this->ordersTransactionsTable")
            ->select(
                "$this->ordersTransactionsTable.lineno_ as line",
                DB::raw("COALESCE($this->itemsTable.code, '0') as code"),
                DB::raw("COALESCE($this->itemsTable.name, '0') as name"),
                "$this->ordersTransactionsTable.amount as quantity",
                "$this->ordersTransactionsTable.price",
                "$this->ordersTransactionsTable.total",
                "$this->ordersTransactionsTable.distdisc as discount",
                "$this->weightsTable.grossweight as weight",
            )
            ->leftJoin("$this->itemsTable", "$this->itemsTable.logicalref", '=', "$this->ordersTransactionsTable.stockref")
            ->leftJoin("$this->weightsTable", function ($join) {
                $join->on("$this->ordersTransactionsTable.stockref", '=', "$this->weightsTable.itemref")
                    ->where("$this->weightsTable.linenr", '=', 1);
            })
            ->where("$this->ordersTransactionsTable.ordficheref", '=', $order_id)
            ->get();
        if ($item->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ], 200);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Order details',
            'order_info' => $info = (array) $info,
            'data' => $item,
        ]);
    }

    public function getOrderToBill($id)
    {
        $is_exist = DB::table("$this->ordersTable")->where('logicalref', $id)->first();
        if (!$is_exist) {
            return response()->json([
                'status' => 'failed',
                'status' => 'Order not found',
                'data' => [],
            ], 404);
        }
            $order = DB::table("$this->ordersTransactionsTable")
                // ->join("$this->itemsTable", "$this->ordersTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
                ->leftjoin("$this->salesmansTable", "$this->ordersTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
                // ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
                ->join("$this->ordersTable", "$this->ordersTransactionsTable.ordficheref", "=", "$this->ordersTable.logicalref")
                ->join("$this->customersTable", "$this->ordersTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
                // ->join("$this->cutoemrsView", "$this->cutoemrsView.logicalref", "=", "$this->customersTable.logicalref")
                ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->customersTable.paymentref")
                ->join("$this->whousesTable", "$this->whousesTable.nr", '=', "$this->ordersTransactionsTable.sourceindex")
                ->select(
                    "$this->customersTable.code as customer_code",
                    "$this->customersTable.definition_ as customer_name",
                    "$this->payplansTable.code as payment_code",
                    "$this->whousesTable.name as warehouse",
                    // "$this->ordersTable.date_ as order_date",
                    // "$this->ordersTable.ficheno as order_number",
                    DB::raw("COALESCE($this->salesmansTable.definition_, '') as salesman_name"),
                    DB::raw("COALESCE($this->ordersTable.totaldiscounts, 0) as total_discount"),
                    "$this->ordersTable.totaldiscounted as total_discounted",
                    "$this->ordersTable.nettotal as order_net_total"
                )
                ->where(["$this->ordersTable.logicalref" => $id,"$this->whousesTable.firmnr" => 888, "$this->salesmansTable.firmnr" => 888])
                ->first();
            $item = DB::table("$this->ordersTransactionsTable")
                ->leftjoin("$this->ordersTable", "$this->ordersTable.logicalref", "=", "$this->ordersTransactionsTable.ordficheref")
                ->leftjoin("$this->unitsTable", "$this->unitsTable.logicalref", "=", "$this->ordersTransactionsTable.uomref")
                ->leftjoin("$this->itemsTable", "$this->ordersTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
                ->select(
                    "$this->ordersTransactionsTable.lineno_ as line",
                    DB::raw("COALESCE($this->itemsTable.code, '') as code"),
                    DB::raw("COALESCE($this->itemsTable.name, '') as name"),
                    "$this->ordersTransactionsTable.amount as quantity",
                    DB::raw("COALESCE($this->unitsTable.name, '') as unit"),
                    "$this->ordersTransactionsTable.price as price",
                    "$this->ordersTransactionsTable.total as total",
                    "$this->ordersTable.ficheno as order_number",
                )
                ->where(["$this->ordersTransactionsTable.ordficheref" => $id])
                ->get();
            $data = [
                "invoice_number" => InvoiceNumberGenerator::generateInvoiceNumber($this->invoicesTable),
                "date" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
                "time" => TimeHelper::calculateTime(),
                "customer_code" => $order->customer_code,
                "customer_name" => $order->customer_name,
                "payment_paln" => $order->payment_code,
                "warehouse" => $order->warehouse,
                "salesman_name" => $order->salesman_name,
                "total_discount" => $order->total_discount,
                "total" => $order->total_discounted,
                "net_total" => $order->order_net_total,
                "items" => $item
            ];
            return response()->json([
                'status' => 'success',
                'data' => $data,
            ], 200);
        
    }

    public function ordersStatusFilter(Request $request)
    {
        $order = DB::table("$this->salesmansTable")
            ->join("$this->ordersTable", "$this->ordersTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join("$this->customersTable", "$this->customersTable.logicalref", "=", "$this->ordersTable.clientref")
            ->select(
                "$this->ordersTable.capiblock_creadeddate as order_date",
                "$this->ordersTable.ficheno as order_number",
                "$this->ordersTable.docode as invoice_number",
                "$this->customersTable.definition_ as customer_name",
                "$this->customersTable.addr1 as customer_address",
                "$this->salesmansTable.code as salesman_code",
                "$this->ordersTable.nettotal as order_total_amount",
                "$this->ordersTable.status as order_status"
            )
            ->orderby("$this->ordersTable.capiblock_creadeddate", "desc");
        if ($request->hasHeader('status')) {
            if ($this->status == -1) {
                $order->get();
            } else {
                $order->where(["$this->ordersTable.status" => $this->status]);
            }
        }
        $result = $order->paginate($this->perpage);
        if ($result->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ], 200);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Order list',
            'data' => $result->items(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'next_page' => $result->nextPageUrl($this->page),
            'previous_page' => $result->previousPageUrl($this->page),
            'last_page' => $result->lastPage(),
            'total' => $result->total(),
        ], 200);
    }

    public function OrderDateFilter(Request $request)
    {
        $order = DB::table("$this->salesmansTable")
            ->join("$this->ordersTable", "$this->ordersTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join("$this->customersTable", "$this->customersTable.logicalref", "=", "$this->ordersTable.clientref")
            ->select(
                DB::raw("CONVERT(date, $this->ordersTable.capiblock_creadeddate) as order_date"),
                "$this->ordersTable.ficheno as order_number",
                "$this->ordersTable.docode as invoice_number",
                "$this->customersTable.definition_ as customer_name",
                "$this->customersTable.addr1 as customer_address",
                'lg_slsman.code as salesman_code',
                "$this->ordersTable.nettotal as order_total_amount",
                "$this->ordersTable.status as order_status"
            )
            ->where('lg_slsman.firmnr', $this->code);
        if ($this->start_date !== '-1' && $this->end_date !== '-1') {
            $order->whereBetween(DB::raw("CONVERT(date, $this->ordersTable.CAPIBLOCK_CREADEDDATE)"), [$this->start_date, $this->end_date]);
        }
        $order->orderBy(DB::raw("CONVERT(date, $this->ordersTable.CAPIBLOCK_CREADEDDATE)"), "desc");
        $result = $order->paginate($this->perpage);
        if ($result->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ], 200);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Order list',
            'data' => $result->items(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'next_page' => $result->nextPageUrl($this->page),
            'previous_page' => $result->previousPageUrl($this->page),
            'last_page' => $result->lastPage(),
            'total' => $result->total(),
        ], 200);
    }

    public function salesmanlacurrentmonthorder(Request $request)
    {
        $order = DB::table("$this->ordersTable")
            ->join("$this->customersTable", "$this->customersTable.logicalref", "=", "$this->ordersTable.clientref")
            ->select(
                "$this->customersTable.definition_ as customer_name",
                "$this->ordersTable.ficheno as number",
                "$this->ordersTable.nettotal as total",
                "$this->ordersTable.status"
            )
            ->where(["$this->ordersTable.salesmanref" => $this->salesman_id])
            ->whereYear("$this->ordersTable.date_", "=", now()->year)
            ->whereMonth("$this->ordersTable.date_", "=", now()->month)
            ->orderby("$this->ordersTable.capiblock_creadeddate", "desc")
            ->get();
        if ($order->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'orders list',
            'data' => $order,
        ], 200);
    }

    public function previousorderdetails(Request $request)
    {
        $order = $request->header('order');
        $order_id = $this->fetchValueFromTable("$this->ordersTable", "FICHENO", $order, "LOGICALREF");
        $info = DB::table("$this->ordersTransactionsTable")
            ->join("$this->itemsTable", "$this->ordersTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
            ->join("$this->ordersTable", "$this->ordersTransactionsTable.ordficheref", "=", "$this->ordersTable.logicalref")
            ->leftjoin("$this->payplansTable", "$this->payplansTable.logicalref", "=", "$this->ordersTable.paydefref")
            ->select(
                "$this->ordersTable.logicalref as id",
                "$this->ordersTable.ficheno as number",
                "$this->ordersTable.grosstotal as order_amount",
                "$this->ordersTable.totaldiscounts as order_discount",
                "$this->ordersTable.nettotal as order_total",
                "$this->ordersTable.genexp1 as approved_by",
                "$this->ordersTable.printcnt as print_counter",
                "$this->ordersTable.printdate as print_date",
                "$this->payplansTable.code as payment_type"
            )
            ->where(["$this->ordersTable.ficheno" => $order])
            ->distinct()
            ->first();

        $item = DB::table("$this->ordersTransactionsTable")
            ->join("$this->itemsTable", "$this->ordersTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
            ->join("$this->ordersTable", "$this->ordersTransactionsTable.ordficheref", "=", "$this->ordersTable.logicalref")
            ->select(
                "$this->ordersTransactionsTable.lineno_ as line",
                "$this->ordersTable.capiblock_creadeddate as date",
                "$this->itemsTable.code as code",
                "$this->itemsTable.name as name",
                "$this->ordersTransactionsTable.amount as quantity",
                "$this->ordersTransactionsTable.price as price",
                "$this->ordersTransactionsTable.total as total",
                "$this->ordersTransactionsTable.distdisc as discount",
                "$this->weightsTable.grossweight as weight"
            )
            ->where(["$this->ordersTable.ficheno" => $order, "$this->weightsTable.linenr" => 1])
            ->get();
        if ($item->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ], 200);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Order details',
            'order_info' => $info = (array) $info,
            'data' => $item,
        ]);
    }

    public function increasePaper()
    {
        $id = request()->order_id;
        $number = request()->print_number;
        $date = request()->print_date;
        $tableName = $this->ordersTable;
        try {
            $result = PaperLimit::increaseNumber($tableName, $id, $number, $date);
            return response()->json([
                'status' => 'success',
                'data' => $result,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function store(Request $request)
    {
        $customer = $request->header('customer');
        $salesman = $request->header('salesman');
        $salesman_code = $this->fetchValueFromTable($this->salesmansTable, 'logicalref', $salesman, 'code');
        $payment_ref = $this->fetchValueFromTable($this->customersTable, 'code', $customer, 'paymentref');
        $data = [
            'INTERNAL_REFERENCE' => 0,
            'NUMBER' => '~',
            'DATE' => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            'TIME' => TimeHelper::calculateTime(),
            'ARP_CODE' => $customer,
            "TOTAL_DISCOUNTS" => $request->total_discounts,
            "TOTAL_DISCOUNTED" => $request->after_discount,
            "TOTAL_GROSS" => $request->before_discount,
            "TOTAL_NET" => $request->net_total,
            "RC_RATE" => 1,
            "RC_NET" => $request->net_total,
            "PAYMENT_CODE" => $request->payment_code,
            "ORDER_STATUS" => 1,
            "CREATED_BY" => request()->header('username'),
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "DATE_MODIFIED" => '',
            "HOUR_MODIFIED" => '',
            "MIN_MODIFIED" => '',
            "SEC_MODIFIED" => '',
            "SALESMAN_CODE" => $salesman_code,
            "AFFECT_RISK" => 1,
            "DEDUCTIONPART1" => 2,
            "DEDUCTIONPART2" => 3,
            "CURRSEL_TOTAL" => 1,
            "SOURCE_WH" => 3,
        ];
        $transactions = $request->input('TRANSACTIONS.items');
        foreach ($transactions as $item) {
            $item_type = $item['item_type'];
            $master_code = $item['item_code'];
            $quantity = $item['item_quantity'];
            $price = $item['item_price'];
            $total = $item['item_total'];
            $unit = isset($item['item_unit']) ? $item['item_unit'] : '';
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "TYPE" => $item_type,
                "MASTER_CODE" => $master_code,
                "QUANTITY" => $quantity,
                "PRICE" => $price,
                "TOTAL" => $total,
                "VAT_BASE" => $total,
                "DUE_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
                "TOTAL_NET" => $total,
                "SALESMAN_CODE" => $salesman_code,
                "MULTI_ADD_TAX" => 0,
                "AFFECT_RISK" => 1,
                "EDT_PRICE" => $total,
                "EDT_CURR" => 30,
                "SOURCE_WH" => 3,
            ];
            if ($item['item_type'] == 0) {
                $itemData["UNIT_CONV1"] = 1;
                $itemData["UNIT_CONV2"] = 1;
                $itemData["RESERVE_DATE"] = Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d');
                $itemData["RESERVE_AMOUNT"] = $quantity;
            } else {
                $itemData["UNIT_CONV1"] = 0;
                $itemData["UNIT_CONV2"] = 0;
                $itemData["CALC_TYPE"] = 1;
            }
            if ($unit) {
                $itemData["UNIT_CODE"] = $unit;
            } else {
                $itemData["UNIT_CODE"] = 0;
            }
            $data['TRANSACTIONS']['items'][] = $itemData;
        }


        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/salesOrders');
            $responseData = $response->json();
            $order = DB::table($this->ordersTable)
                ->select("logicalref as id", "FICHENO", "DATE_")
                ->where("LOGICALREF", $responseData['INTERNAL_REFERENCE'])
                ->first();
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $order,
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function edit($id)
    {
        $order = DB::table($this->ordersTable)->where('logicalref',$id)->first();
         if (!$order) {
            return response()->json([
                'status' => 'failed',
                'status' => 'Order is not exist',
                'data' => [],
            ], 404);
        }
        $info = DB::table("$this->ordersTransactionsTable")
            ->join("$this->itemsTable", "$this->ordersTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
            ->join("$this->salesmansTable", "$this->ordersTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
            ->join("$this->ordersTable", "$this->ordersTransactionsTable.ordficheref", "=", "$this->ordersTable.logicalref")
            ->join("$this->customersTable", "$this->ordersTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
            ->join("$this->cutoemrsView", "$this->cutoemrsView.logicalref", "=", "$this->customersTable.logicalref")
            ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->customersTable.paymentref")
            ->select(
                "$this->ordersTable.date_ as date",
                "$this->ordersTable.ficheno as number",
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
                "$this->payplansTable.code as customer_payment_plan",
                "$this->salesmansTable.definition_ as salesman_name",
                "$this->ordersTable.grosstotal as order_total",
                "$this->ordersTable.totaldiscounts as order_discount",
                "$this->ordersTable.nettotal as order_net",
            )
            ->where(["$this->ordersTable.logicalref" => $id])
            ->distinct()
            ->first();

        $item = DB::table("$this->ordersTransactionsTable")
            ->select(
                "$this->ordersTransactionsTable.lineno_ as line",
                DB::raw("COALESCE($this->itemsTable.code, '0') as code"),
                DB::raw("COALESCE($this->itemsTable.name, '0') as name"),
                "$this->ordersTransactionsTable.amount as quantity",
                "$this->ordersTransactionsTable.price",
                "$this->ordersTransactionsTable.total",
                "$this->ordersTransactionsTable.distdisc as discount",
                DB::raw("COALESCE($this->weightsTable.grossweight, '0') as weight"),
            )
            ->leftJoin("$this->itemsTable", "$this->itemsTable.logicalref", '=', "$this->ordersTransactionsTable.stockref")
            ->leftJoin("$this->weightsTable", function ($join) {
                $join->on("$this->ordersTransactionsTable.stockref", '=', "$this->weightsTable.itemref")
                    ->where("$this->weightsTable.linenr", '=', 1);
            })
            ->where("$this->ordersTransactionsTable.ordficheref", '=', $id)
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Order details',
            'order_info' => $info = (array) $info,
            'data' => $item,
        ]);
    }

    public function update(Request $request, $id)
    {
        $customer = $request->header('customer');
        $salesman = $request->header('salesman');
        $salesman_code = $this->fetchValueFromTable($this->salesmansTable, 'logicalref', $salesman, 'code');
        $payment_ref = $this->fetchValueFromTable($this->customersTable, 'code', $customer, 'paymentref');
        $existingRecord = DB::table($this->ordersTable)->where('logicalref', $id)->first();


        if (!$existingRecord) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Record not found',
            ], 404);
        }
        $data = [
            'INTERNAL_REFERENCE' => $existingRecord->LOGICALREF,
            'NUMBER' => $existingRecord->FICHENO,
            'ARP_CODE' => $customer,
            'TOTAL_DISCOUNTS' => $request->total_discounts,
            'TOTAL_DISCOUNTED' => $request->after_discount,
            'TOTAL_GROSS' => $request->before_discount,
            'TOTAL_NET' => $request->net_total,
            'RC_RATE' => 1,
            'RC_NET' => $request->net_total,
            'PAYMENT_CODE' => $request->payment_code,
            'DATE' => $existingRecord->DATE_,
            'CREATED_BY' => $existingRecord->CAPIBLOCK_CREATEDBY,
            'DATE_CREATED' => $existingRecord->CAPIBLOCK_CREADEDDATE,
            'HOUR_CREATED' => $existingRecord->CAPIBLOCK_CREATEDHOUR,
            'MIN_CREATED' => $existingRecord->CAPIBLOCK_CREATEDMIN,
            'SEC_CREATED' => $existingRecord->CAPIBLOCK_CREATEDSEC,
            'MODIFIED_BY' => request()->header('username'),
            'DATE_MODIFIED' => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            'HOUR_MODIFIED' => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            'MIN_MODIFIED' => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            'SEC_MODIFIED' => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            'SALESMAN_CODE' => $salesman_code,
            'AFFECT_RISK' => 1,
            'DEDUCTIONPART1' => 2,
            'DEDUCTIONPART2' => 3,
            'CURRSEL_TOTAL' => 1,
        ];

        $transactions = $request->input('TRANSACTIONS.items');
        foreach ($transactions as $item) {
            $item_type = $item['item_type'];
            $master_code = $item['item_code'];
            $quantity = $item['item_quantity'];
            $price = $item['item_price'];
            $total = $item['item_total'];
            $unit = isset($item['item_unit']) ? $item['item_unit'] : '';
            $itemData = [
                "TYPE" => $item_type,
                "MASTER_CODE" => $master_code,
                "QUANTITY" => $quantity,
                "PRICE" => $price,
                "TOTAL" => $total,
                "SALESMAN_CODE" => $salesman_code,
                "MULTI_ADD_TAX" => 0,
                "AFFECT_RISK" => 1,
                "EDT_CURR" => 30,
                "SOURCE_WH" => 3,
            ];
            if ($item['item_type'] == 0) {
                $itemData["UNIT_CONV1"] = 1;
                $itemData["UNIT_CONV2"] = 1;
                $itemData["RESERVE_DATE"] = Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d');
                $itemData["RESERVE_AMOUNT"] = $quantity;
            } else {
                $itemData["UNIT_CONV1"] = 0;
                $itemData["UNIT_CONV2"] = 0;
                $itemData["CALC_TYPE"] = 1;
            }
            if ($unit) {
                $itemData["UNIT_CODE"] = $unit;
            } else {
                $itemData["UNIT_CODE"] = 0;
            }
            $data['TRANSACTIONS']['items'][] = $itemData;
        }

        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->put("https://10.27.0.109:32002/api/v1/salesOrders/{$id}");

            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }


    public function acceptOrder($id)
    {
        try {
        $order = $this->fetchValueFromTable($this->ordersTable, 'logicalref', $id, 'date_');
        if (!$order) {
            return response()->json([
                'status' => 'status',
                'message' => 'Order is not exist',
                'data' => [],
            ], 404);
        }
        $data = [
            'DATE' => $order,
            'ORDER_STATUS' => 4,
            'APPROVE_DATE' => now()->format('y-m-d h:m:i'),
        ];
        
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->put("https://10.27.0.109:32002/api/v1/salesOrders/{$id}");

            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function rejectOrder($id)
    {
        try {
        $order = $this->fetchValueFromTable($this->ordersTable, 'logicalref', $id, 'date_');
        if (!$order) {
            return response()->json([
                'status' => 'status',
                'message' => 'Order is not exist',
                'data' => [],
            ], 404);
        }
        $data = [
            'DATE' => $order,
            'ORDER_STATUS' => 2,
        ];
        
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->put("https://10.27.0.109:32002/api/v1/salesOrders/{$id}");

            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy($id)
    {
       $order = $this->fetchValueFromTable($this->ordersTable, 'logicalref', $id, 'ficheno');
        if (!$order) {
            return response()->json([
                'status' => 'success',
                'message' => 'Order is not exist',
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
                ->delete("https://10.27.0.109:32002/api/v1/salesOrders/{$id}");

            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
