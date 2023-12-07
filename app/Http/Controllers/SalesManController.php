<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesManController extends Controller
{
    protected $code;
    protected $isactive;
    protected $perpage;
    protected $page;
    protected $salesman_id;
    protected $salesmansTable;
    protected $ordersTable;
    protected $customersTable;
    protected $invoicesTable;
    protected $customersTransactionsTable;
    protected $safesTable;
    protected $specialcodesTable;

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->isactive = $request->input('isactive');
        $this->perpage = request()->input('per_page', 50);
        $this->page = request()->input('page', 1);
        $this->salesman_id = $request->header('id');
        $this->salesmansTable = 'LG_SLSMAN';
        $this->ordersTable = 'LG_' . $this->code . '_01_ORFICHE';
        $this->customersTable = 'LG_' . $this->code . '_CLCARD';
        $this->invoicesTable = 'LG_' . $this->code . '_01_INVOICE';
        $this->customersTransactionsTable = 'LG_' . $this->code . '_01_CLFLINE';
        $this->safesTable = 'LG_' . $this->code . '_KSCARD';
        $this->specialcodesTable = 'LG_' . $this->code . '_SPECODES';
    }

    public function index(Request $request)
    {
        $position = $request->input('position');
        $salesman = DB::table($this->salesmansTable)
            ->select('LOGICALREF as id', 'code', 'DEFINITION_ as name', 'TELNUMBER as phone')
            ->where(['FIRMNR' => $this->code, 'ACTIVE' => $this->isactive, 'POSITION_' => $position])->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Salesman list',
            'data' => $salesman,

        ]);
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

    public function salesmaninvoice(Request $request)
    {
        $invoicetype = $request->header('invoicetype');
        $invoice = DB::table($this->customersTable)
            ->join("$this->invoicesTable", "$this->customersTable.logicalref", "=", "$this->invoicesTable.clientref")
            ->join("$this->customersTransactionsTable", "$this->invoicesTable.logicalref", "=", "$this->customersTransactionsTable.sourcefref")
            ->join('lg_slsman', "lg_slsman.logicalref", "=", "$this->invoicesTable.salesmanref")
            ->select(
                "$this->customersTable.code as customer_code",
                "$this->customersTable.definition_ as customer_name",
                "$this->invoicesTable.ficheno as invoice_number",
                "$this->invoicesTable.date_ as invoice_date",
                "$this->customersTransactionsTable.amount as total_amount",
                "$this->invoicesTable.grosstotal as weight"
            )
            ->orderBy("$this->invoicesTable.date_", 'desc')
            ->where(["$this->invoicesTable.trcode" => $invoicetype, 'lg_slsman.logicalref' => $this->salesman_id])
            ->paginate($this->perpage);
        if ($invoice->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Customer list',
            'data' => $invoice->items(),
            'current_page' => $invoice->currentPage(),
            'per_page' => $invoice->perPage(),
            'last_page' => $invoice->lastPage(),
            'total' => $invoice->total(),
        ], 200);
    }

    public function store(Request $request)
    {
        $latest_salesman_specode = DB::table($this->specialcodesTable)->where(['codetype' => 1, 'specodetype' => 50])->orderby('logicalref', 'desc')->value('specode');
        $latest_safe_specode = DB::table($this->specialcodesTable)->where(['codetype' => 1, 'specodetype' => 34])->orderby('logicalref', 'desc')->value('specode');
        $user = DB::table("L_CAPIUSER")->where('name', request()->header('username'))
            ->value('nr');
        $salesman = [
            'code' => $request->salesman_code,
            'definition_' => $request->salesman_name,
            'position_' => $request->position,
            'cardtype' => 0,
            'specode' => $latest_salesman_specode + 1,
            'firmnr' => $this->code,
            'active' => 0,
            "CAPIBLOCK_CREATEDBY" => $user,
            'capiblock_creadeddate' => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            'capiblock_createdhour' => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            'capiblock_createdmin' => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            'capiblock_createdsec' => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            'active' => 0,
            'telnumber' => $request->phone_number,
        ];
        $dinar_safe = [
            'code' => $request->dinar_safe_code,
            'specode' => $latest_safe_specode + 1,
            'cyphcode' => 3,
            'name' => $request->salesman_name,
            'explain' => $request->dinar_safe_explain,
            "CREATED_BY" => request()->header('username'),            'capiblock_creadeddate' => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            'capiblock_createdhour' => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            'capiblock_createdmin' => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            'capiblock_createdsec' => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            'active' => 0,
            'recstatus' => 1,
            'ccurrency' => 30,
            'curratetype' => 2,
            'branch' => -1,
        ];
        $salesman['specode'] = $latest_salesman_specode + 1;
        $salesman_specode = [
            'codetype' => 1,
            'specodetype' => 50,
            'specode' => $latest_salesman_specode + 1,
            'definition_' => $request->dinar_safe_code,
            'recstatus' => 1,
        ];
        $safe_specode = [
            'codetype' => 1,
            'specodetype' => 34,
            'specode' => $latest_safe_specode + 1,
            'definition_' => $request->dollar_safe_code,
            'recstatus' => 1,
        ];
        $dollar_safe = [
            'code' => $request->dollar_safe_code,
            'specode' => $latest_safe_specode + 2,
            'cyphcode' => 3,
            'name' => $request->salesman_name,
            'explain' => $request->dollar_safe_explain,
            "CREATED_BY" => request()->header('username'),            'capiblock_creadeddate' => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            'capiblock_createdhour' => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            'capiblock_createdmin' => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            'capiblock_createdsec' => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            'active' => 0,
            'recstatus' => 1,
            'ccurrency' => 0,
            'curratetype' => 1,
            'branch' => -1,
        ];
        DB::beginTransaction();
        DB::table($this->salesmansTable)->insert($salesman);
        DB::table($this->safesTable)->insert($dinar_safe);
        DB::table($this->safesTable)->insert($dollar_safe);
        DB::table($this->specialcodesTable)->insert($salesman_specode);
        DB::table($this->specialcodesTable)->insert($safe_specode);
        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Salesman added successfully',
            'data' => $salesman,
            'Dinar Safe' => $dinar_safe,
            'Dollar Safe' => $dollar_safe,
        ]);
    }
}
