<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Traits\Filterable;
use Throwable;
use Illuminate\Support\Facades\Http;


class CustomerController extends Controller
{
    use Filterable;

    protected $code;
    protected $perpage;
    protected $page;
    protected $isactive;
    protected $salesman_id;
    protected $salesmansTable;
    protected $customersTable;
    protected $customersView;
    protected $payplansTable;
    protected $customersLimitTable;
    protected $customersTransactionsTable;
    protected $customersSalesmansRelationsTable;
    protected $specialcodesTable;
    protected $invoicesTable;

    private function fetchValueFromTable($table, $column, $value1, $value2)
    {
        return DB::table($table)
            ->where($column, $value1)
            ->select($value2)
            ->first();
    }

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->perpage = request()->input('per_page', 50);
        $this->page = request()->input('page', 1);
        $this->isactive = $request->input('isactive', 0);
        $this->salesman_id = $request->header('id');
        $this->salesmansTable = 'LG_SLSMAN';
        $this->customersTable = 'LG_' . $this->code . '_CLCARD';
        $this->customersTransactionsTable = 'LG_' . $this->code . '_01_CLFLINE';
        $this->customersView = 'LV_' . $this->code . '_01_CLCARD';
        $this->payplansTable = 'LG_' . $this->code . '_PAYPLANS';
        $this->customersLimitTable = 'LG_' . $this->code . '_01_CLRNUMS';
        $this->customersSalesmansRelationsTable = 'LG_' . $this->code . '_SLSCLREL';
        $this->specialcodesTable = 'LG_' . $this->code . '_SPECODES';
        $this->invoicesTable = 'LG_' . $this->code . '_01_INVOICE';
    }
    public function index(Request $request)
    {
        $data = DB::table($this->customersTable)
            ->leftjoin("$this->customersView", "$this->customersTable.logicalref", '=', "$this->customersView.logicalref")
            ->leftjoin("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->customersTable.paymentref")
            ->leftjoin("$this->customersLimitTable", "$this->customersTable.logicalref", '=', "$this->customersLimitTable.clcardref")
            ->select(
                "$this->customersTable.logicalref as id",
                "$this->customersTable.code",
                "$this->customersTable.definition_ as name",
                "$this->customersTable.addr1 as address",
                "$this->customersTable.telnrs1 as phone",
                DB::raw("COALESCE($this->customersView.debit, 0) as debit"),
                DB::raw("COALESCE($this->payplansTable.code, '') as payment_plan"),
                DB::raw("COALESCE($this->customersView.credit, 0) as credit"),
                DB::raw("COALESCE($this->customersLimitTable.accrisklimit, '') as limit"),
            )->where("$this->customersTable.cardtype", "=", 3);
        if ($request->input('isactive')) {
            $data->where("{$this->customersTable}.active", $this->isactive);
        } else {
            $data->where("{$this->customersTable}.active", 0);
        }
        $this->applyFilters($data, [
            "$this->customersTable.code" => [
                'value' => '%' . $request->input('customer_code') . '%',
                'operator' => 'LIKE',
            ],
            "$this->customersTable.telnrs1" => [
                'value' => $request->input('customer_phone'),
                'operator' => '=',
            ],
            "$this->customersTable.definition_" => [
                'value' => '%' . $request->input('customer_name') . '%',
                'operator' => 'LIKE',
            ],
            "$this->customersView.credit" => [
                'value' => $request->input('credit'),
                'operator' => $request->input('operator'),
            ],
            "$this->customersView.debit" => [
                'value' => $request->input('debit'),
                'operator' => $request->input('operator'),
            ],
        ]);

        $customer = $data->paginate($this->perpage);
        if ($customer->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Customers list',
            'total' => $customer->total(),
            'data' => $customer->items(),
            'current_page' => $customer->currentPage(),
            'per_page' => $customer->perPage(),
            'next_page' => $customer->nextPageUrl($this->page),
            'previous_page' => $customer->previousPageUrl($this->page),
            'first_page' => $customer->url(1),
            'last_page' => $customer->url($customer->lastPage()),
        ], 200);
    }

    public function addCustomerData()
    {
        $salesmans = DB::table($this->salesmansTable)
            ->select('logicalref as salesmsna_id', 'definition_ as salesman_name')
            ->where(['firmnr' => $this->code, 'active' => 0])
            ->get();

        $payplans = DB::table($this->payplansTable)
            ->select('logicalref as payplan_id', 'definition_ as payplan_name')
            ->where(['active' => 0])
            ->get();

        $customers_types = DB::table($this->specialcodesTable)
            ->select(
                'logicalref as type_id',
                'definition_ as type_name',
                'specode as special_code'
            )
            ->where(['codetype' => 1, 'specodetype' => 26, 'spetyp2' => 1])
            ->get();

        $customers_status = [
            [
                'name' => 'active',
                'code' => '0',
            ],
            [
                'name' => 'inactive',
                'code' => '1',
            ],
            [
                'name' => 'pending',
                'code' => '2',
            ],
        ];

        return response()->json([
            'status' => 'Customers data',
            'salesmans' => $salesmans,
            'payplans' => $payplans,
            'customers_types' => $customers_types,
            'customers_status' => $customers_status,
        ]);
    }

    public function store(Request $request)
    {
        $data = [
            'ACCOUNT_TYPE' => 3,
            'RECORD_STATUS' => 2,
            'TITLE' => $request->market_name,
            'TITLE2' => $request->customer_name,
            'TELEPHONE1' => $request->phone,
            'TELEPHONE2' => $request->phone2,
            'CITY' => $request->city,
            'ADDRESS1' => $request->address,
            'ADDRESS2' => $request->zone,
            'COUNTRY' => 'iraq',
            'AUTH_CODE' => '1',
            'AUXIL_CODE2' => '1',
            'PAYMENTREF' => '10',
            'LONGITUDE' => $request->longitude,
            'LATITUDE' => $request->latitude,
        ];
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
                ->post('https://10.27.0.109:32002/api/v1/Arps');
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

    public function updateCustomer(Request $request, $id)
    {
        $data = [
            'TELEPHONE1' => $request->customer_phone,
            'ADDRESS1' => $request->customer_address,
            'LONGITUDE' => $request->longitude,
            'LATITUDE' => $request->latitude,
        ];
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
                ->put("https://10.27.0.109:32002/api/v1/Arps/{$id}");
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

    public function editCustomer($id)
    {
        $customer = DB::table($this->customersTable)
            ->select(
                'definition_ as market_name',
                'addr1 as customer_address',
                'telnrs1 as customer_phone',
                'longitude',
                'latitute'
            )
            ->where('logicalref', $id)
            ->first();
        if ($customer->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'customer informations',
            'data' => $customer
        ]);
    }

    // public function pendingCustomerList(Request $request)
    // {
    //     $customer = DB::table($this->customersTable)
    //         ->join($this->salesmansTable, "{$this->customersTable}.capiblock_createdby", '=', 'lg_slsman.logicalref')
    //         ->select(
    //             "$this->customersTable.logicalref as customer_id",
    //             "$this->customersTable.definition2 as customer_name",
    //             "$this->customersTable.definition_ as market_name",
    //             "$this->customersTable.telnrs1 as first_phone",
    //             "$this->customersTable.code as customer_code",
    //             "$this->customersTable.addr2 as zone",
    //             "lg_slsman.definition_ as salesman_name"
    //         )
    //         ->where("{$this->customersTable}.active", 2)
    //         ->orderby("{$this->customersTable}.logicalref", "desc")
    //         ->get();
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'New customer list',
    //         'data' => $customer,
    //     ]);
    // }

    public function getPendingCustomerList()
    {
        $customer = DB::table("$this->customersTable")
            // ->join("$this->customersSalesmansRelationsTable", "$this->customersSalesmansRelationsTable.clientref", "$this->customersTable.logicalref")
            // ->join("$this->salesmansTable", "$this->customersSalesmansRelationsTable.salesmanref", "$this->salesmansTable.logicalref")
            ->join("$this->customersLimitTable", "$this->customersLimitTable.clcardref", "$this->customersTable.logicalref")
            // ->join("$this->payplansTable", "$this->payplansTable.logicalref", "$this->customersTable.paymentref")
            // ->join("$this->specialcodesTable", "$this->specialcodesTable.specode", "$this->customersTable.specode2")
            // ->where(["$this->specialcodesTable.codetype" => 1, "$this->specialcodesTable.specodetype" => 26])
            ->select(
                "$this->customersTable.logicalref as customer_id",
                "$this->customersTable.definition_ as market_name",
                "$this->customersTable.definition2 as customer_name",
                // "$this->salesmansTable.definition_ as salesman_name",
                "$this->customersTable.telnrs1 as first_phone",
                DB::raw("COALESCE($this->customersTable.telnrs2, '0') as secondPhone"),
                "$this->customersTable.code",
                "$this->customersTable.city",
                "$this->customersTable.addr2 as zone",
                "$this->customersTable.addr1 as address",
                // DB::raw("COALESCE($this->specialcodesTable.specode, '0') as customerType_id"),
                // DB::raw("COALESCE($this->specialcodesTable.definition_, '0') as customerType"),
                // "$this->payplansTable.logicalref as paymentPlan_id",
                // "$this->payplansTable.code as paymentPlan",
                "$this->customersLimitTable.accrisklimit as limit"
            )
            ->where(["$this->customersTable.specode5" => 1])
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Pending Customer List',
            'data' => $customer,
        ], 200);
    }

    public function pendingCustomerDetails(Request $request)
    {
        $customer = $request->header('customer');
        $data = DB::table("$this->customersTable")
            ->leftjoin("$this->customersSalesmansRelationsTable", "$this->customersSalesmansRelationsTable.clientref", '=', "$this->customersTable.logicalref")
            ->join("$this->salesmansTable", "$this->customersSalesmansRelationsTable.salesmanref", '=', "$this->salesmansTable.logicalref")
            ->join("{$this->customersLimitTable}", "{$this->customersLimitTable}.clcardref", "=", "{$this->customersTable}.logicalref")
            ->join("{$this->payplansTable}", "{$this->payplansTable}.logicalref", "=", "{$this->customersTable}.paymentref")
            ->select(
                "{$this->customersTable}.definition2 as customer_name",
                "{$this->customersTable}.definition_ as market_name",
                "$this->salesmansTable.definition_ as salesman_name",
                "{$this->customersTable}.telnrs1 as first_phone",
                "{$this->customersTable}.telnrs2 as second_phone",
                "{$this->customersTable}.code",
                "{$this->customersTable}.city",
                "{$this->customersTable}.addr2 as zone",
                "{$this->customersTable}.addr1 as address",
                "{$this->customersTable}.specode2 as customer_type",
                "{$this->payplansTable}.code as payment_plan",
                DB::raw("COALESCE({$this->customersLimitTable}.accrisklimit, 0) as limit")
            )
            ->where([
                "$this->customersTable.logicalref" => $customer,
                "$this->salesmansTable.active" => 0,
                "$this->salesmansTable.firmnr" => $this->code
            ])
            ->get();
        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Pending customer details',
            'data' => $data,
        ]);
    }

    public function updatePendingCustomerAccounting(Request $request, $id)
    {
        $custData = [
            'TITLE2' => $request->customer_name,
            'TITLE' => $request->market_name,
            'REC_STATUS' => $request->active,
            'TELEPHONE1' => $request->first_phone,
            'TELEPHONE2' => $request->second_phone,
            'CITY' => $request->city,
            'ADDRESS2' => $request->zone,
            'ADDRESS1' => $request->address,
            'AUXIL_CODE2' => $request->customer_type,
            'PAYMENTREF' => $request->payment_plan,
            'RECORD_STATUS' => $request->active,
            'ACC_RISK_LIMIT' => $request->limit,
        ];
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($custData), 'application/json')
                ->put("https://10.27.0.109:32002/api/v1/Arps/{$id}");
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



    public function getcustomerByCode(Request $request)
    {
        $customer = $request->header('customer');
        $data = DB::table("{$this->customersSalesmansRelationsTable}")
            ->join('lg_slsman', "$this->customersSalesmansRelationsTable.salesmanref", '=', 'lg_slsman.logicalref')
            ->join("$this->customersTable", "$this->customersSalesmansRelationsTable.clientref", '=', "$this->customersTable.logicalref")
            ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->customersTable.paymentref")
            ->select(
                "$this->customersSalesmansRelationsTable.salesmanref",
                'lg_slsman.code as salesman_code',
                'lg_slsman.definition_ as salesman_name',
                "$this->customersTable.logicalref as customer_id",
                "$this->customersTable.definition2 as customer_name",
                "$this->customersTable.code as customer_code",
                "$this->payplansTable.code as payplan_code",
                "$this->customersTable.definition_ as market_name",
                "$this->customersTable.addr1 as customer_address",
                "$this->customersTable.PPGROUPCODE as group",
                "$this->customersTable.telnrs1 as customer_phone",
                "$this->customersTable.telnrs2 as second_customer_phone",
                "$this->customersTable.longitude",
                "$this->customersTable.latitute as latitude"
            )
            ->where([
                "{$this->customersTable}.code" => $customer,
                "{$this->customersSalesmansRelationsTable}.salesmanref" => $this->salesman_id
            ])
            ->whereNotNull("{$this->customersTable}.telnrs2")
            ->orderByDesc("{$this->customersSalesmansRelationsTable}.logicalref")
            ->first();
        if (!$data) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => $data
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Customers list',
            'data' => $data,
        ]);
    }

    public function customerDetails(Request $request)
    {
        $results = DB::table("{$this->customersTable}")
            ->join("{$this->customersSalesmansRelationsTable}", "{$this->customersSalesmansRelationsTable}.clientref", '=', "{$this->customersTable}.logicalref")
            ->join("{$this->customersView}", "{$this->customersSalesmansRelationsTable}.clientref", '=', "{$this->customersView}.logicalref")
            ->join("{$this->payplansTable}", "{$this->payplansTable}.logicalref", '=', "{$this->customersTable}.paymentref")
            ->join("lg_slsman", "lg_slsman.logicalref", '=', "{$this->customersSalesmansRelationsTable}.salesmanref")
            ->leftJoin("{$this->customersLimitTable}", function ($join) {
                $join->on("{$this->customersLimitTable}.clcardref", "=", "{$this->customersTable}.logicalref");
            })
            ->select(
                "{$this->customersTable}.logicalref as customer_id",
                "{$this->customersTable}.code as customer_code",
                "{$this->customersTable}.definition_ as customer_name",
                "{$this->customersTable}.addr1 as address",
                "{$this->customersTable}.city",
                "{$this->customersTable}.country",
                "{$this->customersTable}.telnrs1 as customer_phone",
                "{$this->payplansTable}.definition_ as payment_plan",
                DB::raw("COALESCE({$this->customersLimitTable}.debit, 0) as debit"),
                DB::raw("COALESCE({$this->customersLimitTable}.credit, 0) as credit"),
                DB::raw("COALESCE({$this->customersLimitTable}.accrisklimit, 0) as limit")
            )
            ->where(["{$this->customersTable}.active" => '0', 'lg_slsman.logicalref' => $this->salesman_id, 'lg_slsman.active' => 0, "{$this->customersSalesmansRelationsTable}.salesmanref" => $this->salesman_id])
            ->get();
        if ($results->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Customers list',
            'data' => $results,
        ]);
    }

    public function salesmancustomers()
    {
        $data = DB::table($this->customersSalesmansRelationsTable)
            ->join("$this->salesmansTable", "$this->customersSalesmansRelationsTable.salesmanref", '=', "$this->salesmansTable.logicalref")
            ->join("$this->customersTable", "$this->customersSalesmansRelationsTable.clientref", '=', "$this->customersTable.logicalref")
            ->join("$this->customersView", "$this->customersView.logicalref", '=', "$this->customersSalesmansRelationsTable.clientref")
            ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->customersTable.paymentref")
            ->leftjoin("$this->customersLimitTable", "$this->customersLimitTable.clcardref", "=", "$this->customersTable.logicalref")
            ->select(
                "$this->customersSalesmansRelationsTable.salesmanref as salesman_id",
                "$this->salesmansTable.code as salesman_code",
                "$this->salesmansTable.definition_ as salesman_name",
                "$this->customersTable.logicalref as customer_id",
                "$this->customersTable.definition2 as customer_name",
                "$this->customersTable.code as customer_code",
                "$this->payplansTable.code as payment_plan",
                "$this->customersTable.definition_ as market_name",
                "$this->customersTable.addr1 as customer_address",
                "$this->customersTable.PPGROUPCODE as group",
                "$this->customersTable.telnrs1 as customer_phone",
                "$this->customersTable.telnrs2 as second_customer_phone",
                "$this->customersTable.longitude",
                "$this->customersTable.latitute",
                DB::raw("COALESCE($this->customersView.credit, 0) as credit"),
                DB::raw("COALESCE($this->customersView.debit, 0) as debit"),
                DB::raw("COALESCE($this->customersLimitTable.accrisklimit, 0) as limit"),
                DB::raw("COALESCE(
                    CONVERT(VARCHAR, (SELECT TOP 1 CAPIBLOCK_CREADEDDATE
                                     FROM LG_888_01_INVOICE
                                     WHERE LG_888_01_INVOICE.clientref = LG_888_CLCARD.logicalref
                                     ORDER BY logicalref DESC), 120),
                    'No invoice found'
                ) as last_invoice_date
                "),
            )
            ->where(["$this->salesmansTable.logicalref" => $this->salesman_id, "$this->salesmansTable.active" => '0', "$this->customersTable.active" => 0])
            ->orderBy("$this->customersSalesmansRelationsTable.clientref")
            ->get();
        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Customers list',
            'data' => $data,
        ]);
    }

    public function accountingSalesmanCustomers(Request $request)
    {
        $data = DB::table($this->customersSalesmansRelationsTable)
            ->join("$this->salesmansTable", "$this->customersSalesmansRelationsTable.salesmanref", '=', "$this->salesmansTable.logicalref")
            ->join("$this->customersTable", "$this->customersSalesmansRelationsTable.clientref", '=', "$this->customersTable.logicalref")
            ->join("$this->customersView", "$this->customersView.logicalref", '=', "$this->customersSalesmansRelationsTable.clientref")
            ->join("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->customersTable.paymentref")
            ->leftjoin("$this->customersLimitTable", "$this->customersLimitTable.clcardref", "=", "$this->customersTable.logicalref")
            ->select(
                "$this->customersSalesmansRelationsTable.salesmanref as salesman_id",
                "$this->salesmansTable.code as salesman_code",
                "$this->salesmansTable.definition_ as salesman_name",
                "$this->customersTable.logicalref as customer_id",
                "$this->customersTable.definition2 as customer_name",
                "$this->customersTable.code as customer_code",
                "$this->payplansTable.code as payment_plan",
                "$this->customersTable.definition_ as market_name",
                "$this->customersTable.addr1 as address",
                "$this->customersTable.PPGROUPCODE as price_group",
                "$this->customersTable.telnrs1 as customer_phone",
                "$this->customersTable.telnrs2 as second_customer_phone",
                "$this->customersTable.longitude",
                "$this->customersTable.latitute",
                DB::raw("COALESCE($this->customersView.credit, 0) as credit"),
                DB::raw("COALESCE($this->customersView.debit, 0) as debit"),
                DB::raw("COALESCE($this->customersLimitTable.accrisklimit, 0) as limit"),
                DB::raw("COALESCE(
                    CONVERT(VARCHAR, (SELECT TOP 1 CAPIBLOCK_CREADEDDATE
                                     FROM $this->invoicesTable
                                     WHERE $this->invoicesTable.clientref = $this->customersTable.logicalref
                                     ORDER BY logicalref DESC), 120),
                    'No invoice found'
                ) as last_invoice_date
                "),
                DB::raw("COALESCE(
                    CONVERT(VARCHAR, (SELECT TOP 1 DATE_
                    FROM $this->customersTransactionsTable
                    WHERE clientref = $this->customersTable.logicalref and trcode = 1
                    ORDER BY date_ DESC), 120),
                    'No payment found'
                ) as last_payment_date")
            )
            ->where(["$this->salesmansTable.logicalref" => $this->salesman_id, "$this->salesmansTable.active" => '0', "$this->customersTable.active" => 0])
            ->orderBy("$this->customersSalesmansRelationsTable.clientref")->paginate($this->perpage);
        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ], 200);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Customers list',
            'data' => $data->items(),
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'next_page' => $data->nextPageUrl($this->page),
            'previous_page' => $data->previousPageUrl($this->page),
            'last_page' => $data->lastPage(),
            'total' => $data->total(),
        ]);
    }

    public function debitandpayment(Request $request)
    {
        $customer = $request->header('customer');
        $customer_id = DB::table("$this->customersTable")->select('logicalref')->where('code', $customer)->first();

        $last_payment_date = DB::table("$this->customersTransactionsTable")
            ->where(['clientref' => $customer_id->logicalref, 'modulenr' => 10, 'trcode' => 1])
            ->orderByDesc('logicalref')
            ->first();
        $last_invoice_date = DB::table("$this->invoicesTable")
            ->where('clientref' , $customer_id->logicalref)
            ->orderByDesc('logicalref')
            ->first();

        $query = DB::table("$this->customersTable")
            ->leftJoin("$this->customersLimitTable", "$this->customersLimitTable.clcardref", '=', "$this->customersTable.logicalref")
            ->leftJoin("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->customersTable.paymentref")
            ->leftJoin("$this->customersView", "$this->customersView.logicalref", '=', "$this->customersLimitTable.clcardref")
            ->select(
                DB::raw("COALESCE($this->customersLimitTable.accrisklimit, 0) as [limit]"),
                DB::raw("COALESCE($this->payplansTable.code, '0') as payment_plan"),
                DB::raw("ISNULL($this->customersView.debit, 0) - ISNULL($this->customersView.credit, 0) as balance"),
            )
            ->where(["$this->customersTable.code" => $customer])
            ->groupBy("$this->customersLimitTable.accrisklimit", "$this->payplansTable.code", "$this->customersView.debit", "$this->customersView.credit")
            ->first();

        if (!$query) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }

        $lastPaymentDate = $last_payment_date ? \Carbon\Carbon::parse($last_payment_date->DATE_)->format('Y-m-d') : null;
        $lastPaymentAmount = $last_payment_date ? (number_format($last_payment_date->AMOUNT, 2, '.', '')) : null;
        $lastInvoiceDate = $last_invoice_date ? \Carbon\Carbon::parse($last_invoice_date->DATE_)->format('Y-m-d') : null;
        $lastInvoiceAmount = $last_invoice_date ? (number_format($last_invoice_date->GROSSTOTAL, 2, '.', '')) : null;
        return response()->json([
            'status' => 'success',
            'message' => 'Customer details',
            'data' => [
                'limit' => number_format($query->limit, 2, '.', ''),
                'payment_plan' => $query->payment_plan,
                'balance' => number_format($query->balance, 2, '.', ''),
                'last_payment_date' => $lastPaymentDate,
                'last_payment_amount' => $lastPaymentAmount,
                'last_invoice_date' => $lastInvoiceDate,
                'last_invoice_amount' => $lastInvoiceAmount,
            ],
        ]);
    }

    public function allCustomers()
    {
        $customers = DB::select("select lg_329_clcard.logicalref as customer_id,
        lg_329_clcard.definition_ as customer_name,
        lg_329_clcard.code as customer_code,
        lg_329_clcard.addr1 as address,
        lg_329_clcard.city,
        lg_329_clcard.country,
        lg_329_clcard.telnrs1 as phone,
        lg_329_clcard.longitude,
        lg_329_clcard.latitute,
        lg_slsman.logicalref as salesman_id,
        lg_slsman.code as salesman_code,
        lg_slsman.definition_ as salesman_name,
        lg_slsman.firmnr as salesman_city_code,
        lg_329_clcard.logicalref
        from lg_329_clcard
        left join
        (select * from lg_329_slsclrel
        where logicalref in
        (select max(logicalref) from lg_329_slsclrel
        group by clientref)
        ) as n2
        on n2.clientref=
        lg_329_clcard.logicalref
        left join lg_slsman on
        n2.salesmanref = lg_slsman.LOGICALREF");
        return response()->json([
            'message' => 'customers list',
            'data' => $customers
        ]);
    }


    public function storeFromAccounting(Request $request)
    {
        $user_nr = $this->fetchValueFromTable('l_capiuser', 'name', request()->header('username'), 'nr');
        $defaultValues = [
            'RECORD_STATUS' => 0,
            'ACCOUNT_TYPE' => 3,
            'TITLE' => $request->market_name,
            'TITLE2' => $request->customer_name,
            'TELEPHONE1' => $request->phone,
            'TELEPHONE2' => $request->phone2,
            'CITY' => $request->city,
            'ADDRESS1' => $request->address,
            'ADDRESS2' => $request->zone,
            'COUNTRY' => 'iraq',
            'AUTH_CODE' => '1',
            'AUXIL_CODE2' => $request->customer_type,
            'AUXIL_CODE5' => 1,
            'CREATED_BY' => $user_nr->nr,
            'ACC_RISK_LIMIT' => $request->limit,
        ];
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($defaultValues), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/Arps');
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
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->delete("https://10.27.0.109:32002/api/v1/Arps/{$id}");
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'message' => 'Customer deleted succssefully'
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function searchCustomerByCode()
    {
        $code = request()->input('customer_code');
        $customer = DB::table("$this->customersTable")
            ->leftjoin("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->customersTable.paymentref")
            ->select(
                "$this->customersTable.logicalref as id",
                "$this->customersTable.code",
                "$this->customersTable.definition_ as name",
                DB::raw("COALESCE($this->payplansTable.code, '') as payment_plan"),
            )
            ->where("$this->customersTable.cardtype", 3)
            ->where("$this->customersTable.code", "LIKE", '%' . $code . '%')
            ->get();

        return response()->json([
            'message' => 'customer info',
            'data' => $customer
        ]);
    }
}
