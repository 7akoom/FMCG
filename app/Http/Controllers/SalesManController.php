<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

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

    public function store(Request $request)
    {
        $user = DB::table("L_CAPIUSER")->where('name', request()->header('username'))
            ->value('nr');
        $last_salesman_specode = DB::table($this->specialcodesTable)
            ->where(['codetype' => 1, 'specodetype' => 50])
            ->orderby('logicalref', 'desc')
            ->value('specode');
        $special_code = $last_salesman_specode + 1;
        $salesman_specode = [
            'CODE_TYPE' => 1,
            'SPE_CODE_TYPE' => 50,
            'CODE' => $special_code,
            'DEFINITION' => $request->kasa_code,
        ];
        $safe_specode = [
            'CODE_TYPE' => 1,
            'SPE_CODE_TYPE' => 34,
            'CODE' => $special_code,
            'DEFINITION' => $request->kasa_code,
        ];
        $salesman = [
            'CODE' => $request->salesmna_code,
            'NAME' => $request->salesman_name,
            'POSITION' => $request->position,
            'AUXIL_CODE' => $special_code,
            'FIRM_NO' => $this->code,
            "CREATED_BY" => $user,
            'DATE_CREATED' => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            'HOUR_CREATED' => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            'MIN_CREATED' => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            'SEC_CREATED' => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            'TELNUMBER' => $request->phone_number,
        ];
        $safe = [
            'CODE' => $request->kasa_code,
            'DESCRIPTION' => $salesman['NAME'],
            'AUXIL_CODE' => $special_code,
            'AUTH_CODE' => 3,
            "USAGE_NOTE" => $request->kasa_description,
            'CREATED_BY' => $user,
            'DATE_CREATED' => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            'HOUR_CREATED' => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            'MIN_CREATED' => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            'SEC_CREATED' => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            'CCURRENCY' => $request->currency_type,
        ];
        $salesman_password = hash::make($request->salesman_password);
        $salesman_imei = $request->salesman_imei;

        try {
            $response2 = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($salesman_specode), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/specialCodes');
            $specode_response = $response2->json();

            $response4 = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($safe_specode), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/specialCodes');
            $safe_response = $response4->json();

            $response1 = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($salesman), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/salesmen');
            $salesman_response = $response1->json();
            $response3 = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($safe), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/safeDeposits');
            $safe_response = $response3->json();
            $last_slsman = DB::connection('sqlsrv')
                ->table($this->salesmansTable)
                ->select('logicalref')
                ->where(['active' => 0, 'firmnr' => 888])
                ->orderby('logicalref', 'desc')
                ->first();

            if ($last_slsman) {
                $last_slsman_value = $last_slsman->logicalref;
                $final = [
                    'SLSMAN_LOGICALREF' => $last_slsman_value,
                    'PASSWORD' => hash::make($salesman_password),
                    'IMEI' => $salesman_imei,
                ];
                DB::connection('sqlsrv3')
                    ->table('LG_XT_SALESMAN')
                    ->insert($final);
                return response()->json([
                    'status' => $response1->successful() && $response2->successful()
                        && $response3->successful() ? 'success' : 'failed',
                    'data' => [
                        'salesman_specode' => $specode_response,
                        'safe_specode' => $safe_response,
                        'salesman' => $salesman_response,
                        'last_slsman' => $last_slsman_value,
                        'safe' => $safe_response,
                    ],
                ], $response1->status());
            } else {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'No logicalref found for the given conditions.',
                ], 422);
            }
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function edit($id)
    {
        $salesman_info = DB::table($this->salesmansTable)
            ->where('logicalref', $id)
            ->select(
                'code',
                'active',
                'definition_ as name',
                'position_ as position',
                'specode as special_code',
                'firmnr as city_code',
                'telnumber as phone_number'
            )
            ->first();
        $salesman_imei = DB::connection('sqlsrv3')
            ->table('LG_XT_SALESMAN')
            ->select('imei')
            ->where('slsman_logicalref', $id)
            ->first();
            if ($salesman_info && $salesman_imei) {
            return response()->json([
                'success' => true,
                'data' => [
                    'salesman_info' => $salesman_info,
                    'imei' => $salesman_imei
                ]
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Salesman does not exist',
                'data' => [],
            ], 404);
        }
    }
    public function update($id)
    {
        $salesmanExists = DB::table($this->salesmansTable)->where('logicalref', $id)->exists();

        if (!$salesmanExists) {
            return response()->json([
                'success' => false,
                'message' => 'Salesman not found',
                'data' => [],
            ], 404);
        }
        $updateData = [
            'CODE' => request('code'),
            'NAME' => request('name'),
            'POSITION' => request('position'),
            'AUXIL_CODE' => request('special_code'),
            'FIRM_NO' => request('city_code'),
            'TELNUMBER' => request('phone_number'),
        ];
        $password = request('password');
        $info = [
            'password' => hash::make($password),
            'imei' => request('imei'),
        ];
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($updateData), 'application/json')
                ->patch("https://10.27.0.109:32002/api/v1/salesmen/{$id}");
            $salesman_response = $response->json();
            DB::connection('sqlsrv3')
                ->table('LG_XT_SALESMAN')
                ->where('slsman_logicalref', $id)
                ->update($info);
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => [
                    'salesman' => $salesman_response,
                    'info' => $info,
                ],
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
                'data' => [],
            ], 422);
        }
    }

    public function destroy($id)
    {
        try {
            $salesmanExists = DB::table($this->salesmansTable)->where('logicalref', $id)->exists();
            if (!$salesmanExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salesman not found',
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
                ->delete("https://10.27.0.109:32002/api/v1/salesmen/{$id}");
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'message' => 'Salesman deleted succssefully'
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}


    // public function previousorders(Request $request)
    // {
    //     $orders = DB::table($this->salesmansTable)
    //         ->join($this->ordersTable, "$this->ordersTable.salesmanref", "=", 'lg_slsman.logicalref')
    //         ->join($this->customersTable, "$this->ordersTable.clientref", "=", "$this->customersTable.logicalref")
    //         ->select(
    //             "$this->ordersTable.logicalref as order_id",
    //             "$this->ordersTable.ficheno as order_number",
    //             "$this->ordersTable.capiblock_creadeddate as order_date",
    //             "$this->ordersTable.status as order_status",
    //             "$this->ordersTable.nettotal as order_amount",
    //             "$this->customersTable.definition_ as customer_name"
    //         )
    //         ->whereMonth("$this->ordersTable.capiblock_creadeddate", '=', now()->month)
    //         ->where("$this->ordersTable.salesmanref", $this->salesman_id)
    //         ->orderby("$this->ordersTable.capiblock_creadeddate", "desc")
    //         ->get();
    //     if ($orders->isEmpty()) {
    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'There is no data',
    //             'data' => [],
    //         ]);
    //     }
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Salesman list',
    //         'data' => $orders,
    //     ]);
    // }

    // public function salesmaninvoice(Request $request)
    // {
    //     $invoicetype = $request->header('invoicetype');
    //     $invoice = DB::table($this->customersTable)
    //         ->join("$this->invoicesTable", "$this->customersTable.logicalref", "=", "$this->invoicesTable.clientref")
    //         ->join("$this->customersTransactionsTable", "$this->invoicesTable.logicalref", "=", "$this->customersTransactionsTable.sourcefref")
    //         ->join('lg_slsman', "lg_slsman.logicalref", "=", "$this->invoicesTable.salesmanref")
    //         ->select(
    //             "$this->customersTable.code as customer_code",
    //             "$this->customersTable.definition_ as customer_name",
    //             "$this->invoicesTable.ficheno as invoice_number",
    //             "$this->invoicesTable.date_ as invoice_date",
    //             "$this->customersTransactionsTable.amount as total_amount",
    //             "$this->invoicesTable.grosstotal as weight"
    //         )
    //         ->orderBy("$this->invoicesTable.date_", 'desc')
    //         ->where(["$this->invoicesTable.trcode" => $invoicetype, 'lg_slsman.logicalref' => $this->salesman_id])
    //         ->paginate($this->perpage);
    //     if ($invoice->isEmpty()) {
    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'There is no data',
    //             'data' => [],
    //         ]);
    //     }
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Customer list',
    //         'data' => $invoice->items(),
    //         'current_page' => $invoice->currentPage(),
    //         'per_page' => $invoice->perPage(),
    //         'last_page' => $invoice->lastPage(),
    //         'total' => $invoice->total(),
    //     ], 200);
    // }
