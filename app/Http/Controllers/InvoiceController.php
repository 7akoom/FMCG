<?php

namespace App\Http\Controllers;

use App\Helpers\InvoiceNumberGenerator;
use Throwable;
use App\Traits\Filterable;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;


class InvoiceController extends Controller
{

    use Filterable;

    protected $code;
    protected $type;
    protected $perpage;
    protected $page;
    protected $start_date;
    protected $end_date;
    protected $group_code;
    protected $transaction_code;
    protected $stock_number;
    protected $salesman_id;
    protected $customer_id;
    protected $customersTable;
    protected $cutomersView;
    protected $invoicesTable;
    protected $ordersTable;
    protected $dispatchesTable;
    protected $ledgerTable;
    protected $salesmansTable;
    protected $itemsTable;
    protected $weightsTable;
    protected $payplansTable;
    protected $stocksTransactionsTable;
    protected $customersTransactionsTable;
    protected $servicesTable;


    private function fetchValueFromTable($table, $column, $value1, $value2)
    {
        return DB::table($table)
            ->where($column, $value1)
            ->value($value2);
    }

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->type = $request->header('type');
        $this->salesman_id = $request->header('id');
        $this->customer_id = $request->header('customer-id');
        $this->perpage = $request->input('per_page', 50);
        $this->page = $request->input('page', 1);
        $this->start_date = $request->input('start_date');
        $this->end_date = $request->input('end_date');
        $this->group_code = $request->header('group-code');
        $this->transaction_code = $request->header('transaction-code');
        $this->stock_number = $request->header('stock-number');
        $this->salesmansTable = 'LG_SLSMAN';
        $this->customersTable = 'LG_' . $this->code . '_CLCARD';
        $this->cutomersView = 'LV_' . $this->code . '_01_CLCARD';
        $this->itemsTable = 'LG_' . $this->code . '_ITEMS';
        $this->servicesTable = 'LG_' . $this->code . '_SRVCARD';
        $this->weightsTable = 'LG_' . $this->code . '_ITMUNITA';
        $this->payplansTable = 'LG_' . $this->code . '_PAYPLANS';
        $this->invoicesTable = 'LG_' . $this->code . '_01_INVOICE';
        $this->ordersTable = 'LG_' . $this->code . '_01_ORFICHE';
        $this->dispatchesTable = 'LG_' . $this->code . '_01_STFICHE';
        $this->ledgerTable = 'LG_' . $this->code . '_01_PAYTRANS';
        $this->stocksTransactionsTable = 'LG_' . $this->code . '_01_STLINE';
        $this->customersTransactionsTable = 'LG_' . $this->code . '_01_CLFLINE';
    }

    // stock-number = null => all invoices, stock-number = 3 => preparing, stock-number = other value => shipping, stock-number = 0 => shipped
    public function index(Request $request)
    {
        $invoices = DB::table("$this->invoicesTable")
            ->leftjoin($this->salesmansTable, "$this->invoicesTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->leftjoin($this->customersTable, "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
            ->select(
                DB::raw("COALESCE($this->salesmansTable.definition_, '0') as salesman_name"),
                "$this->customersTable.logicalref as customer_id",
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
                "$this->invoicesTable.logicalref as invoice_id",
                "$this->invoicesTable.capiblock_creadeddate as invoice_date",
                "$this->invoicesTable.ficheno as invoice_number",
                "$this->invoicesTable.nettotal as total_amount",
                "$this->invoicesTable.docode as from_p_invoice",
                "$this->invoicesTable.trcode"
            )
            ->where([
                "$this->invoicesTable.grpcode" => 2,
            ]);
        if ($this->stock_number) {
            if ($this->stock_number == -1) {
                $invoices->where("$this->invoicesTable.sourceindex", 0);
            } else
                $invoices->where("$this->invoicesTable.sourceindex", '=', $this->stock_number);
        }
        $this->applyFilters($invoices, [
            "$this->customersTable.code" => [
                'value' => '%' . $request->input('customer_code') . '%',
                'operator' => 'LIKE',
            ],
            "$this->customersTable.definition_" => [
                'value' => '%' . $request->input('customer_name') . '%',
                'operator' => 'LIKE',
            ],
            "$this->invoicesTable.ficheno" => [
                'value' => '%' . $request->input('invoice_number') . '%',
                'operator' => 'LIKE',
            ],
            "$this->invoicesTable.sourceindex" => [
                'value' => $request->input('warehouse_number'),
                'operator' => '=',
            ],
            "$this->invoicesTable.capiblock_createdby" => [
                'value' => $request->input('added_by'),
                'operator' => '=',
            ],
            "$this->invoicesTable.capiblock_modifiedby" => [
                'value' => $request->input('modified_by'),
                'operator' => '=',
            ],
        ]);
        if ($request->input('start_date') && $request->input('end_date')) {
            $this->start_date = request()->input('start_date');
            $this->end_date = request()->input('end_date');
            if ($this->start_date != '-1' && $this->end_date != '-1') {
                $invoices->whereBetween(DB::raw("CONVERT(date, $this->invoicesTable.date_)"), [$this->start_date, $this->end_date]);
            }
        }

        $invoicesData = $invoices->orderBy("$this->invoicesTable.date_", "desc")->paginate($this->perpage);

        if ($invoicesData->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoices list',
            'total' => $invoicesData->total(),
            'data' => $invoicesData->items(),
            'current_page' => $invoicesData->currentPage(),
            'per_page' => $invoicesData->perPage(),
            'next_page' => $invoicesData->nextPageUrl($this->page),
            'previous_page' => $invoicesData->previousPageUrl($this->page),
            'last_page' => $invoicesData->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $creator = DB::table('L_CAPIUSER')->where('name', request()->header('username'))
            ->value('nr');
        $data = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 8,
            "NUMBER" => InvoiceNumberGenerator::generateInvoiceNumber($this->invoicesTable),
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            'TIME' => TimeHelper::calculateTime(),
            "ARP_CODE" => $request->customer_code,
            "SOURCE_WH" => $request->store_number,
            "POST_FLAGS" => 247,
            "VAT_RATE" => 18,
            "TOTAL_DISCOUNTS" => $request->total_discounts,
            "TOTAL_DISCOUNTED" => $request->after_discount,
            "TOTAL_GROSS" => $request->before_discount,
            "TOTAL_NET" => $request->net_total,
            "TC_NET" => $request->net_total,
            "RC_XRATE" => 1,
            "RC_NET" => $request->net_total,
            "NOTES1" => $request->notes,
            "DOC_NUMBER" => $request->document_number,
            "PAYMENT_CODE" => $request->payment_code,
            "CREATED_BY" => $creator,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SALESMAN_CODE" => $request->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
        ];
        $DISPATCHES = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 8,
            "NUMBER" => InvoiceNumberGenerator::generateInvoiceNumber($this->dispatchesTable),
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            'TIME' => $data['TIME'],
            "INVOICE_NUMBER" => $data['NUMBER'],
            "ARP_CODE" => $request->customer_code,
            "INVOICED" => 1,
            "TOTLA_DISCOUNTS" => $request->total_discounts,
            "TOTAL_DISCOUNTED" => $request->after_discount,
            "TOTAL_GROSS" => $request->before_discount,
            "TOTAL_NET" => $request->net_total,
            "RC_RATE" => 1,
            "RC_NET" => $request->net_total,
            "PAYMENT_CODE" => $request->payment_code,
            "CREATED_BY" => $creator,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SALESMANCODE" => $request->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DEDUCTIONPART1" => 2,
            "DEDUCTIONPART2" => 3,
            "AFFECT_RISK" => 1,
            "DISP_STATUS" => 1,
            "SHIP_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SHIP_TIME" => TimeHelper::calculateTime(),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "DOC_TIME" => TimeHelper::calculateTime(),
        ];
        $transactions = $request->input('TRANSACTIONS.items');
        foreach ($transactions as $item) {
            $type = $item['item_type'];
            $master_code = $item['item_code'];
            $quantity = $item['item_quantity'];
            $price = $item['item_price'];
            $total = $item['item_total'];
            $unit_code = $item['item_unit_code'];
            $salesman_code = $request->salesman_code;
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "TYPE" => $type,
                "MASTER_CODE" => $master_code,
                "SOURCEINDEX" => $request->store_number,
                "QUANTITY" => $quantity,
                "PRICE" => $price,
                "TOTAL" => $total,
                "RC_XRATE" => 1,
                "COST_DISTR" => $request->total_discounts,
                "DISCOUNT_DISTR" => $request->total_discounts,
                "UNIT_CODE" => $unit_code,
                "VAT_BASE" => $total - $request->total_discounts,
                "BILLED" => 1,
                "TOTAL_NET" => $total - $request->total_discounts,
                "DISPATCH_NUMBER" => $DISPATCHES['NUMBER'],
                "MULTI_ADD_TAX" => 0,
                "EDT_CURR" => 30,
                "EDT_PRICE" => $price,
                "SALEMANCODE" => $salesman_code,
                "MONTH" => Carbon::now()->timezone('Asia/Baghdad')->format('m'),
                "YEAR" => Carbon::now()->timezone('Asia/Baghdad')->format('Y'),
                "AFFECT_RISK" => 1,
                "FOREIGN_TRADE_TYPE" => 0,
                "DISTRIBUTION_TYPE_WHS" => 0,
                "DISTRIBUTION_TYPE_FNO" => 0,
            ];
            if ($item['item_type'] == 0) {
                $itemData["UNIT_CONV1"] = 1;
                $itemData["UNIT_CONV2"] = 1;
            } else {
                $itemData["DISCOUNT_RATE"] = ($request->total_discounts / $request->before_discount) * 100;
                $itemData["DISCEXP_CALC"] = 1;
                $itemData["UNIT_CONV1"] = 0;
                $itemData["UNIT_CONV2"] = 0;
            }
            $data['TRANSACTIONS']['items'][] = $itemData;
        }
        $PAYMENT = [
            "INTERNAL_REFERENCE" => 0,
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "MODULENR" => 4,
            "TRCODE" => 8,
            "TOTAL" => $request->net_total,
            "DAYS" => $request->payment_code,
            "PROCDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "REPORTRATE" => 1,
            "PAY_NO" => 1,
            "DISCTRDELLIST" => 0,
        ];
        $data['DISPATCHES']['items'][] = $DISPATCHES;
        $data['PAYMENT_LIST']['items'][] = $PAYMENT;
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
                ->post('https://10.27.0.109:32002/api/v1/salesInvoices');
            $json_resp = $response->getBody()->getContents();
            if ($response->status() === 400) {
                return response()->json([
                    'status' => 'failed',
                    'message' => $response['ModelState']['ValError0'],
                    'data' => []
                ], $response->status());
            }
            $resp = json_decode($json_resp, true);
            $customer = DB::table($this->customersTable)->where('code', $resp['ARP_CODE'])->first();
            $balance = DB::table($this->cutomersView)->where('code', $resp['ARP_CODE'])->first();
            $last_invoice = DB::table($this->invoicesTable)->where('logicalref', $resp['INTERNAL_REFERENCE'])->first();
            $stlines = DB::table("$this->stocksTransactionsTable as stline")
                ->select(
                    "stline.logicalref as id",
                    "stline.invoicelnno as item_line_number",
                    "stline.linetype as linetype",
                    DB::raw("COALESCE(item.code, '0') as code"),
                    DB::raw("COALESCE(item.name, '0') as name"),
                    "stline.amount",
                    "stline.price",
                    "stline.total",
                    "stline.distdisc as discount",
                    "weights.grossweight as item_weight",
                )
                ->leftJoin("$this->itemsTable as item", "item.logicalref", '=', "stline.stockref")
                ->leftJoin("$this->weightsTable as weights", function ($join) {
                    $join->on("stline.stockref", '=', "weights.itemref")
                        ->where("weights.linenr", '=', 1);
                })
                ->where("stline.invoiceref", '=', $resp['INTERNAL_REFERENCE'])
                ->get();

            $salesman_id = DB::table($this->salesmansTable)
                ->where(['code' => $resp['SALESMAN_CODE'], 'firmnr' => $this->code])
                ->select('definition_')
                ->first();

            if ($salesman_id) {
                $salesman_name = $salesman_id->definition_;
            } else {
                $salesman_name = "";
            }
            foreach ($stlines as $item) {
                $items[] = [
                    'id' => $item->id,
                    'item_line_number' => $item->item_line_number,
                    'type' => $item->linetype,
                    'code' => $item->code,
                    'name' => $item->name,
                    'quantity' => $item->amount,
                    'price' => $item->price,
                    'total' => $item->total,
                    'discount' => $item->discount,
                    'weight' => $item->amount * $item->item_weight,
                ];
            }
            $data = [
                'info' => [
                    'customer_code' => $resp['ARP_CODE'],
                    'customer_name' => $customer->DEFINITION_,
                    'customer_address' => $customer->ADDR1,
                    'invoice_number' => $resp['NUMBER'],
                    'salesman_name' => $salesman_name,
                    "before_discount" => $last_invoice->GROSSTOTAL,
                    "total_discount" => $last_invoice->TOTALDISCOUNTS,
                    "after_discount" => $last_invoice->TOTALDISCOUNTED,
                    "notes" => $last_invoice->GENEXP1,
                ],
                'items' => $items
            ];
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'invoice' => $data,
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function storeReturnInvoice(Request $request)
    {
        $creator = DB::table('L_CAPIUSER')->where('name', request()->header('username'))
            ->value('nr');
        $data = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 3,
            "NUMBER" => InvoiceNumberGenerator::generateInvoiceNumber($this->invoicesTable),
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            'TIME' => TimeHelper::calculateTime(),
            "ARP_CODE" => $request->customer_code,
            "SOURCE_WH" => $request->input('stock_number'),
            "POST_FLAGS" => 247,
            "VAT_RATE" => 18,
            "TOTAL_DISCOUNTS" => $request->total_discounts,
            "TOTAL_DISCOUNTED" => $request->after_discount,
            "TOTAL_GROSS" => $request->before_discount,
            "TOTAL_NET" => $request->net_total,
            "TC_NET" => $request->net_total,
            "RC_XRATE" => 1,
            "RC_NET" => $request->net_total,
            "NOTES1" => $request->notes,
            "DOC_NUMBER" => $request->document_number,
            "PAYMENT_CODE" => $request->payment_code,
            "CREATED_BY" => $creator,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SALESMAN_CODE" => $request->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DEDUCTIONPART1" => 2,
            "DEDUCTIONPART2" => 3,
            "AFFECT_RISK" => 1,
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
        ];
        $DISPATCHES = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 3,
            "NUMBER" => InvoiceNumberGenerator::generateInvoiceNumber($this->dispatchesTable),
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            'TIME' => $data['TIME'],
            "INVOICE_NUMBER" => $data['NUMBER'],
            "SOURCEINDEX" => $request->input('stock_number'),
            "ARP_CODE" => $request->customer_code,
            "INVOICED" => 1,
            "TOTLA_DISCOUNTS" => $request->total_discounts,
            "TOTAL_DISCOUNTED" => $request->after_discount,
            "TOTAL_GROSS" => $request->before_discount,
            "TOTAL_NET" => $request->net_total,
            "RC_RATE" => 1,
            "RC_NET" => $request->net_total,
            "PAYMENT_CODE" => $request->payment_code,
            "CREATED_BY" => $creator,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SALESMANCODE" => $request->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DEDUCTIONPART1" => 2,
            "DEDUCTIONPART2" => 3,
            "AFFECT_RISK" => 1,
            "DISP_STATUS" => 1,
            "SHIP_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SHIP_TIME" => TimeHelper::calculateTime(),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "DOC_TIME" => TimeHelper::calculateTime(),
        ];
        $transactions = $request->input('TRANSACTIONS.items');
        foreach ($transactions as $item) {
            $type = $item['item_type'];
            $master_code = $item['item_code'];
            $quantity = $item['item_quantity'];
            $price = $item['item_price'];
            $total = $item['item_total'];
            $unit_code = $item['item_unit_code'];
            $salesman_code = $request->salesman_code;
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "TYPE" => $type,
                "MASTER_CODE" => $master_code,
                "SOURCEINDEX" => $request->input('stock_number'),
                "QUANTITY" => $quantity,
                "PRICE" => $price,
                "TOTAL" => $total,
                "CURR_PRICE" => 30,
                "PC_PRICE" => $price,
                "RC_XRATE" => 1,
                "COST_DISTR" => $request->total_discounts,
                "DISCOUNT_DISTR" => $request->total_discounts,
                "UNIT_CODE" => $unit_code,
                "VAT_BASE" => $total - $request->total_discounts,
                "BILLED" => 1,
                "RET_COST_TYPE" => 1,
                "TOTAL_NET" => $total - $request->total_discounts,
                "DISPATCH_NUMBER" => $DISPATCHES['NUMBER'],
                "MULTI_ADD_TAX" => 0,
                "EDT_CURR" => 30,
                "EDT_PRICE" => $price,
                "SALEMANCODE" => $salesman_code,
                "MONTH" => Carbon::now()->timezone('Asia/Baghdad')->format('m'),
                "YEAR" => Carbon::now()->timezone('Asia/Baghdad')->format('Y'),
                "AFFECT_RISK" => 1,
                "FOREIGN_TRADE_TYPE" => 0,
                "DISTRIBUTION_TYPE_WHS" => 0,
                "DISTRIBUTION_TYPE_FNO" => 0,
            ];
            if ($item['item_type'] == 0) {
                $itemData["UNIT_CONV1"] = 1;
                $itemData["UNIT_CONV2"] = 1;
            } else {
                $itemData["DISCOUNT_RATE"] = ($request->total_discounts / $request->before_discount) * 100;
                $itemData["DISCEXP_CALC"] = 1;
                $itemData["UNIT_CONV1"] = 0;
                $itemData["UNIT_CONV2"] = 0;
            }
            $data['TRANSACTIONS']['items'][] = $itemData;
        }
        $PAYMENT = [
            "INTERNAL_REFERENCE" => 0,
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "MODULENR" => 4,
            "SIGN" => 1,
            "TRCODE" => 3,
            "TOTAL" => $request->net_total,
            // "DAYS" => $request->payment_code,
            "PROCDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "REPORTRATE" => 1,
            "PAY_NO" => 1,
            "DISCTRDELLIST" => 0,
        ];
        $data['DISPATCHES']['items'][] = $DISPATCHES;
        $data['PAYMENT_LIST']['items'][] = $PAYMENT;
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
                ->post('https://10.27.0.109:32002/api/v1/salesInvoices');
            $json_resp = $response->getBody()->getContents();
            // if ($response->status() === 400) {
            //     return response()->json([
            //         'status' => 'failed',
            //         'message' => $response['ModelState']['ValError0'],
            //         'data' => []
            //     ],$response->status());
            // }
            $resp = json_decode($json_resp, true);
            $customer = DB::table($this->customersTable)->where('code', $resp['ARP_CODE'])->first();
            $balance = DB::table($this->cutomersView)->where('code', $resp['ARP_CODE'])->first();
            $last_invoice = DB::table($this->invoicesTable)->where('logicalref', $resp['INTERNAL_REFERENCE'])->first();
            $stlines = DB::table("$this->stocksTransactionsTable as stline")
                ->select(
                    "stline.logicalref as id",
                    "stline.invoicelnno as item_line_number",
                    "stline.linetype as linetype",
                    DB::raw("COALESCE(item.code, '0') as code"),
                    DB::raw("COALESCE(item.name, '0') as name"),
                    "stline.amount",
                    "stline.price",
                    "stline.total",
                    "stline.distdisc as discount",
                    "weights.grossweight as item_weight",
                )
                ->leftJoin("$this->itemsTable as item", "item.logicalref", '=', "stline.stockref")
                ->leftJoin("$this->weightsTable as weights", function ($join) {
                    $join->on("stline.stockref", '=', "weights.itemref")
                        ->where("weights.linenr", '=', 1);
                })
                ->where("stline.invoiceref", '=', $resp['INTERNAL_REFERENCE'])
                ->get();

            $salesman_id = DB::table($this->salesmansTable)
                ->where(['code' => $resp['SALESMAN_CODE'], 'firmnr' => $this->code])
                ->select('definition_')
                ->first();

            if ($salesman_id) {
                $salesman_name = $salesman_id->definition_;
            } else {
                $salesman_name = "";
            }
            foreach ($stlines as $item) {
                $items[] = [
                    'id' => $item->id,
                    'item_line_number' => $item->item_line_number,
                    'type' => $item->linetype,
                    'code' => $item->code,
                    'name' => $item->name,
                    'quantity' => $item->amount,
                    'price' => $item->price,
                    'total' => $item->total,
                    'discount' => $item->discount,
                    'weight' => $item->amount * $item->item_weight,
                ];
            }
            $data = [
                'info' => [
                    'customer_code' => $resp['ARP_CODE'],
                    'customer_name' => $customer->DEFINITION_,
                    'customer_address' => $customer->ADDR1,
                    'invoice_number' => $resp['NUMBER'],
                    'salesman_name' => $salesman_name,
                    "before_discount" => $last_invoice->GROSSTOTAL,
                    "total_discount" => $last_invoice->TOTALDISCOUNTS,
                    "after_discount" => $last_invoice->TOTALDISCOUNTED,
                    "notes" => $last_invoice->GENEXP1,
                ],
                'items' => $items
            ];
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'invoice' => $data,
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    public function billOrder(Request $request, $orderId)
    {
        $creator = DB::table('L_CAPIUSER')->where('name', request()->header('username'))
            ->value('nr');
        $order = DB::table("$this->ordersTable")->where('logicalref', $orderId)
            ->value('date_');
        $number = DB::table("$this->ordersTable")->where('logicalref', $orderId)
            ->value('ficheno');
        $data = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 8,
            "NUMBER" => InvoiceNumberGenerator::generateInvoiceNumber($this->invoicesTable),
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            'TIME' => TimeHelper::calculateTime(),
            "ARP_CODE" => $request->customer_code,
            "SOURCE_WH" => 3,
            "POST_FLAGS" => 247,
            "VAT_RATE" => 18,
            "TOTAL_DISCOUNTS" => $request->total_discounts,
            "TOTAL_DISCOUNTED" => $request->after_discount,
            "TOTAL_GROSS" => $request->before_discount,
            "TOTAL_NET" => $request->net_total,
            "TC_NET" => $request->net_total,
            "RC_XRATE" => 1,
            "RC_NET" => $request->net_total,
            "NOTES1" => $request->notes,
            "PAYMENT_CODE" => $request->payment_code,
            "CREATED_BY" => $creator,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SALESMAN_CODE" => $request->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
        ];
        $DISPATCHES = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 8,
            "NUMBER" => InvoiceNumberGenerator::generateInvoiceNumber($this->dispatchesTable),
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            'TIME' => $data['TIME'],
            "INVOICE_NUMBER" => $data['NUMBER'],
            "ARP_CODE" => $request->customer_code,
            "INVOICED" => 1,
            "TOTLA_DISCOUNTS" => $request->total_discounts,
            "TOTAL_DISCOUNTED" => $request->after_discount,
            "TOTAL_GROSS" => $request->before_discount,
            "TOTAL_NET" => $request->net_total,
            "RC_RATE" => 1,
            "RC_NET" => $request->net_total,
            "PAYMENT_CODE" => $request->payment_code,
            "CREATED_BY" => $creator,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SALESMANCODE" => $request->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DEDUCTIONPART1" => 2,
            "DEDUCTIONPART2" => 3,
            "AFFECT_RISK" => 1,
            "DISP_STATUS" => 1,
            "SHIP_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SHIP_TIME" => TimeHelper::calculateTime(),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "DOC_TIME" => TimeHelper::calculateTime(),
        ];
        $transactions = $request->input('TRANSACTIONS.items');
        foreach ($transactions as $item) {
            $type = $item['item_type'];
            $master_code = $item['item_code'];
            $quantity = $item['item_quantity'];
            $price = $item['item_price'];
            $total = $item['item_total'];
            $unit_code = $item['item_unit_code'];
            $salesman_code = $request->salesman_code;
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "TYPE" => $type,
                "MASTER_CODE" => $master_code,
                "SOURCEINDEX" => 3,
                "QUANTITY" => $quantity,
                "PRICE" => $price,
                "TOTAL" => $total,
                "RC_XRATE" => 1,
                "COST_DISTR" => $request->total_discounts,
                "DISCOUNT_DISTR" => $request->total_discounts,
                "UNIT_CODE" => $unit_code,
                "VAT_BASE" => $total - $request->total_discounts,
                "BILLED" => 1,
                "TOTAL_NET" => $total - $request->total_discounts,
                "DISPATCH_NUMBER" => $DISPATCHES['NUMBER'],
                "MULTI_ADD_TAX" => 0,
                "EDT_CURR" => 30,
                "EDT_PRICE" => $price,
                "SALEMANCODE" => $salesman_code,
                "MONTH" => Carbon::now()->timezone('Asia/Baghdad')->format('m'),
                "YEAR" => Carbon::now()->timezone('Asia/Baghdad')->format('Y'),
                "AFFECT_RISK" => 1,
                "FOREIGN_TRADE_TYPE" => 0,
                "DISTRIBUTION_TYPE_WHS" => 0,
                "DISTRIBUTION_TYPE_FNO" => 0,
            ];
            if ($item['item_type'] == 0) {
                $itemData["UNIT_CONV1"] = 1;
                $itemData["UNIT_CONV2"] = 1;
            } else {
                $itemData["DISCOUNT_RATE"] = ($request->total_discounts / $request->before_discount) * 100;
                $itemData["DISCEXP_CALC"] = 1;
                $itemData["UNIT_CONV1"] = 0;
                $itemData["UNIT_CONV2"] = 0;
            }
            $data['TRANSACTIONS']['items'][] = $itemData;
        }
        $PAYMENT = [
            "INTERNAL_REFERENCE" => 0,
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "MODULENR" => 4,
            "TRCODE" => 8,
            "TOTAL" => $request->net_total,
            "DAYS" => $request->payment_code,
            "PROCDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "REPORTRATE" => 1,
            "PAY_NO" => 1,
            "DISCTRDELLIST" => 0,
        ];
        $data['DISPATCHES']['items'][] = $DISPATCHES;
        $data['PAYMENT_LIST']['items'][] = $PAYMENT;
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
                ->post('https://10.27.0.109:32002/api/v1/salesInvoices');
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'invoice' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function edit($id)
    {
        $invoice = DB::table($this->invoicesTable)->where('logicalref', $id)->first();
        if (!$invoice) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invoice is not exist',
                'data' => [],
            ], 404);
        }
        $info = DB::table("$this->stocksTransactionsTable")
            ->join("$this->itemsTable", "$this->stocksTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
            ->join("$this->salesmansTable", "$this->stocksTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
            ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
            ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
            ->join("$this->cutomersView", "$this->cutomersView.logicalref", "=", "$this->customersTable.logicalref")
            ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->invoicesTable.paydefref")
            ->select(
                "$this->invoicesTable.capiblock_creadeddate as date",
                "$this->invoicesTable.ficheno as number",
                "$this->invoicesTable.grosstotal as invoice_amount",
                "$this->invoicesTable.totaldiscounts as invoice_discount",
                "$this->invoicesTable.nettotal as invoice_total",
                "$this->salesmansTable.definition_ as salesman_name",
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
                "$this->payplansTable.code as payment_plan",
            )
            ->where(["$this->invoicesTable.logicalref" => $id])
            ->distinct()
            ->first();
        $item = DB::table("$this->stocksTransactionsTable")
            ->select(
                "$this->stocksTransactionsTable.invoicelnno as line",
                DB::raw("COALESCE($this->itemsTable.code, '0') as code"),
                DB::raw("COALESCE($this->itemsTable.name, '0') as name"),
                "$this->stocksTransactionsTable.amount as quantity",
                "$this->stocksTransactionsTable.price as price",
                "$this->stocksTransactionsTable.total as total",
                "$this->stocksTransactionsTable.distdisc as discount",
                DB::raw("COALESCE($this->weightsTable.grossweight, '0') as weight"),
            )
            ->leftjoin("$this->itemsTable", "$this->stocksTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
            ->leftJoin("$this->weightsTable", function ($join) {
                $join->on("$this->stocksTransactionsTable.stockref", '=', "$this->weightsTable.itemref")
                    ->where("$this->weightsTable.linenr", '=', 1);
            })
            ->where(["$this->stocksTransactionsTable.invoiceref" => $id])
            ->orderby("$this->stocksTransactionsTable.invoicelnno", "asc")
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice details',
            'invoice_info' => $info = (array) $info,
            'data' => $item,
        ]);
    }

    public function update($id)
    {
        $invoice = DB::table($this->invoicesTable)->where('logicalref', $id)->first();
        $dispatch = DB::table($this->dispatchesTable)->where('invoiceref', $id)->first();
        $payment = DB::table($this->ledgerTable)->where('ficheref', $id)->first();
        if (!$invoice) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invoice is not exist',
                'data' => [],
            ], 404);
        }
        $data = [
            "INTERNAL_REFERENCE" => $invoice->LOGICALREF,
            "DATE" => $invoice->DATE_,
            'TIME' => $invoice->TIME_,
            "ARP_CODE" => request()->customer_code,
            "SOURCE_WH" => 3,
            "POST_FLAGS" => 247,
            "VAT_RATE" => 18,
            "TOTAL_DISCOUNTS" => request()->total_discounts,
            "TOTAL_DISCOUNTED" => request()->after_discount,
            "TOTAL_GROSS" => request()->before_discount,
            "TOTAL_NET" => request()->net_total,
            "TC_NET" => request()->net_total,
            "RC_XRATE" => 1,
            "RC_NET" => request()->net_total,
            "NOTES1" => request()->notes,
            "PAYMENT_CODE" => request()->payment_code,
            "CREATED_BY" => $invoice->CAPIBLOCK_CREATEDBY,
            "DATE_CREATED" => $invoice->CAPIBLOCK_CREADEDDATE,
            "HOUR_CREATED" => $invoice->CAPIBLOCK_CREATEDHOUR,
            "MIN_CREATED" => $invoice->CAPIBLOCK_CREATEDMIN,
            "SEC_CREATED" => $invoice->CAPIBLOCK_CREATEDSEC,
            "SALESMAN_CODE" => request()->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DOC_DATE" => $invoice->DOCDATE,
        ];
        $DISPATCHES = [
            "INTERNAL_REFERENCE" => $dispatch->LOGICALREF,
            "DATE" => $dispatch->DATE_,
            'TIME' => $dispatch->FTIME,
            "ARP_CODE" => request()->customer_code,
            "INVOICED" => 1,
            "TOTLA_DISCOUNTS" => request()->total_discounts,
            "TOTAL_DISCOUNTED" => request()->after_discount,
            "TOTAL_GROSS" => request()->before_discount,
            "TOTAL_NET" => request()->net_total,
            "RC_RATE" => 1,
            "RC_NET" => request()->net_total,
            "PAYMENT_CODE" => request()->payment_code,
            "CREATED_BY" => $dispatch->CAPIBLOCK_CREATEDBY,
            "DATE_CREATED" => $dispatch->CAPIBLOCK_CREADEDDATE,
            "HOUR_CREATED" => $dispatch->CAPIBLOCK_CREATEDHOUR,
            "MIN_CREATED" => $dispatch->CAPIBLOCK_CREATEDMIN,
            "SEC_CREATED" => $dispatch->CAPIBLOCK_CREATEDSEC,
            "SALESMANCODE" => request()->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DEDUCTIONPART1" => 2,
            "DEDUCTIONPART2" => 3,
            "AFFECT_RISK" => 1,
            "DISP_STATUS" => 1,
            "SHIP_DATE" => $dispatch->SHIPDATE,
            "SHIP_TIME" => $dispatch->SHIPTIME,
            "DOC_DATE" => $dispatch->DOCDATE,
            "DOC_TIME" => $dispatch->DOCTIME,
        ];
        $transactions = request()->input('TRANSACTIONS.items');
        foreach ($transactions as $item) {
            $line_number = $item['item_line_number'];
            $type = $item['item_type'];
            $master_code = $item['item_code'];
            $quantity = $item['item_quantity'];
            $price = $item['item_price'];
            $total = $item['item_total'];
            $unit_code = $item['item_unit_code'];
            $salesman_code = request()->salesman_code;
            $itemData = [
                "TYPE" => $type,
                "MASTER_CODE" => $master_code,
                "INVOICELNNO" => $line_number,
                "SOURCEINDEX" => 3,
                "QUANTITY" => $quantity,
                "PRICE" => $price,
                "TOTAL" => $total,
                "RC_XRATE" => 1,
                "COST_DISTR" => request()->total_discounts,
                "DISCOUNT_DISTR" => request()->total_discounts,
                "UNIT_CODE" => $unit_code,
                "VAT_BASE" => $total - request()->total_discounts,
                "BILLED" => 1,
                "TOTAL_NET" => $total - request()->total_discounts,
                "DISPATCH_NUMBER" => $dispatch->FICHENO,
                "MULTI_ADD_TAX" => 0,
                "EDT_CURR" => 30,
                "EDT_PRICE" => $price,
                "SALEMANCODE" => $salesman_code,
                "AFFECT_RISK" => 1,
                "FOREIGN_TRADE_TYPE" => 0,
                "DISTRIBUTION_TYPE_WHS" => 0,
                "DISTRIBUTION_TYPE_FNO" => 0,
            ];
            if ($item['item_type'] == 0) {
                $itemData["UNIT_CONV1"] = 1;
                $itemData["UNIT_CONV2"] = 1;
            } else {
                $itemData["DISCOUNT_RATE"] = (request()->total_discounts / request()->before_discount) * 100;
                $itemData["DISCEXP_CALC"] = 1;
                $itemData["UNIT_CONV1"] = 0;
                $itemData["UNIT_CONV2"] = 0;
            }
            $data['TRANSACTIONS']['items'][] = $itemData;
        }
        if ($payment) {
            $PAYMENT = [
                "INTERNAL_REFERENCE" => $payment->LOGICALREF,
                "DATE" => $payment->DATE_,
                "MODULENR" => 4,
                "TRCODE" => 8,
                "TOTAL" => $payment->TOTAL,
                "PROCDATE" => $payment->PROCDATE,
                "REPORTRATE" => 1,
                "PAY_NO" => 1,
                "DISCTRDELLIST" => 0,
            ];
            $data['PAYMENT_LIST']['items'][] = $PAYMENT;
        }
        $data['DISPATCHES']['items'][] = $DISPATCHES;
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
                ->put("https://10.27.0.109:32002/api/v1/salesInvoices/{$id}");
            $info = DB::table("$this->stocksTransactionsTable")
                ->join("$this->itemsTable", "$this->stocksTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
                ->join("$this->salesmansTable", "$this->stocksTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
                ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
                ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
                ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
                ->join("$this->cutomersView", "$this->cutomersView.logicalref", "=", "$this->customersTable.logicalref")
                ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->invoicesTable.paydefref")
                ->select(
                    "$this->customersTable.code as customer_code",
                    "$this->customersTable.definition_ as customer_name",
                    "$this->customersTable.addr1 as customer_address",
                    "$this->invoicesTable.ficheno as invoice_number",
                    DB::raw("COALESCE($this->invoicesTable.genexp1,' ') as notes"),
                    DB::raw("COALESCE($this->salesmansTable.definition_,'0') as salesman_name"),
                    "$this->invoicesTable.grosstotal as before_discount",
                    "$this->invoicesTable.totaldiscounts as total_discount",
                    "$this->invoicesTable.totaldiscounted as after_discount",

                )
                ->where(["$this->invoicesTable.logicalref" => $id])
                ->distinct()
                ->first();

            $item = DB::table("$this->stocksTransactionsTable")
                ->select(
                    "$this->stocksTransactionsTable.logicalref as id",
                    "$this->stocksTransactionsTable.invoicelnno as item_line_number",
                    "$this->stocksTransactionsTable.linetype as type",
                    DB::raw("COALESCE($this->itemsTable.code, '0') as code"),
                    DB::raw("COALESCE($this->itemsTable.name, '0') as name"),
                    "$this->stocksTransactionsTable.amount as quantity",
                    "$this->stocksTransactionsTable.price",
                    "$this->stocksTransactionsTable.total",
                    "$this->stocksTransactionsTable.distdisc as discount",
                    DB::raw("COALESCE($this->weightsTable.grossweight, '0') as weight"),
                )
                ->leftJoin("$this->itemsTable", "$this->itemsTable.logicalref", '=', "$this->stocksTransactionsTable.stockref")
                ->leftJoin("$this->weightsTable", function ($join) {
                    $join->on("$this->stocksTransactionsTable.stockref", '=', "$this->weightsTable.itemref")
                        ->where("$this->weightsTable.linenr", '=', 1);
                })
                ->where("$this->stocksTransactionsTable.invoiceref", '=', $id)
                ->get();

            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'message' => 'Invoice updated successfully',
                'invoice_info' => $info = (array) $info,
                'data' => $item,
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function prepare($id)
    {
        $invoice = DB::table($this->invoicesTable)->where('logicalref', $id)->first();
        $dispatch = DB::table($this->dispatchesTable)->where('invoiceref', $id)->first();
        $payment = DB::table($this->ledgerTable)->where('ficheref', $id)->first();
        if (!$invoice) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invoice is not exist',
                'data' => [],
            ], 404);
        }
        $data = [
            "INTERNAL_REFERENCE" => $invoice->LOGICALREF,
            "DATE" => $invoice->DATE_,
            'TIME' => $invoice->TIME_,
            "ARP_CODE" => request()->customer_code,
            "SOURCE_WH" => request()->stock_number,
            "POST_FLAGS" => 247,
            "VAT_RATE" => 18,
            "TOTAL_DISCOUNTS" => request()->total_discounts,
            "TOTAL_DISCOUNTED" => request()->after_discount,
            "TOTAL_GROSS" => request()->before_discount,
            "TOTAL_NET" => request()->net_total,
            "TC_NET" => request()->net_total,
            "RC_XRATE" => 1,
            "RC_NET" => request()->net_total,
            "NOTES1" => request()->notes,
            "PAYMENT_CODE" => request()->payment_code,
            "CREATED_BY" => $invoice->CAPIBLOCK_CREATEDBY,
            "DATE_CREATED" => $invoice->CAPIBLOCK_CREADEDDATE,
            "HOUR_CREATED" => $invoice->CAPIBLOCK_CREATEDHOUR,
            "MIN_CREATED" => $invoice->CAPIBLOCK_CREATEDMIN,
            "SEC_CREATED" => $invoice->CAPIBLOCK_CREATEDSEC,
            "SALESMAN_CODE" => request()->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DOC_DATE" => $invoice->DOCDATE,
        ];
        $DISPATCHES = [
            "INTERNAL_REFERENCE" => $dispatch->LOGICALREF,
            "DATE" => $dispatch->DATE_,
            'TIME' => $dispatch->FTIME,
            "SOURCE_WH" => request()->stock_number,
            "ARP_CODE" => request()->customer_code,
            "INVOICED" => 1,
            "TOTLA_DISCOUNTS" => request()->total_discounts,
            "TOTAL_DISCOUNTED" => request()->after_discount,
            "TOTAL_GROSS" => request()->before_discount,
            "TOTAL_NET" => request()->net_total,
            "RC_RATE" => 1,
            "RC_NET" => request()->net_total,
            "PAYMENT_CODE" => request()->payment_code,
            "CREATED_BY" => $dispatch->CAPIBLOCK_CREATEDBY,
            "DATE_CREATED" => $dispatch->CAPIBLOCK_CREADEDDATE,
            "HOUR_CREATED" => $dispatch->CAPIBLOCK_CREATEDHOUR,
            "MIN_CREATED" => $dispatch->CAPIBLOCK_CREATEDMIN,
            "SEC_CREATED" => $dispatch->CAPIBLOCK_CREATEDSEC,
            "SALESMANCODE" => request()->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DEDUCTIONPART1" => 2,
            "DEDUCTIONPART2" => 3,
            "AFFECT_RISK" => 1,
            "DISP_STATUS" => 1,
            "SHIP_DATE" => $dispatch->SHIPDATE,
            "SHIP_TIME" => $dispatch->SHIPTIME,
            "DOC_DATE" => $dispatch->DOCDATE,
            "DOC_TIME" => $dispatch->DOCTIME,
        ];
        $transactions = request()->input('TRANSACTIONS.items');
        foreach ($transactions as $item) {
            $type = $item['item_type'];
            $master_code = $item['item_code'];
            $quantity = $item['item_quantity'];
            $price = $item['item_price'];
            $total = $item['item_total'];
            $unit_code = $item['item_unit_code'];
            $salesman_code = request()->salesman_code;
            $itemData = [
                "TYPE" => $type,
                "MASTER_CODE" => $master_code,
                "SOURCE_WH" => request()->stock_number,
                "QUANTITY" => $quantity,
                "PRICE" => $price,
                "TOTAL" => $total,
                "RC_XRATE" => 1,
                "COST_DISTR" => request()->total_discounts,
                "DISCOUNT_DISTR" => request()->total_discounts,
                "UNIT_CODE" => $unit_code,
                "VAT_BASE" => $total - request()->total_discounts,
                "BILLED" => 1,
                "TOTAL_NET" => $total - request()->total_discounts,
                "DISPATCH_NUMBER" => $dispatch->FICHENO,
                "MULTI_ADD_TAX" => 0,
                "EDT_CURR" => 30,
                "EDT_PRICE" => $price,
                "SALEMANCODE" => $salesman_code,
                "AFFECT_RISK" => 1,
                "FOREIGN_TRADE_TYPE" => 0,
                "DISTRIBUTION_TYPE_WHS" => 0,
                "DISTRIBUTION_TYPE_FNO" => 0,
            ];
            if ($item['item_type'] == 0) {
                $itemData["UNIT_CONV1"] = 1;
                $itemData["UNIT_CONV2"] = 1;
            } else {
                $itemData["DISCOUNT_RATE"] = (request()->total_discounts / request()->before_discount) * 100;
                $itemData["DISCEXP_CALC"] = 1;
                $itemData["UNIT_CONV1"] = 0;
                $itemData["UNIT_CONV2"] = 0;
            }
            $data['TRANSACTIONS']['items'][] = $itemData;
        }
        $PAYMENT = [
            "INTERNAL_REFERENCE" => $payment->LOGICALREF,
            "DATE" => $payment->DATE_,
            "MODULENR" => 4,
            "TRCODE" => 8,
            "TOTAL" => $payment->TOTAL,
            "PROCDATE" => $payment->PROCDATE,
            "REPORTRATE" => 1,
            "PAY_NO" => 1,
            "DISCTRDELLIST" => 0,
        ];
        $data['DISPATCHES']['items'][] = $DISPATCHES;
        $data['PAYMENT_LIST']['items'][] = $PAYMENT;
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
                ->put("https://10.27.0.109:32002/api/v1/salesInvoices/{$id}");
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'invoice' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function deliver($id)
    {
        $invoice = DB::table($this->invoicesTable)->where('logicalref', $id)->first();
        $dispatch = DB::table($this->dispatchesTable)->where('invoiceref', $id)->first();
        $payment = DB::table($this->ledgerTable)->where('ficheref', $id)->first();
        if (!$invoice) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invoice is not exist',
                'data' => [],
            ], 404);
        }
        $data = [
            "INTERNAL_REFERENCE" => $invoice->LOGICALREF,
            "DATE" => $invoice->DATE_,
            'TIME' => $invoice->TIME_,
            "ARP_CODE" => request()->customer_code,
            "SOURCE_WH" => 0,
            "POST_FLAGS" => 247,
            "VAT_RATE" => 18,
            "TOTAL_DISCOUNTS" => request()->total_discounts,
            "TOTAL_DISCOUNTED" => request()->after_discount,
            "TOTAL_GROSS" => request()->before_discount,
            "TOTAL_NET" => request()->net_total,
            "TC_NET" => request()->net_total,
            "RC_XRATE" => 1,
            "RC_NET" => request()->net_total,
            "NOTES1" => request()->notes,
            "PAYMENT_CODE" => request()->payment_code,
            "CREATED_BY" => $invoice->CAPIBLOCK_CREATEDBY,
            "DATE_CREATED" => $invoice->CAPIBLOCK_CREADEDDATE,
            "HOUR_CREATED" => $invoice->CAPIBLOCK_CREATEDHOUR,
            "MIN_CREATED" => $invoice->CAPIBLOCK_CREATEDMIN,
            "SEC_CREATED" => $invoice->CAPIBLOCK_CREATEDSEC,
            "SALESMAN_CODE" => request()->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DOC_DATE" => $invoice->DOCDATE,
        ];
        $DISPATCHES = [
            "INTERNAL_REFERENCE" => $dispatch->LOGICALREF,
            "DATE" => $dispatch->DATE_,
            'TIME' => $dispatch->FTIME,
            "SOURCE_WH" => 0,
            "ARP_CODE" => request()->customer_code,
            "INVOICED" => 1,
            "TOTLA_DISCOUNTS" => request()->total_discounts,
            "TOTAL_DISCOUNTED" => request()->after_discount,
            "TOTAL_GROSS" => request()->before_discount,
            "TOTAL_NET" => request()->net_total,
            "RC_RATE" => 1,
            "RC_NET" => request()->net_total,
            "PAYMENT_CODE" => request()->payment_code,
            "CREATED_BY" => $dispatch->CAPIBLOCK_CREATEDBY,
            "DATE_CREATED" => $dispatch->CAPIBLOCK_CREADEDDATE,
            "HOUR_CREATED" => $dispatch->CAPIBLOCK_CREATEDHOUR,
            "MIN_CREATED" => $dispatch->CAPIBLOCK_CREATEDMIN,
            "SEC_CREATED" => $dispatch->CAPIBLOCK_CREATEDSEC,
            "SALESMANCODE" => request()->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "DEDUCTIONPART1" => 2,
            "DEDUCTIONPART2" => 3,
            "AFFECT_RISK" => 1,
            "DISP_STATUS" => 1,
            "SHIP_DATE" => $dispatch->SHIPDATE,
            "SHIP_TIME" => $dispatch->SHIPTIME,
            "DOC_DATE" => $dispatch->DOCDATE,
            "DOC_TIME" => $dispatch->DOCTIME,
        ];
        $transactions = request()->input('TRANSACTIONS.items');
        foreach ($transactions as $item) {
            $type = $item['item_type'];
            $master_code = $item['item_code'];
            $quantity = $item['item_quantity'];
            $price = $item['item_price'];
            $total = $item['item_total'];
            $unit_code = $item['item_unit_code'];
            $salesman_code = request()->salesman_code;
            $itemData = [
                "TYPE" => $type,
                "MASTER_CODE" => $master_code,
                "SOURCE_WH" => 0,
                "QUANTITY" => $quantity,
                "PRICE" => $price,
                "TOTAL" => $total,
                "RC_XRATE" => 1,
                "COST_DISTR" => request()->total_discounts,
                "DISCOUNT_DISTR" => request()->total_discounts,
                "UNIT_CODE" => $unit_code,
                "VAT_BASE" => $total - request()->total_discounts,
                "BILLED" => 1,
                "TOTAL_NET" => $total - request()->total_discounts,
                "DISPATCH_NUMBER" => $dispatch->FICHENO,
                "MULTI_ADD_TAX" => 0,
                "EDT_CURR" => 30,
                "EDT_PRICE" => $price,
                "SALEMANCODE" => $salesman_code,
                "AFFECT_RISK" => 1,
                "FOREIGN_TRADE_TYPE" => 0,
                "DISTRIBUTION_TYPE_WHS" => 0,
                "DISTRIBUTION_TYPE_FNO" => 0,
            ];
            if ($item['item_type'] == 0) {
                $itemData["UNIT_CONV1"] = 1;
                $itemData["UNIT_CONV2"] = 1;
            } else {
                $itemData["DISCOUNT_RATE"] = (request()->total_discounts / request()->before_discount) * 100;
                $itemData["DISCEXP_CALC"] = 1;
                $itemData["UNIT_CONV1"] = 0;
                $itemData["UNIT_CONV2"] = 0;
            }
            $data['TRANSACTIONS']['items'][] = $itemData;
        }
        $PAYMENT = [
            "INTERNAL_REFERENCE" => $payment->LOGICALREF,
            "DATE" => $payment->DATE_,
            "MODULENR" => 4,
            "TRCODE" => 8,
            "TOTAL" => $payment->TOTAL,
            "PROCDATE" => $payment->PROCDATE,
            "REPORTRATE" => 1,
            "PAY_NO" => 1,
            "DISCTRDELLIST" => 0,
        ];
        $data['DISPATCHES']['items'][] = $DISPATCHES;
        $data['PAYMENT_LIST']['items'][] = $PAYMENT;
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
                ->patch("https://10.27.0.109:32002/api/v1/salesInvoices/{$id}");
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'invoice' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function accountingSalesmanInvoiceDetails(Request $request)
    {
        $invoice = $request->header('invoice');
        $invoice_id = $this->fetchValueFromTable("$this->invoicesTable", "FICHENO", $invoice, "LOGICALREF");
        $info = DB::table("$this->stocksTransactionsTable")
            ->join("$this->itemsTable", "$this->stocksTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
            ->join("$this->salesmansTable", "$this->stocksTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
            ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
            ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
            ->join("$this->cutomersView", "$this->cutomersView.logicalref", "=", "$this->customersTable.logicalref")
            ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->invoicesTable.paydefref")
            ->join("L_CAPIUSER", "L_CAPIUSER.NR", '=', "$this->invoicesTable.capiblock_createdby")
            ->select(
                "$this->invoicesTable.capiblock_creadeddate as date",
                "$this->invoicesTable.ficheno as number",
                "L_CAPIUSER.NAME as created_by",
                "$this->invoicesTable.genexp1 as approved_by",
                "$this->invoicesTable.grosstotal as invoice_amount",
                "$this->invoicesTable.totaldiscounts as invoice_discount",
                "$this->invoicesTable.nettotal as invoice_total",
                "$this->salesmansTable.definition_ as salesman_name",
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
                "$this->customersTable.addr1 as customer_address",
                "$this->customersTable.telnrs1 as customer_phone",
                "$this->cutomersView.debit as customer_debit",
                "$this->cutomersView.credit as customer_credit",
                "$this->payplansTable.code as payment_plan",
                "$this->invoicesTable.genexp2 as payment_type"
            )
            ->where([
                "$this->invoicesTable.ficheno" => $invoice,
                "$this->salesmansTable.logicalref" => $this->salesman_id,
            ])
            ->distinct()
            ->first();

        $item = DB::table("$this->stocksTransactionsTable")
            ->select(
                "$this->stocksTransactionsTable.invoicelnno as line",
                DB::raw("COALESCE($this->itemsTable.code, '') as code"),
                DB::raw("COALESCE($this->itemsTable.name, '') as name"),
                "$this->stocksTransactionsTable.amount as quantity",
                "$this->stocksTransactionsTable.price",
                "$this->stocksTransactionsTable.total",
                "$this->stocksTransactionsTable.distdisc as discount",
                "$this->weightsTable.grossweight as weight",
            )
            ->leftJoin("$this->itemsTable", "$this->itemsTable.logicalref", '=', "$this->stocksTransactionsTable.stockref")
            ->leftJoin("$this->weightsTable", function ($join) {
                $join->on("$this->stocksTransactionsTable.stockref", '=', "$this->weightsTable.itemref")
                    ->where("$this->weightsTable.linenr", '=', 1);
            })
            ->where([
                "$this->stocksTransactionsTable.invoiceref" => $invoice_id,
                "$this->weightsTable.linenr" => 1,
            ])
            ->orderby("$this->stocksTransactionsTable.invoicelnno", "asc")
            ->get();
        if ($item->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice details',
            'invoice_info' => $info = (array) $info,
            'data' => $item,
        ]);
    }

    public function salesinvoicedetails(Request $request)
    {
        $invoice = $request->header('invoice-id');
        $info = DB::table("$this->stocksTransactionsTable")
            ->join("$this->itemsTable", "$this->stocksTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
            ->join("$this->salesmansTable", "$this->stocksTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
            ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
            ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
            ->join("$this->cutomersView", "$this->cutomersView.logicalref", "=", "$this->customersTable.logicalref")
            ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->invoicesTable.paydefref")
            ->select(
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
                "$this->customersTable.addr1 as customer_address",
                "$this->invoicesTable.ficheno as invoice_number",
                DB::raw("COALESCE($this->invoicesTable.genexp1,' ') as notes"),
                DB::raw("COALESCE($this->salesmansTable.definition_,'0') as salesman_name"),
                "$this->invoicesTable.grosstotal as before_discount",
                "$this->invoicesTable.totaldiscounts as total_discount",
                "$this->invoicesTable.totaldiscounted as after_discount",

            )
            ->where(["$this->invoicesTable.logicalref" => $invoice])
            ->distinct()
            ->first();

        $item = DB::table("$this->stocksTransactionsTable")
            ->select(
                "$this->stocksTransactionsTable.logicalref as id",
                "$this->stocksTransactionsTable.invoicelnno as item_line_number",
                "$this->stocksTransactionsTable.linetype as type",
                DB::raw("COALESCE($this->itemsTable.code, '0') as code"),
                DB::raw("COALESCE($this->itemsTable.name, '0') as name"),
                "$this->stocksTransactionsTable.amount as quantity",
                "$this->stocksTransactionsTable.price",
                "$this->stocksTransactionsTable.total",
                "$this->stocksTransactionsTable.distdisc as discount",
                DB::raw("COALESCE($this->weightsTable.grossweight, '0') as weight"),
            )
            ->leftJoin("$this->itemsTable", "$this->itemsTable.logicalref", '=', "$this->stocksTransactionsTable.stockref")
            ->leftJoin("$this->weightsTable", function ($join) {
                $join->on("$this->stocksTransactionsTable.stockref", '=', "$this->weightsTable.itemref")
                    ->where("$this->weightsTable.linenr", '=', 1);
            })
            ->where("$this->stocksTransactionsTable.invoiceref", '=', $invoice)
            ->get();

        if ($item->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice details',
            'invoice_info' => $info = (array) $info,
            'data' => $item,
        ]);
    }

    public function salesReturnInvoicesList(Request $request)
    {
        $invoices = DB::table("$this->invoicesTable")
            ->leftjoin($this->salesmansTable, "$this->invoicesTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join($this->customersTable, "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
            ->select(
                // "$this->salesmansTable.definition_ as salesman_name",
                DB::raw("COALESCE($this->salesmansTable.definition_,'0') as salesman_name"),
                "$this->customersTable.logicalref as customer_id",
                "$this->customersTable.code as customer_code",
                "$this->invoicesTable.logicalref as invoice_id",
                "$this->invoicesTable.capiblock_creadeddate as invoice_date",
                "$this->customersTable.definition_ as customer_name",
                "$this->invoicesTable.ficheno as invoice_number",
                "$this->invoicesTable.nettotal as total_amount",
                "$this->invoicesTable.docode as from_p_invoice"
            )
            ->where(["$this->invoicesTable.grpcode" => 2, "$this->invoicesTable.trcode" => 3]);
        $this->applyFilters($invoices, [
            "$this->customersTable.code" => [
                'value' => '%' . $request->input('customer_code') . '%',
                'operator' => 'LIKE',
            ],
            "$this->customersTable.definition_" => [
                'value' => '%' . $request->input('customer_name') . '%',
                'operator' => 'LIKE',
            ],
            "$this->invoicesTable.ficheno" => [
                'value' => '%' . $request->input('invoice_number') . '%',
                'operator' => 'LIKE',
            ],
            "$this->invoicesTable.sourceindex" => [
                'value' => $request->input('warehouse_number'),
                'operator' => '=',
            ],
            "$this->invoicesTable.capiblock_createdby" => [
                'value' => $request->input('added_by'),
                'operator' => '=',
            ],
            "$this->invoicesTable.capiblock_modifiedby" => [
                'value' => $request->input('modified_by'),
                'operator' => '=',
            ],
        ]);
        $invoicesData = $invoices->orderBy("$this->invoicesTable.capiblock_creadeddate", "desc")->paginate($this->perpage);
        if ($invoicesData->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoices list',
            'data' => $invoicesData->items(),
            'current_page' => $invoicesData->currentPage(),
            'per_page' => $invoicesData->perPage(),
            'next_page' => $invoicesData->nextPageUrl($this->page),
            'previous_page' => $invoicesData->previousPageUrl($this->page),
            'last_page' => $invoicesData->lastPage(),
            'total' => $invoicesData->total(),
        ]);
    }

    public function salesReturnInvoiceDetails(Request $request)
    {
        $invoice = $request->header('invoice');
        $info = DB::table("$this->stocksTransactionsTable")
            ->join("$this->itemsTable", "$this->stocksTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
            ->join("$this->salesmansTable", "$this->stocksTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
            ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
            ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
            ->join("$this->cutomersView", "$this->cutomersView.logicalref", "=", "$this->customersTable.logicalref")
            ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->invoicesTable.paydefref")
            ->select(
                "$this->invoicesTable.capiblock_creadeddate as date",
                "$this->invoicesTable.ficheno as number",
                "$this->invoicesTable.genexp1 as approved_by",
                "$this->invoicesTable.grosstotal as invoice_amount",
                "$this->invoicesTable.totaldiscounts as invoice_discount",
                "$this->invoicesTable.nettotal as invoice_total",
                "$this->salesmansTable.definition_ as salesman_name",
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
                "$this->customersTable.addr1 as customer_address",
                "$this->customersTable.telnrs1 as customer_phone",
                "$this->cutomersView.debit as customer_debit",
                "$this->cutomersView.credit as customer_credit",
                "$this->payplansTable.code as payment_plan",
                "$this->invoicesTable.genexp2 as payment_type"
            )
            ->where(["$this->invoicesTable.ficheno" => $invoice, "$this->stocksTransactionsTable.iocode" => 1, "$this->stocksTransactionsTable.trcode" => 3])
            ->distinct()
            ->first();
        $item = DB::table("$this->stocksTransactionsTable")
            ->join("$this->itemsTable", "$this->stocksTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
            ->join("$this->salesmansTable", "$this->stocksTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
            ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
            ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
            ->select(
                "$this->stocksTransactionsTable.invoicelnno as line",
                "$this->itemsTable.code as code",
                "$this->itemsTable.name as name",
                "$this->stocksTransactionsTable.amount as quantity",
                "$this->stocksTransactionsTable.price as price",
                "$this->stocksTransactionsTable.total as total",
                "$this->stocksTransactionsTable.distdisc as discount",
                "$this->weightsTable.grossweight as weight"
            )
            ->where(["$this->invoicesTable.ficheno" => $invoice, "$this->weightsTable.linenr" => 1, "$this->stocksTransactionsTable.iocode" => 1, "$this->stocksTransactionsTable.trcode" => 3])
            ->orderby("$this->stocksTransactionsTable.invoicelnno", "asc")
            ->get();
        if ($item->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice details',
            'invoice_info' => $info = (array) $info,
            'data' => $item,
        ]);
    }

    public function doSalesReturnInvoice(Request $request)
    {
        $data = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 3,
            "NUMBER" => '~',
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            'TIME' => TimeHelper::calculateTime(),
            "DOC_NUMBER" => $request->document_number,
            "ARP_CODE" => $request->customer_code,
            "POST_FLAGS" => 247,
            "VAT_RATE" => 18,
            "TOTAL_DISCOUNTS" => $request->total_discounts,
            "TOTAL_DISCOUNTED" => $request->after_discount,
            "TOTAL_GROSS" => $request->before_discount,
            "TOTAL_NET" => $request->net_total,
            "TC_NET" => $request->net_total,
            "RC_NET" => $request->net_total,
            "RC_XRATE" => 1,
            "PAYMENT_CODE" => $request->payment_code,
            "CREATED_BY" => request()->header('username'),
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "CURRSEL_TOTALS" => 1,
            "SALESMAN_CODE" => $request->salesman_code,
            "DEDUCTIONPART1" => 2,
            "DEDUCTIONPART2" => 3,
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
        ];
        $DISPATCHES = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 3,
            "NUMBER" => '~',
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            'TIME' => TimeHelper::calculateTime(),
            "DOC_NUMBER" => $request->document_number,
            "ARP_CODE" => $request->customer_code,
            "INVOICED" => 1,
            "TOTLA_DISCOUNTS" => $request->total_discounts,
            "TOTAL_DISCOUNTED" => $request->after_discount,
            "TOTAL_GROSS" => $request->before_discount,
            "TOTAL_NET" => $request->net_total,
            "RC_RATE" => 1,
            "RC_NET" => $request->net_total,
            "PAYMENT_CODE" => $request->payment_code,
            "CREATED_BY" => request()->header('username'),
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SALESMANCODE" => $request->salesman_code,
            "CURRSEL_TOTALS" => 1,
            "ORIG_NUMBER" => '~',
            "DEDUCTIONPART1" => 2,
            "DEDUCTIONPART2" => 3,
            "AFFECT_RISK" => 1,
            "DISP_STATUS" => 1,
            "SHIP_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "SHIP_TIME" => TimeHelper::calculateTime(),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "DOC_TIME" => TimeHelper::calculateTime(),
        ];
        $transactions = $request->input('TRANSACTIONS.items');
        foreach ($transactions as $item) {
            $type = $item['item_type'];
            $master_code = $item['item_code'];
            $payment_code = $item['payment_code'];
            $quantity = $item['item_quantity'];
            $price = $item['item_price'];
            $total = $item['item_total'];
            $unit_code = $item['item_unit_code'];
            $salesman_code = $request->salesman_code;
            $master_def = $item['item_ar_name'];
            $master_def2 = $item['item_en_name'];
            $master_def3 = $item['item_tr_name'];
            $barcode = $item['item_barcode'];
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "TYPE" => $type,
                "MASTER_CODE" => $master_code,
                "PAYMENT_CODE" => $payment_code,
                "QUANTITY" => $quantity,
                "PRICE" => $price,
                "TOTAL" => $total,
                "CURR_PRICE" => 30,
                "PC_PRICE" => $price,
                "RC_XRATE" => 1,
                "UNIT_CODE" => 1,
                "UNIT_CONV1" => 1,
                "UNIT_CONV2" => 1,
                "VAT_BASE" => $total,
                "BILLED" => 1,
                "RET_COST_TYPE" => 1,
                "TOTAL_NET" => $total,
                "DISPATCH_NUMBER" => "~",
                "DIST_ORD_REFERENCE" => 0,
                "MULTI_ADD_TAX" => 0,
                "EDT_CURR" => 30,
                "EDT_PRICE" => $total,
                "SALEMANCODE" => $salesman_code,
                "MONTH" => Carbon::now()->timezone('Asia/Baghdad')->format('m'),
                "YEAR" => Carbon::now()->timezone('Asia/Baghdad')->format('Y'),
                "AFFECT_RISK" => 1,
                "MASTER_DEF" => $master_def,
                "MASTER_DEF2" => $master_def2,
                "MASTER_DEF3" => $master_def3,
                "BARCODE" => $barcode,
                "FOREIGN_TRADE_TYPE" => 0,
                "DISTRIBUTION_TYPE_WHS" => 0,
                "DISTRIBUTION_TYPE_FNO" => 0,
            ];
            if ($item['item_type'] == 0) {
                $itemData["UNIT_CONV1"] = 1;
                $itemData["UNIT_CONV2"] = 1;
            } else {
                $itemData["UNIT_CONV1"] = 0;
                $itemData["UNIT_CONV2"] = 0;
            }
        }
        $PAYMENT = [
            "INTERNAL_REFERENCE" => 0,
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "MODULENR" => 4,
            "SIGN" => 1,
            "TRCODE" => 3,
            "TOTAL" => $request->net_total,
            "DAYS" => $request->customer_payplan,
            "PROCDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "REPORTRATE" => 1,
            "PAY_NO" => 1,
            "DISCTRDELIST" => 0,
        ];
        $data['DISPATCHES']['items'][] = $DISPATCHES;
        $data['TRANSACTIONS']['items'][] = $itemData;
        $data['PAYMENT_LIST']['items'][] = $PAYMENT;
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
                ->post('https://10.27.0.109:32002/api/v1/salesInvoices');
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'invoice' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Invoice failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function salesmanLastTwoMonthsInvoices(Request $request)
    {
        $invoices = DB::table("$this->salesmansTable")
            ->join($this->invoicesTable, "$this->invoicesTable.salesmanref", "=", 'lg_slsman.logicalref')
            ->join($this->customersTable, "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
            ->select(
                "$this->customersTable.logicalref as customer_id",
                "$this->customersTable.code as customer_code",
                "$this->invoicesTable.logicalref as invoice_id",
                "$this->invoicesTable.capiblock_creadeddate as invoice_date_",
                "$this->customersTable.definition_ as customer_name",
                "$this->invoicesTable.ficheno as invoice_number",
                "$this->invoicesTable.nettotal as total_amount",
                "$this->invoicesTable.docode as from_p_invoice"
            )
            ->where("$this->invoicesTable.salesmanref", $this->salesman_id)
            ->orderBy("$this->invoicesTable.capiblock_creadeddate", "desc")
            ->paginate($this->perpage);
        if ($invoices->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Last two months invoices',
            'total' => $invoices->total(),
            'data' => $invoices->items(),
            'current_page' => $invoices->currentPage(),
            'per_page' => $invoices->perPage(),
            'next_page' => $invoices->nextPageUrl($this->page),
            'previous_page' => $invoices->previousPageUrl($this->page),
            'last_page' => $invoices->lastPage(),
        ]);
    }

    public function salesmaninvoices(Request $request)
    {
        $invoices = DB::table("$this->salesmansTable")
            ->join($this->invoicesTable, "$this->invoicesTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join($this->customersTable, "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
            ->select(
                "$this->customersTable.logicalref as customer_id",
                "$this->customersTable.code as customer_code",
                "$this->invoicesTable.logicalref as invoice_id",
                "$this->invoicesTable.capiblock_creadeddate as invoice_date",
                "$this->customersTable.definition_ as customer_name",
                "$this->invoicesTable.ficheno as invoice_number",
                "$this->invoicesTable.nettotal as total_amount",
                "$this->invoicesTable.docode as from_p_invoice",
                "$this->invoicesTable.trcode",
                DB::raw("(SELECT TOP 1 CAPIBLOCK_CREADEDDATE FROM $this->invoicesTable WHERE $this->invoicesTable.clientref = $this->customersTable.logicalref ORDER BY logicalref DESC) as last_invoice_date"),
                DB::raw("(SELECT TOP 1 CAPIBLOCK_CREADEDDATE FROM $this->customersTransactionsTable WHERE $this->customersTransactionsTable.clientref = $this->customersTable.logicalref and trcode=1 ORDER BY $this->customersTransactionsTable.logicalref DESC) as last_payment_date")
            )
            ->where([
                "$this->invoicesTable.salesmanref" => $this->salesman_id,
                "$this->invoicesTable.grpcode" => 2,
                "$this->invoicesTable.trcode" => $this->type
            ]);
        if ($request->input('start_date') && $request->input('end_date')) {
            $this->start_date = request()->input('start_date');
            $this->end_date = request()->input('end_date');
            if ($this->start_date != '-1' && $this->end_date != '-1') {
                $invoices->whereBetween(DB::raw("CONVERT(date, $this->invoicesTable.CAPIBLOCK_CREADEDDATE)"), [$this->start_date, $this->end_date]);
            }
        }
        $invoicesData = $invoices->orderBy("$this->invoicesTable.capiblock_creadeddate", "desc");

        $invoicesData = $invoices->paginate($this->perpage);

        if ($invoicesData->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Invoices list',
            'data' => $invoicesData->items(),
            'current_page' => $invoicesData->currentPage(),
            'per_page' => $invoicesData->perPage(),
            'next_page' => $invoicesData->nextPageUrl($this->page),
            'previous_page' => $invoicesData->previousPageUrl($this->page),
            'last_page' => $invoicesData->lastPage(),
            'total' => $invoicesData->total(),
        ]);
    }

    public function salesmaninvoicedetails(Request $request)
    {
        $invoice = $request->header('invoice');
        $invoice_id = $this->fetchValueFromTable($this->invoicesTable, "FICHENO", $invoice, "LOGICALREF");
        $info = DB::table("$this->stocksTransactionsTable")
            ->join("$this->salesmansTable", "$this->stocksTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->leftjoin("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
            ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
            ->join("$this->cutomersView", "$this->cutomersView.logicalref", "=", "$this->customersTable.logicalref")
            ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->invoicesTable.paydefref")
            ->select(
                "$this->invoicesTable.capiblock_creadeddate as date",
                "$this->invoicesTable.ficheno as number",
                "$this->invoicesTable.genexp1 as approved_by",
                "$this->invoicesTable.grosstotal as invoice_amount",
                "$this->invoicesTable.totaldiscounts as invoice_discount",
                "$this->invoicesTable.nettotal as invoice_total",
                "$this->salesmansTable.definition_ as salesman_name",
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
                "$this->customersTable.addr1 as customer_address",
                "$this->customersTable.telnrs1 as customer_phone",
                "$this->cutomersView.debit as customer_debit",
                "$this->cutomersView.credit as customer_credit",
                "$this->payplansTable.code as payment_plan",
                "$this->invoicesTable.genexp2 as payment_type"
            )
            ->where([
                "$this->invoicesTable.logicalref" => $invoice_id,
                "$this->invoicesTable.salesmanref" => $this->salesman_id,
                "$this->invoicesTable.clientref" => $this->customer_id,
            ])
            ->first();

        $item = DB::table("$this->stocksTransactionsTable")
            ->select(
                "$this->stocksTransactionsTable.invoicelnno as line",
                "$this->stocksTransactionsTable.date_ as date",
                DB::raw("COALESCE($this->itemsTable.code, '') as code"),
                DB::raw("COALESCE($this->itemsTable.name, '') as name"),
                "$this->stocksTransactionsTable.amount as quantity",
                "$this->stocksTransactionsTable.price",
                "$this->stocksTransactionsTable.total",
                "$this->stocksTransactionsTable.distdisc as discount",
                "$this->weightsTable.grossweight as weight",
            )
            ->leftJoin("$this->itemsTable", "$this->itemsTable.logicalref", '=', "$this->stocksTransactionsTable.stockref")
            ->leftJoin("$this->weightsTable", function ($join) {
                $join->on("$this->stocksTransactionsTable.stockref", '=', "$this->weightsTable.itemref")
                    ->where("$this->weightsTable.linenr", '=', 1);
            })
            ->where([
                "$this->stocksTransactionsTable.invoiceref" => $invoice_id,
                "$this->weightsTable.linenr" => 1,
            ])
            ->orderby("$this->stocksTransactionsTable.invoicelnno", "asc")
            ->get();
        if ($item->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice details',
            'invoice_info' => $info = (array) $info,
            'data' => $item,
        ]);
    }

    public function InvoiceDateFilter(Request $request)
    {
        $invoice = DB::table($this->salesmansTable)
            ->join("$this->invoicesTable", "$this->invoicesTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join("$this->customersTable", "$this->customersTable.logicalref", "=", "$this->invoicesTable.clientref")
            ->select(
                DB::raw("CONVERT(date, $this->invoicesTable.capiblock_creadeddate) as order_date"),
                "$this->invoicesTable.ficheno as order_number",
                "$this->customersTable.definition_ as customer_name",
                "$this->customersTable.addr1 as customer_address",
                "$this->salesmansTable.code as salesman_code",
                "$this->invoicesTable.nettotal as order_total_amount",
            )
            ->where(["$this->invoicesTable.trcode" => $this->type])
            ->whereBetween(DB::raw("CONVERT(date, $this->invoicesTable.CAPIBLOCK_CREADEDDATE)"), [$this->start_date, $this->end_date])
            ->orderBy(DB::raw("CONVERT(date, $this->invoicesTable.CAPIBLOCK_CREADEDDATE)"), "desc")
            ->get();
        if ($invoice->isEmpty()) {
            return response()->json([
                'ststus' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'orders list',
            'data' => $invoice,
        ], 200);
    }

    public function customerprevioussalesreturninvoices(Request $request)
    {
        $customer = $request->header('customer');
        $invoices = DB::table("$this->invoicesTable")
            ->join($this->customersTable, "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
            ->join("L_CAPIUSER", "L_CAPIUSER.NR", "=", "$this->invoicesTable.capiblock_createdby")
            ->select(
                "$this->invoicesTable.capiblock_creadeddate as date",
                "$this->invoicesTable.ficheno as invoice_number",
                "l_capiuser.name as created_by",
                "$this->customersTable.definition_ as customer_name",
                "$this->invoicesTable.grosstotal as amount",
                "$this->invoicesTable.totaldiscounts as discount",
                "$this->invoicesTable.nettotal as total",
                "$this->invoicesTable.docode as from_p_invoice",
                "$this->invoicesTable.genexp1 as note",
                "$this->invoicesTable.salesmanref as salesman_id",
            )
            ->where(["$this->customersTable.code" => $customer, "$this->invoicesTable.trcode" => 3]);
        if ($request->input('start_date') && $request->input('end_date')) {
            if ($this->start_date == '-1' && $this->end_date == '-1') {
                $invoices->get();
            } else {
                $invoices->whereBetween(DB::raw("CONVERT(date, $this->invoicesTable.CAPIBLOCK_CREADEDDATE)"), [$this->start_date, $this->end_date]);
            }
        }
        $data = $invoices->orderby("$this->invoicesTable.capiblock_creadeddate", "desc")->get();
        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ], 200);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice list',
            'total' => $data->count(),
            'data' => $data,
        ]);
    }

    public function customerprevioussalesinvoices(Request $request)
    {
        $customer = $request->header('customer');
        $invoices = DB::table("$this->invoicesTable")
            ->join($this->customersTable, "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
            ->join($this->salesmansTable, "$this->invoicesTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->join("L_CAPIUSER", "L_CAPIUSER.nr", "=", "$this->invoicesTable.capiblock_createdby")
            ->select(
                "$this->invoicesTable.capiblock_creadeddate as date",
                "L_CAPIUSER.name as created_by",
                "$this->invoicesTable.ficheno as invoice_number",
                "$this->invoicesTable.grosstotal as amount",
                "$this->invoicesTable.totaldiscounts as discount",
                "$this->invoicesTable.nettotal as total",
                "$this->invoicesTable.docode as from_p_invoice",
                "$this->invoicesTable.genexp1 as note",
                "$this->salesmansTable.logicalref as salesman_id",
            )
            ->where(["$this->customersTable.code" => $customer, "$this->invoicesTable.trcode" => 8]);
        if ($request->input('start_date') && $request->input('end_date')) {
            if ($this->start_date == '-1' && $this->end_date == '-1') {
                $invoices->get();
            } else {
                $invoices->whereBetween(DB::raw("CONVERT(date, $this->invoicesTable.CAPIBLOCK_CREADEDDATE)"), [$this->start_date, $this->end_date]);
            }
        }
        $data = $invoices->orderby("$this->invoicesTable.capiblock_creadeddate", "desc")->paginate($this->perpage);
        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice list',
            'data' => $data->items(),
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'next_page' => $data->nextPageUrl($this->page),
            'previous_page' => $data->previousPageUrl($this->page),
            'last_page' => $data->lastPage(),
            'total' => $data->total(),
        ], 200);
    }

    public function searchreturnedinvoicebydate(Request $request)
    {
        $customer = $request->header('customer');
        $invoices = DB::table("$this->invoicesTable")
            ->join($this->customersTable, "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
            ->select(
                DB::raw("CONVERT(date, $this->invoicesTable.capiblock_creadeddate) as invoice_date"),
                "$this->invoicesTable.ficheno as invoice_number",
                "$this->invoicesTable.grosstotal as amount",
                "$this->invoicesTable.totaldiscounts as discount",
                "$this->invoicesTable.nettotal as total",
                "$this->invoicesTable.docode as from_p_invoice",
                "$this->invoicesTable.genexp1 as note",
            )
            ->where(["$this->customersTable.code" => $customer])
            ->whereBetween(DB::raw("CONVERT(date, $this->invoicesTable.CAPIBLOCK_CREADEDDATE)"), [$this->start_date, $this->end_date])
            ->orderBy(DB::raw("CONVERT(date, $this->invoicesTable.CAPIBLOCK_CREADEDDATE)"), "desc")
            ->get();
        if ($invoices->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice list',
            'data' => $invoices,
        ]);
    }

    public function searchinvoicebynumber(Request $request)
    {
        $invoice = $request->input('invoice_number');
        $invoices = DB::table("$this->invoicesTable")
            ->join($this->customersTable, "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
            ->select(
                "$this->invoicesTable.capiblock_creadeddate as date",
                "$this->invoicesTable.ficheno as invoice_number",
                "$this->invoicesTable.grosstotal as amount",
                "$this->invoicesTable.totaldiscounts as discount",
                "$this->invoicesTable.nettotal as total",
                "$this->invoicesTable.docode as from_p_invoice",
                "$this->invoicesTable.genexp1 as note",
            )
            ->where("$this->invoicesTable.ficheno", $invoice)
            ->get();
        if ($invoices->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice details',
            'data' => $invoices,
        ]);
    }

    public function customerlastteninvoices(Request $request)
    {
        $customer = $request->header('customer');
        $invoices = DB::table("$this->invoicesTable")
            ->join($this->customersTable, "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
            ->select(
                "$this->invoicesTable.clientref as customer_id",
                "$this->invoicesTable.capiblock_creadeddate as date",
                "$this->invoicesTable.ficheno as invoice_number",
                "$this->invoicesTable.grosstotal as amount",
                "$this->invoicesTable.totaldiscounts as discount",
                "$this->invoicesTable.nettotal as total",
            )
            ->where([
                "$this->customersTable.code" => $customer,
                "$this->invoicesTable.salesmanref" => $this->salesman_id,
                "$this->invoicesTable.trcode" => 8
            ])
            ->orderby("$this->invoicesTable.capiblock_creadeddate", "desc")
            ->limit(10)
            ->get();
        if ($invoices->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice list',
            'data' => $invoices,
        ]);
    }

    public function destroy($id)
    {
        $invoice = $this->fetchValueFromTable($this->invoicesTable, 'logicalref', $id, 'ficheno');
        if (!$invoice) {
            return response()->json([
                'status' => 'success',
                'message' => 'Invoice is not exist',
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
                ->delete("https://10.27.0.109:32002/api/v1/salesInvoices/{$id}");

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

    public function wareHouseInvoices(Request $request)
    {
        $invoices = DB::table("$this->invoicesTable")
            ->leftjoin($this->salesmansTable, "$this->invoicesTable.salesmanref", "=", "$this->salesmansTable.logicalref")
            ->leftjoin($this->customersTable, "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
            ->select(
                DB::raw("COALESCE($this->salesmansTable.definition_, '0') as salesman_name"),
                "$this->customersTable.logicalref as customer_id",
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
                "$this->invoicesTable.logicalref as invoice_id",
                "$this->invoicesTable.capiblock_creadeddate as invoice_date",
                "$this->invoicesTable.ficheno as invoice_number",
                "$this->invoicesTable.nettotal as total_amount",
                "$this->invoicesTable.docode as from_p_invoice"
            )
            ->where([
                "$this->invoicesTable.grpcode" => 2,
                "$this->invoicesTable.trcode" => $this->transaction_code,
            ]);
        if ($this->stock_number) {
            $invoices->where("$this->invoicesTable.sourceindex", 3);
        }
        $this->applyFilters($invoices, [
            "$this->customersTable.code" => [
                'value' => '%' . $request->input('customer_code') . '%',
                'operator' => 'LIKE',
            ],
            "$this->customersTable.definition_" => [
                'value' => '%' . $request->input('customer_name') . '%',
                'operator' => 'LIKE',
            ],
            "$this->invoicesTable.ficheno" => [
                'value' => '%' . $request->input('invoice_number') . '%',
                'operator' => 'LIKE',
            ],
            "$this->invoicesTable.sourceindex" => [
                'value' => $request->input('warehouse_number'),
                'operator' => '=',
            ],
            "$this->invoicesTable.capiblock_createdby" => [
                'value' => $request->input('added_by'),
                'operator' => '=',
            ],
            "$this->invoicesTable.capiblock_modifiedby" => [
                'value' => $request->input('modified_by'),
                'operator' => '=',
            ],
        ]);
        if ($request->input('start_date') && $request->input('end_date')) {
            $this->start_date = request()->input('start_date');
            $this->end_date = request()->input('end_date');
            if ($this->start_date != '-1' && $this->end_date != '-1') {
                $invoices->whereBetween(DB::raw("CONVERT(date, $this->invoicesTable.date_)"), [$this->start_date, $this->end_date]);
            }
        }

        $invoicesData = $invoices->orderBy("$this->invoicesTable.date_", "desc")->paginate($this->perpage);

        if ($invoicesData->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Invoices list',
            'total' => $invoicesData->total(),
            'data' => $invoicesData->items(),
            'current_page' => $invoicesData->currentPage(),
            'per_page' => $invoicesData->perPage(),
            'next_page' => $invoicesData->nextPageUrl($this->page),
            'previous_page' => $invoicesData->previousPageUrl($this->page),
            'last_page' => $invoicesData->lastPage(),
        ]);
    }

    public function generateNumber()
    {
        $number = InvoiceNumberGenerator::generateInvoiceNumber($this->invoicesTable);
        return response()->json([
            'data' => $number
        ]);
    }

    public function itemTypes()
    {
        $data = [
            ['id' => 0, 'name' => 'item'],
            ['id' => 2, 'name' => 'discount'],
        ];

        return response()->json([
            'message' => 'item types',
            'data' => $data,
        ]);
    }

    public function invoiceTypes()
    {
        $data = [
            ['id' => 3, 'name' => 'return'],
            ['id' => 8, 'name' => 'sales'],
        ];

        return response()->json([
            'message' => 'invoice types',
            'data' => $data,
        ]);
    }
}
// public function purchaseinvoicedetails(Request $request)
// {
//     $invoice = $request->header('invoice');
//     $info = DB::table("$this->stocksTransactionsTable")
//         ->leftjoin("$this->salesmansTable", "$this->stocksTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
//         ->join("$this->itemsTable", "$this->stocksTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
//         ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
//         ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
//         ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
//         ->join("$this->cutomersView", "$this->cutomersView.logicalref", "=", "$this->customersTable.logicalref")
//         ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->invoicesTable.paydefref")
//         ->select(
//             "$this->invoicesTable.capiblock_creadeddate as date",
//             "$this->invoicesTable.ficheno as number",
//             "$this->invoicesTable.genexp1 as approved_by",
//             "$this->invoicesTable.grosstotal as invoice_amount",
//             "$this->invoicesTable.totaldiscounts as invoice_discount",
//             "$this->invoicesTable.nettotal as invoice_total",
//             "$this->customersTable.code as customer_code",
//             "$this->customersTable.definition_ as customer_name",
//             "$this->customersTable.addr1 as customer_address",
//             "$this->customersTable.telnrs1 as customer_phone",
//             DB::raw("COALESCE($this->salesmansTable.definition_, '0') as salesman_name"),
//             "$this->cutomersView.debit as customer_debit",
//             "$this->cutomersView.credit as customer_credit",
//             "$this->payplansTable.code as payment_plan",
//             "$this->invoicesTable.genexp2 as payment_type"
//         )
//         ->where(["$this->invoicesTable.ficheno" => $invoice, "$this->stocksTransactionsTable.iocode" => 1, "$this->stocksTransactionsTable.trcode" => 1])
//         ->distinct()
//         ->first();
//     $item = DB::table("$this->stocksTransactionsTable")
//         ->join("$this->itemsTable", "$this->stocksTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
//         ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
//         ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
//         ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
//         ->select(
//             "$this->stocksTransactionsTable.invoicelnno as line",
//             "$this->itemsTable.code as code",
//             "$this->itemsTable.name as name",
//             "$this->stocksTransactionsTable.amount as quantity",
//             "$this->stocksTransactionsTable.price as price",
//             "$this->stocksTransactionsTable.total as total",
//             "$this->stocksTransactionsTable.distdisc as discount",
//             "$this->weightsTable.grossweight as weight"
//         )
//         ->where(["$this->invoicesTable.ficheno" => $invoice, "$this->weightsTable.linenr" => 1, "$this->stocksTransactionsTable.iocode" => 1, "$this->stocksTransactionsTable.trcode" => 1])
//         ->orderby("$this->stocksTransactionsTable.invoicelnno", "asc")
//         ->get();
//     if ($item->isEmpty()) {
//         return response()->json([
//             'status' => 'success',
//             'message' => 'There is no data',
//             'data' => []
//         ]);
//     }
//     return response()->json([
//         'status' => 'success',
//         'message' => 'Invoice details',
//         'invoice_info' => $info = (array) $info,
//         'data' => $item,
//     ]);
// }

// public function purchaseReturnInvoicesList(Request $request)
// {
//     $invoices = DB::table("$this->invoicesTable")
//         ->leftJoin("$this->salesmansTable", "$this->invoicesTable.salesmanref", "=", "$this->salesmansTable.logicalref")
//         ->join("$this->customersTable", "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
//         ->select(
//             DB::raw("COALESCE($this->salesmansTable.definition_, '0') as salesman_name"),
//             "$this->customersTable.logicalref as customer_id",
//             "$this->customersTable.code as customer_code",
//             "$this->invoicesTable.logicalref as invoice_id",
//             "$this->invoicesTable.capiblock_creadeddate as invoice_date",
//             "$this->customersTable.definition_ as customer_name",
//             "$this->invoicesTable.ficheno as invoice_number",
//             "$this->invoicesTable.nettotal as total_amount",
//             "$this->invoicesTable.docode as from_p_invoice"
//         )
//         ->where(["$this->invoicesTable.grpcode" => 1, "$this->invoicesTable.trcode" => 6]);
//     $this->applyFilters($invoices, [
//         "$this->customersTable.code" => [
//             'value' => '%' . $request->input('customer_code') . '%',
//             'operator' => 'LIKE',
//         ],
//         "$this->customersTable.definition_" => [
//             'value' => '%' . $request->input('customer_name') . '%',
//             'operator' => 'LIKE',
//         ],
//         "$this->invoicesTable.ficheno" => [
//             'value' => '%' . $request->input('invoice_number') . '%',
//             'operator' => 'LIKE',
//         ],
//         "$this->invoicesTable.sourceindex" => [
//             'value' => $request->input('warehouse_number'),
//             'operator' => '=',
//         ],
//         "$this->invoicesTable.capiblock_createdby" => [
//             'value' => $request->input('added_by'),
//             'operator' => '=',
//         ],
//         "$this->invoicesTable.capiblock_modifiedby" => [
//             'value' => $request->input('modified_by'),
//             'operator' => '=',
//         ],
//     ]);
//     $invoicesData = $invoices->orderBy("$this->invoicesTable.capiblock_creadeddate", "desc")->paginate($this->perpage);
//     if ($invoicesData->isEmpty()) {
//         return response()->json([
//             'status' => 'success',
//             'message' => 'There is no data',
//             'data' => [],
//         ]);
//     }
//     return response()->json([
//         'status' => 'success',
//         'message' => 'Invoices list',
//         'data' => $invoicesData->items(),
//         'current_page' => $invoicesData->currentPage(),
//         'per_page' => $invoicesData->perPage(),
//         'next_page' => $invoicesData->nextPageUrl($this->page),
//         'previous_page' => $invoicesData->previousPageUrl($this->page),
//         'last_page' => $invoicesData->lastPage(),
//         'total' => $invoicesData->total(),
//     ]);
// }

// public function purchaseReturnInvoicesDetails(Request $request)
// {
//     $invoice = $request->header('invoice');
//     $info = DB::table("$this->stocksTransactionsTable")
//         ->leftjoin("$this->salesmansTable", "$this->stocksTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
//         ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
//         ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
//         ->join("$this->cutomersView", "$this->cutomersView.logicalref", "=", "$this->customersTable.logicalref")
//         ->leftJoin("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->invoicesTable.paydefref")
//         ->select(
//             "$this->invoicesTable.capiblock_creadeddate as date",
//             "$this->invoicesTable.ficheno as number",
//             "$this->invoicesTable.genexp1 as approved_by",
//             "$this->invoicesTable.grosstotal as invoice_amount",
//             "$this->invoicesTable.totaldiscounts as invoice_discount",
//             "$this->invoicesTable.nettotal as invoice_total",
//             "$this->customersTable.code as customer_code",
//             "$this->customersTable.definition_ as customer_name",
//             "$this->customersTable.addr1 as customer_address",
//             "$this->customersTable.telnrs1 as customer_phone",
//             DB::raw("COALESCE($this->salesmansTable.definition_, '0') as salesman_name"),
//             "$this->cutomersView.debit as customer_debit",
//             "$this->cutomersView.credit as customer_credit",
//             DB::raw("COALESCE($this->payplansTable.code, '0') as payment_plan"),
//             "$this->invoicesTable.genexp2 as payment_type"
//         )
//         ->where(["$this->invoicesTable.ficheno" => $invoice, "$this->stocksTransactionsTable.trcode" => 6])
//         ->distinct()
//         ->first();
//     $item = DB::table("$this->stocksTransactionsTable")
//         ->join("$this->itemsTable", "$this->stocksTransactionsTable.stockref", "=", "$this->itemsTable.logicalref")
//         ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->itemsTable.logicalref")
//         ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
//         ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
//         ->select(
//             "$this->stocksTransactionsTable.invoicelnno as line",
//             "$this->itemsTable.code as code",
//             "$this->itemsTable.name as name",
//             "$this->stocksTransactionsTable.amount as quantity",
//             "$this->stocksTransactionsTable.price as price",
//             "$this->stocksTransactionsTable.total as total",
//             "$this->stocksTransactionsTable.distdisc as discount",
//             "$this->weightsTable.grossweight as weight"
//         )
//         ->where(["$this->invoicesTable.ficheno" => $invoice, "$this->weightsTable.linenr" => 1, "$this->stocksTransactionsTable.trcode" => 6])
//         ->orderby("$this->stocksTransactionsTable.invoicelnno", "asc")
//         ->get();
//     if ($item->isEmpty()) {
//         return response()->json([
//             'status' => 'success',
//             'message' => 'There is no data',
//             'data' => []
//         ]);
//     }
//     return response()->json([
//         'status' => 'success',
//         'message' => 'Invoice details',
//         'invoice_info' => $info,
//         'data' => $item,
//     ]);
// }

// public function purchaseServicesInvoicesList(Request $request)
// {
//     $invoices = DB::table("$this->invoicesTable")
//         ->leftJoin("$this->salesmansTable", "$this->invoicesTable.salesmanref", "=", "$this->salesmansTable.logicalref")
//         ->join("$this->customersTable", "$this->invoicesTable.clientref", "=", "$this->customersTable.logicalref")
//         ->select(
//             DB::raw("COALESCE($this->salesmansTable.definition_, '0') as salesman_name"),
//             "$this->customersTable.logicalref as customer_id",
//             "$this->customersTable.code as customer_code",
//             "$this->invoicesTable.logicalref as invoice_id",
//             "$this->invoicesTable.capiblock_creadeddate as invoice_date",
//             "$this->customersTable.definition_ as customer_name",
//             "$this->invoicesTable.ficheno as invoice_number",
//             "$this->invoicesTable.nettotal as total_amount",
//             "$this->invoicesTable.docode as from_p_invoice"
//         )
//         ->where(["$this->invoicesTable.grpcode" => 1, "$this->invoicesTable.trcode" => 4]);
//     $this->applyFilters($invoices, [
//         "$this->customersTable.code" => [
//             'value' => '%' . $request->input('customer_code') . '%',
//             'operator' => 'LIKE',
//         ],
//         "$this->customersTable.definition_" => [
//             'value' => '%' . $request->input('customer_name') . '%',
//             'operator' => 'LIKE',
//         ],
//         "$this->invoicesTable.ficheno" => [
//             'value' => '%' . $request->input('invoice_number') . '%',
//             'operator' => 'LIKE',
//         ],
//         "$this->invoicesTable.sourceindex" => [
//             'value' => $request->input('warehouse_number'),
//             'operator' => '=',
//         ],
//         "$this->invoicesTable.capiblock_createdby" => [
//             'value' => $request->input('added_by'),
//             'operator' => '=',
//         ],
//         "$this->invoicesTable.capiblock_modifiedby" => [
//             'value' => $request->input('modified_by'),
//             'operator' => '=',
//         ],
//     ]);
//     $invoicesData = $invoices->orderBy("$this->invoicesTable.capiblock_creadeddate", "desc")->paginate($this->perpage);
//     if ($invoicesData->isEmpty()) {
//         return response()->json([
//             'status' => 'success',
//             'message' => 'There is no data',
//             'data' => [],
//         ]);
//     }
//     return response()->json([
//         'status' => 'success',
//         'message' => 'Invoices list',
//         'data' => $invoicesData->items(),
//         'current_page' => $invoicesData->currentPage(),
//         'per_page' => $invoicesData->perPage(),
//         'next_page' => $invoicesData->nextPageUrl($this->page),
//         'previous_page' => $invoicesData->previousPageUrl($this->page),
//         'last_page' => $invoicesData->lastPage(),
//         'total' => $invoicesData->total(),
//     ]);
// }

// public function purchasedServicesInvoicedetails(Request $request)
// {
//     $invoice = $request->header('invoice');
//     $info = DB::table("$this->stocksTransactionsTable")
//         ->leftjoin("$this->salesmansTable", "$this->stocksTransactionsTable.salesmanref", "=", "$this->salesmansTable.logicalref")
//         ->join("$this->servicesTable", "$this->stocksTransactionsTable.stockref", "=", "$this->servicesTable.logicalref")
//         ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
//         ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
//         ->join("$this->cutomersView", "$this->cutomersView.logicalref", "=", "$this->customersTable.logicalref")
//         ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->invoicesTable.paydefref")
//         ->select(
//             "$this->invoicesTable.capiblock_creadeddate as date",
//             "$this->invoicesTable.ficheno as number",
//             "$this->invoicesTable.genexp1 as approved_by",
//             "$this->invoicesTable.grosstotal as invoice_amount",
//             "$this->invoicesTable.totaldiscounts as invoice_discount",
//             "$this->invoicesTable.nettotal as invoice_total",
//             "$this->customersTable.code as customer_code",
//             "$this->customersTable.definition_ as customer_name",
//             "$this->customersTable.addr1 as customer_address",
//             "$this->customersTable.telnrs1 as customer_phone",
//             DB::raw("COALESCE($this->salesmansTable.definition_, '0') as salesman_name"),
//             "$this->cutomersView.debit as customer_debit",
//             "$this->cutomersView.credit as customer_credit",
//             "$this->payplansTable.code as payment_plan",
//             "$this->invoicesTable.genexp2 as payment_type"
//         )
//         ->where(["$this->invoicesTable.ficheno" => $invoice, "$this->stocksTransactionsTable.linetype" => 4, "$this->stocksTransactionsTable.trcode" => 4])
//         ->distinct()
//         ->first();
//     $item = DB::table("$this->stocksTransactionsTable")
//         ->join("$this->servicesTable", "$this->stocksTransactionsTable.stockref", "=", "$this->servicesTable.logicalref")
//         ->join("$this->weightsTable", "$this->weightsTable.itemref", "=", "$this->servicesTable.logicalref")
//         ->join("$this->invoicesTable", "$this->stocksTransactionsTable.invoiceref", "=", "$this->invoicesTable.logicalref")
//         ->join("$this->customersTable", "$this->stocksTransactionsTable.clientref", "=", "$this->customersTable.logicalref")
//         ->select(
//             "$this->stocksTransactionsTable.invoicelnno as line",
//             "$this->servicesTable.code as code",
//             "$this->servicesTable.definition_ as explanation",
//             "$this->servicesTable.definition2 as explanation2",
//             "$this->stocksTransactionsTable.amount as quantity",
//             "$this->stocksTransactionsTable.price as price",
//             "$this->stocksTransactionsTable.total as total",
//             "$this->stocksTransactionsTable.distdisc as discount",
//             "$this->stocksTransactionsTable.LINEEXP as description",
//         )
//         ->where(["$this->invoicesTable.ficheno" => $invoice, "$this->weightsTable.linenr" => 1, "$this->stocksTransactionsTable.linetype" => 4, "$this->stocksTransactionsTable.trcode" => 4])
//         ->orderby("$this->stocksTransactionsTable.invoicelnno", "asc")
//         ->get();
//     if ($item->isEmpty()) {
//         return response()->json([
//             'status' => 'success',
//             'message' => 'There is no data',
//             'data' => []
//         ]);
//     }
//     return response()->json([
//         'status' => 'success',
//         'message' => 'Invoice details',
//         'invoice_info' => $info = (array) $info,
//         'data' => $item,
//     ]);
// }

// public function doPurchaseInvoice(Request $request)
// {
//     $data = [
//         "INTERNAL_REFERENCE" => 0,
//         "TYPE" => 1,
//         "NUMBER" => '~',
//         "DATE" =>  Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
//         'TIME' => TimeHelper::calculateTime(),
//         "ARP_CODE" => $request->customer_code,
//         "POST_FLAGS" => 247,
//         "VAT_RATE" => 18,
//         "TOTAL_DISCOUNTS" => $request->total_discounts,
//         "TOTAL_DISCOUNTED" => $request->after_discount,
//         "TOTAL_GROSS" => $request->before_discount,
//         "TOTAL_NET" => $request->net_total,
//         "TC_NET" => $request->net_total,
//         "RC_XRATE" => 1,
//         "RC_NET" => $request->net_total,
//         "NOTES1" => $request->notes,
//         "PAYMENT_CODE" => $request->payment_code,
//         "CREATED_BY" => request()->header('username'),            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
//         "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
//         "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
//         "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
//         "SALESMAN_CODE" => $request->salesman_code,
//         "CURRSEL_TOTALS" => 1,
//     ];
//     $DISPATCHES = [
//         "INTERNAL_REFERENCE" => 0,
//         "TYPE" => 1,
//         "NUMBER" => '~',
//         "DATE" =>  Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
//         'TIME' => TimeHelper::calculateTime(),
//         "INVOICE_NUMBER" => $data['NUMBER'],
//         "ARP_CODE" => $request->customer_code,
//         "INVOICED" => 1,
//         "TOTLA_DISCOUNTS" => $request->total_discounts,
//         "TOTAL_DISCOUNTED" => $request->after_discount,
//         "TOTAL_GROSS" => $request->before_discount,
//         "TOTAL_NET" => $request->net_total,
//         "RC_RATE" => 1,
//         "RC_NET" => $request->net_total,
//         "PAYMENT_CODE" => $request->payment_code,
//         "CREATED_BY" => request()->header('username'),            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
//         "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
//         "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
//         "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
//         "SALESMANCODE" => $request->salesman_code,
//         "CURRSEL_TOTALS" => 1,
//         "ORIG_NUMBER" => '~',
//         "DEDUCTIONPART1" => 2,
//         "DEDUCTIONPART2" => 3,
//         "AFFECT_RISK" => 1,
//         "DISP_STATUS" => 1,
//         "SHIP_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
//         "SHIP_TIME" => TimeHelper::calculateTime(),
//         "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
//         "DOC_TIME" => TimeHelper::calculateTime(),
//     ];
//     $transactions = $request->input('TRANSACTIONS.items');
//     foreach ($transactions as $item) {
//         $type = $item['item_type'];
//         $master_code = $item['item_code'];
//         $quantity = $item['item_quantity'];
//         $price = $item['item_price'];
//         $total = $item['item_total'];
//         $unit_code = $item['item_unit_code'];
//         $salesman_code = $request->salesman_code;
//         $itemData = [
//             "INTERNAL_REFERENCE" => 0,
//             "TYPE" => $type,
//             "MASTER_CODE" => $master_code,
//             "QUANTITY" => $quantity,
//             "PRICE" => $price,
//             "TOTAL" => $total,
//             "RC_XRATE" => 1,
//             "COST_DISTR" => $request->total_discounts,
//             "DISCOUNT_DISTR" => $request->total_discounts,
//             "UNIT_CODE" => $unit_code,
//             "VAT_BASE" => $total,
//             "BILLED" => 1,
//             "TOTAL_NET" => $total,
//             "DISPATCH_NUMBER" => $DISPATCHES['NUMBER'],
//             "MULTI_ADD_TAX" => 0,
//             "EDT_CURR" => 30,
//             "EDT_PRICE" => $total,
//             "SALEMANCODE" => $salesman_code,
//             "MONTH" => Carbon::now()->timezone('Asia/Baghdad')->format('m'),
//             "YEAR" => Carbon::now()->timezone('Asia/Baghdad')->format('Y'),
//             "AFFECT_RISK" => 1,
//             "FOREIGN_TRADE_TYPE" => 0,
//             "DISTRIBUTION_TYPE_WHS" => 0,
//             "DISTRIBUTION_TYPE_FNO" => 0,
//         ];
//         if ($item['item_type'] == 0) {
//             $itemData["UNIT_CONV1"] = 1;
//             $itemData["UNIT_CONV2"] = 1;
//         } else {
//             $itemData["DISCEXP_CALC"] = 1;
//             $itemData["UNIT_CONV1"] = 0;
//             $itemData["UNIT_CONV2"] = 0;
//         }
//         $data['TRANSACTIONS']['items'][] = $itemData;
//     }
//     $PAYMENT = [
//         "INTERNAL_REFERENCE" => 0,
//         "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
//         "MODULENR" => 4,
//         "TRCODE" => 1,
//         "SIGN" => 1,
//         "TOTAL" => $request->net_total,
//         "DAYS" => $request->payment_code,
//         "PROCDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
//         "REPORTRATE" => 1,
//         "PAY_NO" => 1,
//         "DISCTRDELLIST" => 0,
//     ];
//     $data['DISPATCHES']['items'][] = $DISPATCHES;
//     $data['PAYMENT_LIST']['items'][] = $PAYMENT;
//     try {
//         $response = Http::withOptions([
//             'verify' => false,
//         ])
//             ->withHeaders([
//                 'Accept' => 'application/json',
//                 'Content-Type' => 'application/json',
//                 'Authorization' => $request->header('authorization')
//             ])
//             ->withBody(json_encode($data), 'application/json')
//             ->post('https://10.27.0.109:32002/api/v1/purchaseInvoices');
//         return response()->json([
//             'status' => $response->successful() ? 'success' : 'failed',
//             'invoice' => $response->json(),
//         ], $response->status());
//     } catch (Throwable $e) {
//         return response()->json([
//             'status' => 'Invoice failed',
//             'message' => $e->getMessage(),
//         ], 422);
//     }
// }