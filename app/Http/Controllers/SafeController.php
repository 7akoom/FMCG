<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\LG_KSCARD;
use Illuminate\Http\Request;
use App\Models\LG_01_KSLINES;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;


class SafeController extends Controller
{
    protected $code;
    protected $type;
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
        // ->first();
    }

    //retrieve all safes with balances
    public function index(Request $request)
    {
        $query = DB::table("$this->safesTable")
            ->leftjoin("$this->safesTransactionsTable", "{$this->safesTable}.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->select(
                "$this->safesTable.code",
                "$this->safesTable.name",
                "$this->safesTable.explain",
                DB::raw("COALESCE(SUM(CASE WHEN $this->safesTransactionsTable.sign = 0 THEN $this->safesTransactionsTable.amount ELSE - $this->safesTransactionsTable.amount END),0) AS balance")
            )
            ->where("$this->safesTable.active", 0);

        if ($this->type !== "0") {
            if ($this->type == "800") {
                $query->where("$this->safesTable.code", "LIKE", "%800.%");
            } elseif ($this->type == "900") {
                $query->where("$this->safesTable.code", "LIKE", "%900.%");
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => 'There is no such as type',
                    'data' => $query
                ]);
            }
        }

        $safe = $query->groupBy("$this->safesTable.code", "$this->safesTable.name", "$this->safesTable.explain")
            ->orderBy("$this->safesTable.code", "asc")
            ->get();

        return response()->json([
            "status" => "success",
            "message" => "Safes list",
            "data" => $safe,
        ]);
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
            "auth_codes" =>  $auth_codes,
            "currencies" => $currency
        ], 200);
    }

    public function addSafe(Request $request)
    {
        $data = [
            'code' => $request->safe_code,
            'active' => $request->status,
            'name' => $request->safe_name,
            'explain' => $request->explain,
            'branch' => $request->branch,
            'specode' => $request->special_code,
            'cyphcode' => $request->auth_code,
            'addr1' => $request->address1,
            'addr2' => $request->address2,
            'ccurrency' => $request->currency_type,
            'curratetype' => $request->exchange_price_type,
            'FIXEDCURRTYPE' => $request->is_currency_type_changable,
        ];
        try {
            DB::table("$this->safesTable")->insert($data);
            return response()->json([
                "status" => "success",
                "message" => "Safes added successfully",
                "data" => $data,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                "status" => "failed",
                "message" => $e->getMessage(),
                "data" => '',
            ], 500);
        }
    }

    //retrieve current month safe transaction
    public function salesmanSafeTransaction(Request $request)
    {
        $salesman_specode = $this->fetchValueFromTable($this->salesmansTable, 'logicalref', $this->salesman_id, 'specode');
        // $salesman_specode = DB::table("$this->salesmansTable")->where('logicalref', $this->salesman_id)->value('specode');
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
        $total =  $total_collection - $total_payment;
        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => $data,
            ]);
        }
        return response()->json([
            "status" => "success",
            "message" => "Safes transaction list",
            "total_amount" => $total,
            "data" => $data,
        ]);
    }

    public function safesInformation($safe_code)
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
            ->where("$this->safesTable.code", $safe_code)
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

        return response()->json([
            "status" => "success",
            "message" => "Safes information",
            "data" => $safe,
        ], 200);
    }

    // accounting salesman safe transaction lines
    public function accountingsalesmanSafeTransaction(Request $request)
    {
        $salesman_specode = $this->fetchValueFromTable($this->salesmansTable, 'logicalref', $this->salesman_id, 'specode');
        // $salesman_specode = DB::table("lg_slsman")->where('logicalref', $salesman)->value('specode');
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
                "$this->safesTransactionsTable.sign as transaction_type",
                "$this->safesTransactionsTable.docode as document_number"
            )
            ->where(["$this->safesTable.specode" => $salesman_specode, "$this->safesTable.cyphcode" => 3])
            // ->whereMonth("{$safeLine}.date_", '=', now()->month)
            ->get();
        $total_collection = DB::table("$this->safesTransactionsTable")
            ->join("$this->safesTable", "$this->safesTable.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->where(["$this->safesTable.specode" => $salesman_specode, "$this->safesTable.cyphcode" => 3])
            ->where("$this->safesTransactionsTable.sign", "=", 0)
            ->sum("$this->safesTransactionsTable.amount");
        $total_payment = DB::table("$this->safesTransactionsTable")
            ->join("$this->safesTable", "$this->safesTable.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->where(["$this->safesTable.specode" => $salesman_specode, "$this->safesTable.cyphcode" => 3])
            ->where("$this->safesTransactionsTable.sign", "=", 1)
            ->sum("$this->safesTransactionsTable.amount");
        $total =  $total_collection - $total_payment;
        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => $data
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
        $transaction = DB::table($this->safesTransactionsTable)
            ->join("$this->customerTransactionsTable", "{$this->customerTransactionsTable}.sourcefref", "=", "$this->safesTransactionsTable.logicalref")
            ->join("$this->safesTable", "{$this->safesTable}.logicalref", "=", "$this->safesTransactionsTable.cardref")
            ->join("$this->customersTable", "{$this->customersTable}.logicalref", "=", "$this->customerTransactionsTable.CLIENTREF")
            ->select(
                "$this->safesTable.code as safe_code",
                "$this->safesTransactionsTable.ficheno as safe_transaction_number",
                "$this->customerTransactionsTable.tranno as transaction_number",
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
                'data' => $transaction
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
                'data' => $transaction
            ]);
        }
        return response()->json([
            'status' => "success",
            'message' => "Transaction details",
            'data' => $transaction
        ]);
    }
}
