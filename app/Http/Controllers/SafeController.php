<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\LG_KSCARD;
use Illuminate\Http\Request;
use App\Models\LG_01_KSLINES;
use Illuminate\Support\Carbon;
use PhpParser\Node\Stmt\TryCatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Response;

class SafeController extends Controller
{
    protected $code;
    protected $type;
    protected $active;
    protected $perpage;
    protected $page;
    protected $weightsTable;
    protected $salesman_id;
    protected $salesmansTable;
    protected $safesTable;
    protected $itemsTable;
    protected $unitsTable;
    protected $customersTable;
    protected $safesTransactionsTable;
    protected $invoicesTable;
    protected $itemsTransactionsTable;
    protected $currenciesTable;
    protected $whousesTable;
    protected $specodesTable;
    protected $customerTransactionsTable;
    protected $stocksTransactionsTable;


    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->type = $request->header('type');
        $this->active = $request->input('active');
        $this->perpage = $request->input('per_page', 50);
        $this->page = $request->input('page', 1);
        $this->salesman_id = $request->header('id');
        $this->salesmansTable = 'LG_SLSMAN';
        $this->stocksTransactionsTable = 'LG_' . $this->code . '_01_STLINE';
        $this->whousesTable = 'L_CAPIWHOUSE';
        $this->safesTable = 'LG_' . $this->code . '_KSCARD';
        $this->itemsTable = 'LG_' . $this->code . '_ITEMS';
        $this->unitsTable = 'LG_' . $this->code . '_UNITSETL';
        $this->weightsTable = 'LG_' . $this->code . '_ITMUNITA';
        $this->customersTable = 'LG_' . $this->code . '_CLCARD';
        $this->specodesTable = 'LG_' . $this->code . '_SPECODES';
        $this->currenciesTable = 'L_CURRENCYLIST';
        $this->safesTransactionsTable = 'LG_' . $this->code . '_01_KSLINES';
        $this->invoicesTable = 'LG_' . $this->code . '_01_INVOICE';
        $this->itemsTransactionsTable = 'LG_' . $this->code . '_01_STLINE';
        $this->customerTransactionsTable = 'LG_' . $this->code . '_01_CLFLINE';
    }

    private function fetchValueFromTable($table, $column, $value1, $value2)
    {
        return DB::table($table)
            ->where($column, $value1)
            ->value($value2);
    }

    public function index()
    {
        $query = DB::table("$this->safesTable")
            ->leftJoin("$this->safesTransactionsTable", "{$this->safesTable}.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->select(
                "$this->safesTable.logicalref as id",
                "$this->safesTable.code",
                "$this->safesTable.name",
                "$this->safesTable.explain",
                DB::raw("COALESCE(SUM(CASE WHEN $this->safesTransactionsTable.sign = 0 THEN $this->safesTransactionsTable.amount ELSE - $this->safesTransactionsTable.amount END),0) AS balance")
            );

        if ($this->active == "") {
            $query->groupBy(
                "$this->safesTable.logicalref",
                "$this->safesTable.code",
                "$this->safesTable.name",
                "$this->safesTable.explain"
            )->get();
        } else {
            $query->where("$this->safesTable.active", $this->active)
                ->groupBy(
                    "$this->safesTable.logicalref",
                    "$this->safesTable.code",
                    "$this->safesTable.name",
                    "$this->safesTable.explain"
                )->get();
        }

        $safe = $query->get();

        return response()->json([
            "status" => "success",
            "message" => "Safes list",
            "total" => $safe->count(),
            "data" => $safe,
        ], 200);
    }

    public function safesInformation($id)
    {
        $safe = DB::table($this->safesTable)
            ->leftJoin($this->safesTransactionsTable, "$this->safesTable.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->join($this->currenciesTable, "$this->currenciesTable.logicalref", "=", "$this->safesTable.ccurrency")
            ->select(
                "$this->safesTable.code",
                "$this->safesTable.active as status",
                "$this->safesTable.name",
                "$this->safesTable.explain",
                "$this->safesTable.branch",
                "$this->safesTable.specode as special_code",
                "$this->safesTable.cyphcode as auth_code",
                "$this->safesTable.addr1 as address1",
                "$this->safesTable.addr2 as address2",
                "$this->currenciesTable.curname as forien_currency_type",
                "$this->safesTable.curratetype as exchange_price_type",
                "$this->safesTable.fixedcurrtype as check_box",
                DB::raw("COALESCE(SUM(CASE WHEN $this->safesTransactionsTable.sign = 0 THEN $this->safesTransactionsTable.amount ELSE 0 END), 0) AS total_collections"),
                DB::raw("COALESCE(SUM(CASE WHEN $this->safesTransactionsTable.sign = 1 THEN $this->safesTransactionsTable.amount ELSE 0 END), 0) AS total_payments"),
                DB::raw("(COALESCE(SUM(CASE WHEN $this->safesTransactionsTable.sign = 0 THEN $this->safesTransactionsTable.amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN $this->safesTransactionsTable.sign = 1 THEN $this->safesTransactionsTable.amount ELSE 0 END), 0)) AS balance")
            )
            ->where(["$this->safesTable.logicalref" => $id])
            ->groupBy(
                "$this->safesTable.code",
                "$this->safesTable.active",
                "$this->safesTable.name",
                "$this->safesTable.explain",
                "$this->safesTable.branch",
                "$this->safesTable.specode",
                "$this->safesTable.cyphcode",
                "$this->safesTable.addr1",
                "$this->safesTable.addr2",
                "$this->currenciesTable.curname",
                "$this->safesTable.curratetype",
                "$this->safesTable.fixedcurrtype"
            )
            ->first();
        if (!$safe) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            "status" => "success",
            "message" => "Safes information",
            "data" => $safe,
        ], 200);
    }

    public function addSafeData()
    {
        $currency = DB::table($this->currenciesTable)
            ->select('logicalref as id', 'curcode as currency_code', 'curname as currency_name')
            ->where('firmnr', 500)
            ->get();
        $auth_codes = DB::table($this->specodesTable)
            ->select('logicalref as auth_code_id', 'specode as auth_special_code', 'definition_ as auth_code_name')
            ->where(['codetype' => 2, 'specodetype' => 34])
            ->get();
        return response()->json([
            "status" => "success",
            "message" => "Adding safes data",
            "auth_codes" => $auth_codes,
            "currencies" => $currency
        ], 200);
    }

    public function store(Request $request)
    {
        $creator = DB::table('L_CAPIUSER')->where('name', request()->header('username'))
            ->value('nr');
        $last_specode = DB::table($this->specodesTable)->where(['codetype' => 1, 'specodetype' => 34])
            ->orderby('logicalref', 'desc')
            ->value('specode');
        $specode = [
            'CODE_TYPE' => 1,
            'SPE_CODE_TYPE' => 34,
            'CODE' => $last_specode + 1,
            'DEFINITION' => request()->input('safe_code'),
        ];
        $data = [
            'CODE' => request()->input('safe_code'),
            'DESCRIPTION' => request()->input('safe_name'),
            'AUXIL_CODE' => $specode['CODE'],
            'AUTH_CODE' => request()->input('auth_code'),
            'USAGE_NOTE' => request()->input('explain'),
            'CREATED_BY' => $creator,
            'CCURRENCY' => request()->input('currency_type'),
        ];
        try {
            $response1 = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/safeDeposits');
            $responseData = $response1->json();
            $response2 = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($specode), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/specialCodes');
            $specode_response = $response2->json();
            return response()->json([
                'status' => $response1->successful() && $response2->successful() ? 'success' : 'failed',
                'data' => $responseData,
            ], $response1->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function edit($id)
    {
        $safe = DB::table($this->safesTable)
            ->select('code', 'name', 'cyphcode', 'explain', 'active', 'ccurrency')
            ->where('logicalref', $id)
            ->first();

        if (!$safe) {
            return response()->json([
                'status' => 'successfull',
                'message' => 'This safe is not exist',
                'data' => [],
            ], 404);
        }
        return response()->json([
            'status' => 'successfull',
            'message' => 'Safe information',
            'data' => $safe,
        ]);
    }
    public function update($id)
    {
        $creator = DB::table('L_CAPIUSER')->where('name', request()->header('username'))
            ->value('nr');
        $data = [
            'CODE' => request()->input('safe_code'),
            'DESCRIPTION' => request()->input('safe_name'),
            'AUTH_CODE' => request()->input('auth_code'),
            'USAGE_NOTE' => request()->input('explain'),
            'MODIFIED_BY' => $creator,
            'CCURRENCY' => request()->input('currency_type'),
            'XML_ATTRIBUTE' => 2,
        ];
        try {
            $response1 = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->put("https://10.27.0.109:32002/api/v1/safeDeposits/{$id}");
            $responseData = $response1->json();

            return response()->json([
                'status' => $response1->successful() ? 'success' : 'failed',
                'data' => $responseData,
            ], $response1->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        try {
            $safeExists = DB::table($this->safesTable)->where('logicalref', $id)->exists();
            if (!$safeExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Safe not found',
                    'data' => [],
                ], 404);
            }
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->delete("https://10.27.0.109:32002/api/v1/safeDeposits/{$id}");
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'message' => 'Safe deleted succssefully'
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function salesmanSafeTransaction()
    {
        $salesman_specode = $this->fetchValueFromTable($this->salesmansTable, 'logicalref', $this->salesman_id, 'specode');
        $data = DB::table("$this->safesTransactionsTable")
            ->join("$this->safesTable", "$this->safesTable.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->select(
                "$this->safesTransactionsTable.date_ as date",
                "$this->safesTransactionsTable.ficheno as transaction_number",
                "$this->safesTransactionsTable.amount",
                "$this->safesTransactionsTable.lineexp as expaline",
                "$this->safesTransactionsTable.sign as transaction_type"
            )
            ->where(["$this->safesTable.specode" => $salesman_specode, "$this->safesTable.cyphcode" => 3])
            ->whereMonth("$this->safesTransactionsTable.date_", '=', now()->month)
            ->get();
        $total_collection = DB::table("$this->safesTransactionsTable")
            ->join("$this->safesTable", "$this->safesTable.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->where(["$this->safesTable.specode" => $salesman_specode, "$this->safesTable.cyphcode" => 3])
            ->where("$this->safesTransactionsTable.sign", "=", 0)
            ->sum("$this->safesTransactionsTable.amount");
        $total_payment = DB::table("$this->safesTransactionsTable")
            ->join("$this->safesTable", "$this->safesTable.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->where(["$this->safesTable.specode" => $salesman_specode, "$this->safesTransactionsTable.cyphcode" => 3])
            ->where("$this->safesTransactionsTable.sign", "=", 1)
            ->sum("$this->safesTransactionsTable.amount");
        $total = $total_collection - $total_payment;
        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                "total_amount" => $total,
                'data' => []
            ]);
        }
        return response()->json([
            "status" => "success",
            "message" => "Safes transaction list",
            "total_amount" => $total,
            "data" => $data,
        ]);
    }

    public function accountingsalesmanSafeTransaction(Request $request)
    {
        $safe_code = request()->header('safe-code');
        $safe_id = $this->fetchValueFromTable($this->safesTable, 'code', $safe_code, 'logicalref');
        $result = DB::table($this->safesTransactionsTable . ' AS LG')
            ->select(
                'LG.LOGICALREF AS id',
                'LG.FICHENO AS safe_transaction_number',
                DB::raw('COALESCE(TR.FICHENO, LG.FICHENO) AS transaction_number'),
                'LG.amount',
                'LG.docode as document_number',
                'LG.lineexp as explain',
                'LG.date_ as date',
                DB::raw("(SELECT CASE 
                    WHEN LG.trcode = '11' THEN 'Current Account Collection(11)'
                    WHEN LG.trcode = '12' THEN 'Current Account Payment(12)'
                    WHEN LG.trcode = '37' THEN 'Wholesale Invoice(73)'
                    WHEN LG.trcode = '73' THEN 'Debt Transfer(73)'
                    WHEN LG.trcode = '74' THEN 'Receivable Transfer(74)'
                    ELSE 'DefaultName' 
                END) AS transaction_type")
            )
            ->leftJoin($this->safesTransactionsTable . ' AS TR', function ($join) use ($safe_id) {
                $join->on('TR.TRANSREF', '=', 'LG.LOGICALREF')
                    ->where('TR.VCARDREF', '=', $safe_id);
            })
            ->where('LG.CARDREF', '=', $safe_id)
            ->paginate($this->perpage);






        $total_collection = DB::table("$this->safesTransactionsTable")
            ->join("$this->safesTable", "$this->safesTable.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->where(["$this->safesTable.logicalref" => $safe_id])
            ->where("$this->safesTransactionsTable.sign", "=", 0)
            ->sum("$this->safesTransactionsTable.amount");
        $total_payment = DB::table("$this->safesTransactionsTable")
            ->join("$this->safesTable", "$this->safesTable.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->where(["$this->safesTable.logicalref" => $safe_id])
            ->where("$this->safesTransactionsTable.sign", "=", 1)
            ->sum("$this->safesTransactionsTable.amount");
        $total = $total_collection - $total_payment;
        if ($result->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => 'success',
            "message" => "Safes transaction list",
            'total' => $result->total(),
            "total_collection" => $total_collection,
            "safe_balance" => $total,
            'data' => $result->items(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'next_page' => $result->nextPageUrl($this->page),
            'previous_page' => $result->previousPageUrl($this->page),
            'last_page' => $result->lastPage(),
        ]);
    }

    public function accountingSafeTransaction(Request $request)
    {
        $safe_id = $request->header('id');
        $data = DB::table("$this->safesTransactionsTable")
            ->join("$this->safesTable", "$this->safesTable.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->join("$this->customerTransactionsTable", "$this->customerTransactionsTable.sourcefref", "=", "$this->safesTransactionsTable.logicalref")
            ->select(
                "$this->safesTransactionsTable.logicalref as id",
                "$this->safesTransactionsTable.date_ as date",
                "$this->safesTransactionsTable.custtitle as safe_description",
                "$this->safesTransactionsTable.ficheno as safe_transaction_number",
                "$this->customerTransactionsTable.tranno as transaction_number",
                "$this->safesTransactionsTable.amount",
                "$this->safesTransactionsTable.lineexp as description",
                "$this->safesTransactionsTable.trcode as transaction_type",
                "$this->safesTransactionsTable.docode as document_number"
            )
            ->where(["$this->safesTable.logicalref" => $safe_id])
            ->get();
        $total_collection = DB::table("$this->safesTransactionsTable")
            ->join("$this->safesTable", "$this->safesTable.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->where(["$this->safesTable.logicalref" => $safe_id, "$this->safesTransactionsTable.sign" => 0])
            ->sum("$this->safesTransactionsTable.amount");
        $total_payment = DB::table("$this->safesTransactionsTable")
            ->join("$this->safesTable", "$this->safesTable.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->where(["$this->safesTable.logicalref" => $safe_id])
            ->where("$this->safesTransactionsTable.sign", "=", 1)
            ->sum("$this->safesTransactionsTable.amount");
        $total = $total_collection - $total_payment;
        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            "status" => "success",
            "message" => "Safes transaction list",
            "total_collection" => $total_collection,
            "safe_balance" => $total,
            "data" => $data,
        ]);
    }

    public function fetchTransactionDetails($id)
    {
        $record = $this->fetchValueFromTable("$this->safesTransactionsTable", 'logicalref', $id, 'trcode');
        $invoice_ref = $this->fetchValueFromTable("$this->safesTransactionsTable", 'logicalref', $id, 'transref');
        if (!$record) {
            return response()->json([
                'message' => 'record not found',
                'data' => [],
            ], 404);
        }
        if ($record != 37) {
            if ($record == 11 || $record == 12) {
                $transaction = DB::table($this->safesTransactionsTable)
                    ->leftJoin("$this->customerTransactionsTable", "$this->customerTransactionsTable.logicalref", '=', "$this->safesTransactionsTable.transref")
                    ->leftJoin("$this->salesmansTable as sls", function ($join) {
                        $join->on('sls.logicalref', '=', "$this->customerTransactionsTable.salesmanref")
                            ->where('sls.firmnr', '=', $this->code);
                    })
                    ->leftJoin("$this->safesTable", "$this->safesTable.logicalref", '=', "$this->safesTransactionsTable.cardref")
                    ->join("$this->customersTable", "$this->customersTable.logicalref", '=', "$this->customerTransactionsTable.CLIENTREF")
                    ->select(
                        "$this->safesTable.code as safe_code",
                        "$this->safesTable.name as safe_name",
                        "$this->safesTransactionsTable.ficheno as safe_transaction_number",
                        DB::raw('CASE WHEN ' . $this->safesTransactionsTable . '.trcode IN (11, 12) THEN ' . "$this->customerTransactionsTable.tranno" . ' ELSE ' . "$this->customerTransactionsTable.tranno" . ' END AS transaction_number'),
                        "$this->safesTransactionsTable.date_",
                        "$this->safesTransactionsTable.hour_",
                        "$this->safesTransactionsTable.minute_",
                        DB::raw("COALESCE(sls.definition_, '0') as salesman_name"),
                        "$this->safesTransactionsTable.amount",
                        "$this->safesTransactionsTable.lineexp as safe_description",
                        "$this->customerTransactionsTable.clientref as customer_id",
                        "$this->customersTable.code as customer_code",
                        "$this->customersTable.definition_ as customer_name"
                    )
                    ->where("$this->safesTransactionsTable.logicalref", "=", $id)
                    ->first();
                return response()->json([
                    'status' => 'success',
                    'message' => 'transaction details',
                    'data' => $transaction
                ], 200);
            }
            if ($record == 73) {
                $transaction = DB::table($this->safesTransactionsTable)
                    ->leftJoin("$this->customerTransactionsTable", "$this->customerTransactionsTable.logicalref", '=', "$this->safesTransactionsTable.transref")
                    ->leftJoin("$this->salesmansTable as sls", function ($join) {
                        $join->on('sls.logicalref', '=', "$this->customerTransactionsTable.salesmanref")
                            ->where('sls.firmnr', '=', $this->code);
                    })
                    ->leftJoin("$this->safesTable", "$this->safesTable.logicalref", '=', "$this->safesTransactionsTable.cardref")
                    ->join("$this->customersTable", "$this->customersTable.logicalref", '=', "$this->customerTransactionsTable.CLIENTREF")
                    ->leftJoin("$this->safesTransactionsTable AS TR", function ($join) {
                        $join->on('TR.TRANSREF', '=', "$this->safesTransactionsTable.LOGICALREF")
                            ->where('TR.trcode', 74);
                    })
                    ->leftJoin("$this->safesTable as sf", "sf.logicalref", '=', "TR.cardref")
                    ->select(
                        "$this->safesTable.code as safe_code",
                        "$this->safesTable.name as safe_name",
                        "$this->safesTransactionsTable.ficheno as safe_transaction_number",
                        "TR.ficheno as transaction_number",
                        "$this->safesTransactionsTable.date_",
                        "$this->safesTransactionsTable.hour_",
                        "$this->safesTransactionsTable.minute_",
                        "sf.code as destination_safe",
                        "sf.name as destination_safe_name",
                        "$this->safesTransactionsTable.amount",
                        "$this->safesTransactionsTable.lineexp as safe_description",
                    )->where("$this->safesTransactionsTable.logicalref", "=", $id)
                    ->first();
                return response()->json([
                    'status' => 'success',
                    'message' => 'transaction details',
                    'data' => $transaction
                ], 200);
            }
            if ($record == 74) {
                $transaction = DB::table($this->safesTransactionsTable)
                    ->leftJoin("$this->customerTransactionsTable", "$this->customerTransactionsTable.logicalref", '=', "$this->safesTransactionsTable.transref")
                    ->leftJoin("$this->salesmansTable as sls", function ($join) {
                        $join->on('sls.logicalref', '=', "$this->customerTransactionsTable.salesmanref")
                            ->where('sls.firmnr', '=', $this->code);
                    })
                    ->leftJoin("$this->safesTable", "$this->safesTable.logicalref", '=', "$this->safesTransactionsTable.cardref")
                    ->join("$this->customersTable", "$this->customersTable.logicalref", '=', "$this->customerTransactionsTable.CLIENTREF")
                    ->leftJoin("$this->safesTransactionsTable AS TR", function ($join) {
                        $join->on('TR.TRANSREF', '=', "$this->safesTransactionsTable.LOGICALREF")
                            ->where('TR.trcode', 73);
                    })
                    ->leftJoin("$this->safesTable as sf", "sf.logicalref", '=', "TR.cardref")
                    ->select(
                        "$this->safesTable.code as safe_code",
                        "$this->safesTable.name as safe_name",
                        "$this->safesTransactionsTable.ficheno as safe_transaction_number",
                        "TR.ficheno as transaction_number",
                        "$this->safesTransactionsTable.date_",
                        "$this->safesTransactionsTable.hour_",
                        "$this->safesTransactionsTable.minute_",
                        "sf.code",
                        "sf.name as destination_safe_name",
                        "$this->safesTransactionsTable.amount",
                        "$this->safesTransactionsTable.lineexp as safe_description",
                    )
                    ->where("$this->safesTransactionsTable.logicalref", "=", $id)
                    ->first();

                return response()->json([
                    'status' => 'success',
                    'message' => 'transaction details',
                    'data' => $transaction
                ], 200);
            }
        } elseif ($record == 37) {
            $transaction = DB::table("$this->safesTransactionsTable as sfl")
                ->join("$this->safesTable as sf", "sf.logicalref", "=", "sfl.cardref")
                ->join("$this->invoicesTable as inv", "inv.logicalref", "=", "sfl.transref")
                ->join("$this->salesmansTable as sls", "sls.logicalref", "=", "inv.salesmanref")
                ->join("$this->customersTable as arp", "inv.clientref", "=", "arp.logicalref")
                ->join("$this->itemsTransactionsTable as stl", "stl.invoiceref", "=", "inv.logicalref")
                ->join("$this->unitsTable as unt", "unt.logicalref", "=", "stl.uomref")
                ->join("$this->itemsTable as it", "it.logicalref", "=", "stl.stockref")
                ->join("$this->whousesTable as wh", "wh.nr", "=", "inv.sourceindex");
            $info = $transaction->select(
                "sf.code as safe_code",
                "sfl.ficheno as safe_trnsaction_number",
                "inv.ficheno as invoice_number",
                DB::raw("CONVERT(DATE, inv.CAPIBLOCK_CREADEDDATE) as date"),
                DB::raw("CONVERT(DATE, inv.docdate) as editing_date"),
                DB::raw("FORMAT(inv.CAPIBLOCK_CREADEDDATE, 'HH:mm:ss') as time"),
                "arp.code as customer_code",
                "arp.definition_ as customer_name",
                "inv.sourceindex as stock_number",
                "wh.name as stock_name",
                "sls.code as salesman_code",
                "sls.definition_ as salesman_name",
                "inv.grosstotal as total_amount"
            )
                ->where("sfl.logicalref", $id)
                ->first();
            // $items = DB::table("$this->itemsTransactionsTable as stl")
            //     ->join("$this->itemsTable as it", "it.logicalref", "=", "stl.stockref")
            //     ->join("$this->unitsTable as unt", "unt.logicalref", "=", "stl.uomref")
            //     ->join("$this->whousesTable as wh", "wh.nr", "=", "stl.sourceindex")
            //     ->select(
            //         "it.code",
            //         "it.name",
            //         "stl.amount",
            //         "unt.name as unit",
            //         "stl.price",
            //         "stl.total",
            //         "stl.sourceindex as stock_number",
            //         "wh.name as stock_name"
            //     )
            //     ->where("stl.invoiceref", $invoice_ref)
            //     ->first();
            $items = DB::table("$this->stocksTransactionsTable")
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
                    "$this->stocksTransactionsTable.invoiceref" => $invoice_ref,
                    "$this->weightsTable.linenr" => 1,
                ])
                ->orderby("$this->stocksTransactionsTable.invoicelnno", "asc")
                ->get();
            return response()->json([
                'status' => 'success',
                'message' => 'transaction details',
                'data' => [
                    'invoice_info' => $info,
                    'invoice_items' => $items,
                ],
            ], 200);
        }
    }

    public function collectionDetails($id)
    {
        $transaction = DB::table($this->safesTransactionsTable)
            ->join("$this->customerTransactionsTable", "{$this->customerTransactionsTable}.sourcefref", "=", "$this->safesTransactionsTable.logicalref")
            ->join("$this->safesTable", "{$this->safesTable}.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->join("$this->customersTable", "{$this->customersTable}.logicalref", "=", "$this->customerTransactionsTable.CLIENTREF")
            ->join("$this->salesmansTable", "{$this->salesmansTable}.logicalref", "=", "$this->safesTransactionsTable.salesmanref")
            ->select(
                "$this->safesTransactionsTable.ficheno as safe_transaction_number",
                "$this->safesTransactionsTable.docode as document_number",
                "$this->safesTransactionsTable.date_",
                "$this->safesTransactionsTable.hour_",
                "$this->safesTransactionsTable.minute_",
                "$this->salesmansTable.definition_ as salesman name",
                "$this->safesTransactionsTable.amount",
                "$this->safesTransactionsTable.lineexp as safe_description",
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
            )
            ->where("$this->safesTransactionsTable.logicalref", $id)
            ->get();
        if ($transaction->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => []
            ]);
        }
        return response()->json([
            'status' => "success",
            'message' => "Transaction details",
            'data' => $transaction
        ]);
    }
}
