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
    protected $salesman_id;
    protected $salesmansTable;
    protected $safesTable;
    protected $customersTable;
    protected $safesTransactionsTable;
    protected $currenciesTable;
    protected $specodesTable;
    protected $customerTransactionsTable;

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->type = $request->header('type');
        $this->active = $request->input('active');
        $this->perpage = $request->input('per_page', 50);
        $this->page = $request->input('page', 1);
        $this->salesman_id = $request->header('id');
        $this->salesmansTable = 'LG_SLSMAN';
        $this->safesTable = 'LG_' . $this->code . '_KSCARD';
        $this->customersTable = 'LG_' . $this->code . '_CLCARD';
        $this->specodesTable = 'LG_' . $this->code . '_SPECODES';
        $this->currenciesTable = 'L_CURRENCYLIST';
        $this->safesTransactionsTable = 'LG_' . $this->code . '_01_KSLINES';
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
        $salesman_name = $this->fetchValueFromTable($this->salesmansTable, 'logicalref', $this->salesman_id, 'definition_');
        $safe_id = $this->fetchValueFromTable($this->safesTable, 'name', $salesman_name, 'logicalref');
        $result = DB::table("$this->safesTransactionsTable")
            ->join("$this->safesTransactionsTable as tr", "tr.transref", '=', "$this->safesTransactionsTable.logicalref")
            ->where(function ($query) use ($safe_id) {
                $query->where(["$this->safesTransactionsTable.cardref" => $safe_id,"$this->safesTransactionsTable.trcode" => 74 ])
                    ->orWhere(["$this->safesTransactionsTable.vcardref" => $safe_id,"$this->safesTransactionsTable.trcode" => 73]);
            })
            ->join("$this->safesTable", "$this->safesTable.logicalref", '=', "$this->safesTransactionsTable.cardref")
            ->select(
                "$this->safesTransactionsTable.logicalref as id",
                "$this->safesTransactionsTable.date_ as date",
                "$this->safesTransactionsTable.ficheno as safe_transaction_number",
                "$this->safesTransactionsTable.custtitle as safe_description",
                "tr.ficheno as transaction_number",
                "$this->safesTransactionsTable.amount",
                "$this->safesTransactionsTable.lineexp as description",
                "$this->safesTransactionsTable.sign as transaction_type",
                "$this->safesTransactionsTable.docode as document_number"
            )
            ->where(["$this->safesTransactionsTable.cardref" => $safe_id])
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
        $transaction_number = $this->fetchValueFromTable($this->safesTransactionsTable, 'logicalref', $id, 'ficheno');
        $transaction = DB::table($this->safesTransactionsTable)
            ->join("$this->customerTransactionsTable", "{$this->customerTransactionsTable}.sourcefref", "=", "$this->safesTransactionsTable.logicalref")
            ->join("$this->safesTable", "{$this->safesTable}.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->join("$this->customersTable", "{$this->customersTable}.logicalref", "=", "$this->customerTransactionsTable.CLIENTREF")
            ->select(
                "$this->safesTable.code as safe_code",
                "$this->safesTransactionsTable.ficheno as safe_transaction_number",
                DB::raw("(SELECT TOP 1 FICHENO FROM $this->safesTransactionsTable WHERE $this->safesTransactionsTable.trcode = 73 AND $this->safesTransactionsTable.cardref = $this->safesTransactionsTable.cardref) as transaction_number"),
                "$this->safesTransactionsTable.specode as special_code",
                "$this->safesTransactionsTable.docode as document_number",
                "$this->safesTransactionsTable.cyphcode as auth_code",
                "$this->safesTransactionsTable.date_",
                "$this->safesTransactionsTable.hour_",
                "$this->safesTransactionsTable.minute_",
                "$this->safesTransactionsTable.branch",
                "$this->safesTransactionsTable.department",
                "$this->safesTransactionsTable.docdate",
                "$this->safesTransactionsTable.projectref",
                "$this->safesTransactionsTable.salesmanref",
                DB::raw("(SELECT TOP 1 CODE  FROM $this->salesmansTable WHERE $this->safesTransactionsTable.salesmanref = $this->salesmansTable.logicalref and active=0) as salesman_code"),
                DB::raw("(SELECT TOP 1 DEFINITION_  FROM $this->salesmansTable WHERE $this->safesTransactionsTable.salesmanref = $this->salesmansTable.logicalref and active=0) as salesman_name"),
                "$this->safesTransactionsTable.amount",
                "$this->safesTransactionsTable.lineexp as safe_description",
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
            )
            ->where("$this->safesTransactionsTable.ficheno", $transaction_number)
            ->first();
        if (!$transaction) {
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
