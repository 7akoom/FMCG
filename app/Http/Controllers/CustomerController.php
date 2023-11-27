<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Traits\Filterable;


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
            "$this->customersTable.definition_" => [
                'value' => '%' . $request->input('customer_name') . '%',
                'operator' => 'LIKE',
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
            'data' => $customer->items(),
            'current_page' => $customer->currentPage(),
            'per_page' => $customer->perPage(),
            'next_page' => $customer->nextPageUrl($this->page),
            'previous_page' => $customer->previousPageUrl($this->page),
            'first_page' => $customer->url(1),
            'last_page' => $customer->url($customer->lastPage()),
            'total' => $customer->total(),
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
            ['active' => '0'],
            ['inactive' => '1'],
            ['pending' => '2'],
        ];

        return response()->json([
            'status' => 'success',
            'salesmans' => $salesmans,
            'payplans' => $payplans,
            'customers_types' => $customers_types,
            'customers_status' => $customers_status,
        ]);
    }


    public function store(Request $request)
    {
        try {
            $sls = $this->fetchValueFromTable($this->salesmansTable, 'logicalref', $this->salesman_id, 'position_');
            $validateData = $request->validate([
                'market_name' => 'required',
                'customer_name' => 'required',
                'phone' => 'required|unique:' . $this->customersTable . ',telnrs1',
                'city' => 'required',
                'address' => 'required',
                'zone' => 'required',
                'longitude' => 'required',
                'latitude' => 'required',
            ]);
            $columnNames = Schema::getColumnListing($this->customersTable);
            $defaultValues = array_fill_keys($columnNames, 0);
            $defaultValues = [
                'active' => 2,
                'cardtype' => 3,
                'definition_' => $request->market_name,
                'definition2' => $request->customer_name,
                'telnrs1' => $request->phone,
                'telnrs2' => $request->phone2,
                'city' => $request->city,
                'addr1' => $request->address,
                'addr2' => $request->zone,
                'country' => 'iraq',
                'cyphcode' => '1',
                'paymentref' => '10',
                'longitude' => $request->longitude,
                'latitute' => $request->latitude,
                'capiblock_createdby' => $this->salesman_id,
            ];
            $sls == 2 ? $defaultValues['specode2'] = 3 : $defaultValues['specode2'] = 2;
            $limitNames = Schema::getColumnListing($this->customersLimitTable);
            $limitValues = array_fill_keys($limitNames, 0);
            DB::beginTransaction();
            if ($sls && $sls->position_ == 1) {
                $latestCode = DB::table($this->customersTable)
                    ->where('code', 'like', '120.%')
                    ->orderBy('logicalref', 'desc')
                    ->value('code');
                $defaultValues['code'] = '120.' . str_pad(substr($latestCode, 4) + 1, 4, '0', STR_PAD_LEFT);
            } else if ($sls && $sls->position_ == 2) {
                $latestCode = DB::table($this->customersTable)
                    ->where('code', 'like', '180.%')
                    ->orderBy('logicalref', 'desc')
                    ->value('code');
                $defaultValues['code'] = '180.' . str_pad(substr($latestCode, 4) + 1, 4, '0', STR_PAD_LEFT);
            }
            $logicalref = DB::table($this->customersTable)->insertGetId($defaultValues);
            $limitValues = [
                'clcardref' => $logicalref,
                'accrisklimit' => '0'
            ];
            DB::table($this->customersLimitTable)->insert($limitValues);
            DB::table($this->customersSalesmansRelationsTable)->insert([
                'SALESMANREF' => $this->salesman_id,
                'CLIENTREF' => $logicalref,
            ]);
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Customers inserted successfully',
                'data' => $defaultValues,
            ], 200);
        } catch (ValidationException $e) {
            $errors = $e->validator->errors()->getMessages();
            $errorMsg = [];
            foreach ($errors as $key => $value) {
                $errorMsg[$key] = $value[0];
            }
            return response()->json([
                'errors' => $errorMsg,
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

    public function updateCustomer(Request $request, $id)
    {
        $data = ([
            'definition2' => $request->customer_name,
            'definition_' => $request->market_name,
            'addr1' => $request->customer_address,
            'telnrs1' => $request->customer_phone,
            'longitude' => $request->longitude,
            'latitute' => $request->latitute,
        ]);
        DB::table($this->customersTable)
            ->where('logicalref', $id)
            ->update($data);
        return response()->json([
            'status' => 'success',
            'message' => 'customer updated successfully',
            'data' => $data
        ]);
    }

    public function newCustomer(Request $request)
    {
        $customer = DB::table($this->customersTable)
            ->join($this->salesmansTable, "{$this->customersTable}.capiblock_createdby", '=', 'lg_slsman.logicalref')
            ->select(
                "$this->customersTable.logicalref as customer_id",
                "$this->customersTable.definition2 as customer_name",
                "$this->customersTable.definition_ as market_name",
                "$this->customersTable.telnrs1 as first_phone",
                "$this->customersTable.code as customer_code",
                "$this->customersTable.addr2 as zone",
                "lg_slsman.definition_ as salesman_name"
            )
            ->where("{$this->customersTable}.active", 2)
            ->orderby("{$this->customersTable}.logicalref", "desc")
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'New customer list',
            'data' => $customer,
        ]);
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
                "{$this->customersTable}.spcode2 as customer_type",
                "{$this->payplansTable}.code as payment_plan",
                DB::raw("COALESCE({$this->customersLimitTable}.accrisklimit, 0) as limit")
            )
            ->where([
                "$this->customersTable.logicalref" => $customer, "$this->salesmansTable.active" => 0, "$this->salesmansTable.firmnr" => $this->code
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

    public function UpdatePendingCustomer(Request $request, $id)
    {
        $customer = DB::table("{$this->customersTable}")->where('logicalref', $id)->first();
        $limit = DB::table("{$this->customersLimitTable}")->where('clcardref', $id)->first();
        $custData = [
            'definition2' => $request->customer_name,
            'definition_' => $request->market_name,
            'telnrs1' => $request->first_phone,
            'telnrs2' => $request->second_phone,
            'city' => $request->city,
            'addr2' => $request->zone,
            'addr1' => $request->address,
            'ppgroupcode' => $request->customer_type,
            'paymentref' => $request->payment_plan,
            'active' => $request->active,
        ];
        $limitData = [
            'accrisklimit' => $request->limit
        ];
        DB::beginTransaction();
        DB::table("{$this->customersTable}")->where('logicalref', $id)->update($custData);
        DB::table("{$this->customersLimitTable}")->where('clcardref', $id)->update($limitData);
        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Customer updated successfully',
            'data' => $custData,
        ], 200);
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
        // return response()->json($data);
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
                                     FROM LG_888_01_INVOICE
                                     WHERE LG_888_01_INVOICE.clientref = LG_888_CLCARD.logicalref
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
        $results = DB::table("$this->customersTable")
            ->leftJoin("$this->customersLimitTable", "$this->customersLimitTable.clcardref", '=', "$this->customersTable.logicalref")
            ->leftJoin("$this->payplansTable", "$this->payplansTable.logicalref", '=', "$this->customersTable.paymentref")
            ->leftJoin("$this->customersView", "$this->customersView.logicalref", '=', "$this->customersLimitTable.clcardref")
            ->leftJoin("$this->invoicesTable", "$this->customersView.logicalref", '=', "$this->invoicesTable.clientref")
            ->select(
                DB::raw("COALESCE($this->customersLimitTable.accrisklimit, 0) as limit"),
                DB::raw("COALESCE($this->payplansTable.code, '0') as payment_plan"),
                DB::raw("COALESCE($this->customersView.debit, 0) as debit"),
                DB::raw("COALESCE($this->customersView.credit, 0) as credit"),
                DB::raw("COALESCE(CONVERT(varchar(10), MAX($this->invoicesTable.date_), 120), 'No invoice found') as last_invoice_date")
            )
            ->where(["$this->customersTable.code" => $customer])
            ->groupBy("$this->customersLimitTable.accrisklimit", "$this->payplansTable.code", "$this->customersView.debit", "$this->customersView.credit")
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
            'message' => 'Customer details',
            'data' => $results,
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
    public function getPendingCustomerList()
    {
        $customer = DB::table("$this->customersTable")
            ->join("$this->customersSalesmansRelationsTable", "$this->customersSalesmansRelationsTable.clientref", "$this->customersTable.logicalref")
            ->join("$this->salesmansTable", "$this->customersSalesmansRelationsTable.salesmanref", "$this->salesmansTable.logicalref")
            ->join("$this->customersLimitTable", "$this->customersLimitTable.clcardref", "$this->customersTable.logicalref")
            ->join("$this->payplansTable", "$this->payplansTable.logicalref", "$this->customersTable.paymentref")
            ->join("$this->specialcodesTable", "$this->specialcodesTable.specode", "$this->customersTable.specode2")
            ->where(["$this->specialcodesTable.codetype" => 1, "$this->specialcodesTable.specodetype" => 26, "$this->specialcodesTable.spetyp2" => 1])
            ->select(
                "$this->customersTable.definition_ as market_name",
                "$this->customersTable.definition2 as customer_name",
                "$this->salesmansTable.definition_ as salesman_name",
                "$this->customersTable.telnrs1 as first_phone",
                DB::raw("COALESCE($this->customersTable.telnrs2, '0') as secondPhone"),
                "$this->customersTable.code",
                "$this->customersTable.city",
                "$this->customersTable.addr2 as zone",
                "$this->customersTable.addr1 as address",
                DB::raw("COALESCE($this->specialcodesTable.definition_, '0') as customerType"),
                "$this->payplansTable.code as paymentPlan",
                "$this->customersLimitTable.accrisklimit as limit"
            )
            ->where("$this->customersTable.active", 2)
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Pending Customer List',
            'data' => $customer,
        ], 200);
    }
}
