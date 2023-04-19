<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LG_CLCARD;
use App\Models\LG_SLSCLREL;
use App\Models\LG_PAYPLANS; 
use App\Models\LV_01_CLCARD;
use App\Models\LG_01_CLRNUMS;
use App\Models\LG_01_INVOICE;
use App\Models\LG_01_ORFICHE;

use App\Imports\CustomerImport;
use App\Exports\CustomerExport;
use Maatwebsite\Excel\Facades\Excel;

class CustomerController extends Controller
{
    // retrieve customers list according on citycode 
    public function index(Request $request)
    {
        $code = $request->header('citycode');
        $perpage = request()->input('per_page', 15);
        $tableName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $data = DB::table($tableName)->select('logicalref as id','code','definition_ AS arabic_name',
        'definition2 AS turkish_name','addr1 as address','city','country','telnrs1 as phone_number','guid as image')
        ->paginate($perpage);
        return response()->json([
            'status' => 'success',
            'message' => 'Customer list',
            'data' => $data->items(),
            'current_page' => $data->currentPage(),
            'per_page' => $data->perPage(),
            'last_page' => $data->lastPage(),
            'total' => $data->total(),
        ], 200);
    }
    // retrieve customer by code
    public function getcustomerByCode(Request $request)
    {
        $code = $request->header('citycode');
        $customer = $request->header('customer');
        $relName = str_replace('{code}', $code, (new LG_SLSCLREL)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $data = DB::table("{$relName}")
        ->join('lg_slsman', "{$relName}.salesmanref", '=', 'lg_slsman.logicalref')
        ->join("{$custName}", "{$relName}.clientref", '=', "{$custName}.logicalref")
        ->select("{$relName}.salesmanref",'lg_slsman.code as salesman_code','lg_slsman.definition_ as salesman_name',"{$custName}.logicalref as customer_id",
        "{$custName}.definition2 as customer_name","{$custName}.code as customer_code", "{$custName}.definition_ as market_name","{$custName}.addr1 as customer_address",
        "{$custName}.PPGROUPCODE as group","{$custName}.telnrs1 as customer_phone","{$custName}.telnrs2 as second_customer_phone","{$custName}.longitude",
        "{$custName}.latitute as latitude")
        ->where(["{$custName}.code" => $customer, "{$relName}.salesmanref" => DB::raw('lg_slsman.logicalref')])
        ->whereNotNull("{$custName}.telnrs2")
        ->orderByDesc("{$relName}.logicalref")
        ->first();
        return response()->json([
            'status' => 'success',
            'message' => 'Customers list',
            'data' => $data,
        ]); 
        return response()->json($data); 
    }
    // retrieve customer details (debit, credit, limit,......) depending on salesman logicalref
    public function customerDetails(Request $request)
    {
        $slsman = $request->header('id');
        $code = $request->header('citycode');
        $relName = str_replace('{code}', $code, (new LG_SLSCLREL)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $ppName = str_replace('{code}', $code, (new LG_PAYPLANS)->getTable());
        $clcName = str_replace('{code}', $code, (new LV_01_CLCARD)->getTable());
        $clrName = str_replace('{code}', $code, (new LG_01_CLRNUMS)->getTable());
        $results = DB::table("{$relName}")
            ->join('lg_slsman', "{$relName}.salesmanref", '=', 'lg_slsman.logicalref')
            ->join("{$custName}", "{$relName}.clientref", '=', "{$custName}.logicalref")
            ->join("{$clcName}","{$relName}.clientref",'=',"{$clcName}.logicalref")
            ->join("{$ppName}","{$ppName}.logicalref",'=',"{$custName}.paymentref")
            ->join("{$clrName}","{$clrName}.clcardref",'=',"{$custName}.logicalref")
            ->select("{$custName}.logicalref as customer_id",
            "{$custName}.code as customer_code", "{$custName}.definition_ as customer_name", "{$custName}.addr1 as address","{$custName}.city",
            "{$custName}.country","{$custName}.telnrs1 as customer_phone","{$clcName}.debit","{$ppName}.definition_ as payment_plan",
            "{$clcName}.credit","{$clrName}.accrisklimit as customer_limit")
            ->where("{$custName}.country" ,"!=", "stop")
            ->where(['lg_slsman.logicalref' => $slsman,'lg_slsman.active' => '0',"{$custName}.active" => '0'])
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Customers list',
            'data' => $results,
        ]);
    }
        // retrieve active or nonactive customers list depending on salesman logicalref
    public function salesmancustomers(Request $request)
    {
        $slsman = $request->header('id');
        $code = $request->header('citycode');
        $isactive = $request->header('isactive');
        $relName = str_replace('{code}', $code, (new LG_SLSCLREL)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $data = DB::table("{$relName}")
        ->join('lg_slsman', "{$relName}.salesmanref", '=', 'lg_slsman.logicalref')
        ->join("{$custName}", "{$relName}.clientref", '=', "{$custName}.logicalref")
        ->select("{$relName}.salesmanref",'lg_slsman.code as salesman_code','lg_slsman.definition_ as salesman_name',"{$custName}.logicalref as customer_id",
        "{$custName}.definition2 as customer_name","{$custName}.code as customer_code", "{$custName}.definition_ as market_name","{$custName}.addr1 as customer_address",
        "{$custName}.PPGROUPCODE as group","{$custName}.telnrs1 as customer_phone","{$custName}.telnrs2 as second_customer_phone","{$custName}.longitude","{$custName}.latitute")
        ->where(['lg_slsman.logicalref' => $slsman,'lg_slsman.active' => '0',"{$custName}.active" => $isactive,])
        ->whereNotNull("{$custName}.telnrs2")
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Customers list',
            'data' => $data,
        ]); 
    } 
    // retrieve customer debit and limit 
    public function debitandpayment(Request $request)
    {
        $code = $request->header('citycode');
        $customer = $request->header('customer');
        $relName = str_replace('{code}', $code, (new LG_SLSCLREL)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $ppName = str_replace('{code}', $code, (new LG_PAYPLANS)->getTable());
        $clcName = str_replace('{code}', $code, (new LV_01_CLCARD)->getTable());
        $clrName = str_replace('{code}', $code, (new LG_01_CLRNUMS)->getTable());
        $invName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
        $results = DB::table("{$custName}")
            ->join("{$clrName}","{$clrName}.clcardref",'=',"{$custName}.logicalref")
            ->join("{$ppName}","{$ppName}.logicalref",'=',"{$custName}.paymentref")
            ->join("{$clcName}","{$clcName}.logicalref",'=',"{$clrName}.clcardref")
            ->join("{$invName}","{$clcName}.logicalref",'=',"{$invName}.clientref")
            ->select("{$clrName}.accrisktotal as limit","{$ppName}.code as payment_plan","{$clcName}.debit","{$clcName}.credit",
            DB::raw("(MAX({$invName}.date_)) as last_invoice_date"))
            ->where(["{$custName}.code" => $customer])
            ->groupBy("{$clrName}.accrisktotal", "{$ppName}.code", "{$clcName}.debit", "{$clcName}.credit")
            ->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Customer details',
                'data' => $results,
            ]);
    }

//     public function store(Request $request)
//     {
//         $code = $request->header('code');
//         $tableName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
//         $validatedData = $request->validate([
//             'CODE' => 'unique:LG_325_CLCARD,CODE',
//         ]);
//         $customer = new LG_CLCARD;
//         $customer->setTable($tableName);
//         $customer->CODE = $request->code;
//         $customer->DEFINITION_ = $request->definition;
//         $customer->save();
//         return response()->json([
//             'status' => 'success',
//             'message' => 'Customer added successfully',
//             'data' => $customer,
//         ], 200);
//     }
    
//     public function update(Request $request, $id)
//     {
//         $code = $request->header('code');
//         $tableName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
//         $customerClass = new LG_CLCARD;
//         $customerClass->setTable($tableName);
//         $customer = $customerClass->where('LOGICALREF', $id)->first();
//         if (!$customer) {
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'Customer not found',
//             ], 404);
//         }
//         $oldValues = [];
//         if ($request->has('code')) {
//             $oldValues['code'] = $customer->CODE;
//             $customer->CODE = $request->input('code');
//         }
//         if ($request->has('definition')) {
//             $oldValues['definition'] = $customer->DEFINITION_;
//             $customer->DEFINITION_ = $request->input('definition');
//         }
//         $customer->save();
//         foreach ($oldValues as $key => $value) {
//             $customer->$key = $value;
//         }
//         return response()->json([
//             'status' => 'success',
//             'message' => 'Customer updated successfully',
//             'data' => $customer,
//         ], 200);
//     }
//     public function destroy(Request $request, $id)
//     {
//         $code = $request->header('code');
//         $tableName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
//         $customerClass = new LG_CLCARD;
//         $customerClass->setTable($tableName);
//         $customer = $customerClass->where('LOGICALREF', $id)->first();
//         if (!$customer) {
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'Customer not found',
//             ], 404);
//         }
//         $customer->delete();
//         return response()->json([
//             'status' => 'success',
//             'message' => 'Customer deleted successfully',
//         ],200);
//     }
//     public function import(Request $request)
//     {
//         $import = new CustomerImport($request);
//         $type = Excel::import($import, $request->file('file')->store('files'));
//         return response()->json([
//             'status' => 'success',
//             'message' => 'Customer inserted successfully',
//             'data' => $type,
//         ]);
//     }
//     public function export(Request $request){
//         return Excel::download(new CustomerExport($request), 'Cutomer.xlsx');        
//     }
//     public function importView(Request $request){
//         return view('importFile');
//     }
}
