<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LG_CLCARD;
use App\Models\LG_SLSCLREL;
use App\Models\LG_SPECODES;
use App\Models\LG_PAYPLANS; 
use App\Models\LV_01_CLCARD;
use App\Models\LG_01_CLRNUMS;
use App\Models\LG_01_INVOICE;
use App\Models\LG_01_ORFICHE;
use App\Imports\CustomerImport;
use App\Exports\CustomerExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Schema;

class CustomerController extends Controller
{
    // retrieve customers list according on citycode 
    public function index(Request $request)
    {
        $code = $request->header('citycode');
        $paginate = $request->input('per_page',50);
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $ppName = str_replace('{code}', $code, (new LG_PAYPLANS)->getTable());
        $clcName = str_replace('{code}', $code, (new LV_01_CLCARD)->getTable());
        $clrName = str_replace('{code}', $code, (new LG_01_CLRNUMS)->getTable());
        $data = DB::table("{$custName}")
        ->join("{$clcName}","{$custName}.logicalref",'=',"{$clcName}.logicalref")
        ->join("{$ppName}","{$ppName}.logicalref",'=',"{$custName}.paymentref")
        ->join("{$clrName}","{$custName}.logicalref",'=',"{$clrName}.clcardref")
        ->select("{$custName}.logicalref as id","{$custName}.code","{$custName}.definition_ as name","{$custName}.addr1 as address","{$custName}.telnrs1 as phone","{$clcName}.debit",
        "{$clcName}.credit","{$ppName}.code as paymant_plan","{$clrName}.accrisklimit as limit");
        if ($request->hasHeader('country')) {
            $country = $request->header('country');
            $data->where("{$custName}.country", $country);
        }
        $customer = $data->paginate($paginate);
        return response()->json([
            'status' => 'success',
            'message' => 'Customers list',
            'data' => $customer->items(),
            'current_page' => $customer->currentPage(),
            'per_page' => $customer->perPage(),
            'last_page' => $customer->lastPage(),
            'total' => $customer->total(),
        ], 200);
    }
    
    // store new customer
    public function store(Request $request)
    {
        try {
        $salesman = $request->header('id');
        $code = $request->header('citycode');
        $sls = DB::table('lg_slsman')->where('logicalref', $salesman)->select('position_')->first();
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $limitName = str_replace('{code}', $code, (new LG_01_CLRNUMS)->getTable());
        $validateData = $request->validate([
            'market_name' => 'required',
            'customer_name' => 'required',
            'phone' => 'required|unique:'.$custName.',telnrs1|numeric|digits:11',
            'city' => 'required',
            'address' => 'required',
            'zone' => 'required',
            'longitude' => 'required',
            'latitude' => 'required',
        ]);
        $columnNames = Schema::getColumnListing($custName);
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
            'mapid' => $salesman,
            'country' => 'iraq',
            'cyphcode' => '1',
            'paymentref' => '4',
            'longitude' => $request->longitude,
            'latitute' => $request->latitude,
            'capiblock_createdby' => $salesman,
        ];
        $limitNames = Schema::getColumnListing($limitName);
        $limitValues = array_fill_keys($limitNames, 0);
        DB::beginTransaction();
            if($sls && $sls->position_ ==1)
            {
                $latestCode = DB::table($custName)
                ->where('code', 'like', '120.%')
                ->orderBy('logicalref', 'desc')
                ->value('code');
                $defaultValues['code'] = '120.' . str_pad(substr($latestCode, 4) + 1, 4, '0', STR_PAD_LEFT);
            }
            else if ($sls && $sls->position_ ==2)
            {
                $latestCode = DB::table($custName)
                ->where('code', 'like', '180.%')
                ->orderBy('logicalref', 'desc')
                ->value('code');
                $defaultValues['code'] = '180.' . str_pad(substr($latestCode, 4) + 1, 4, '0', STR_PAD_LEFT);
            }
            $logicalref = DB::table($custName)->insertGetId($defaultValues);
            $limitValues = [
                'clcardref' => $logicalref,
                'accrisklimit' => '0'
            ];
            DB::table($limitName)->insert($limitValues);
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Customers inserted successfully',
                'data' => $defaultValues,
            ],200);
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
        
    //retrieve pending customers
    public function newCustomer(Request $request)
    {
        $code = $request->header('citycode');
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $customer = DB::table("{$custName}")
        ->join('lg_slsman', "{$custName}.mapid", '=', 'lg_slsman.logicalref')
        ->select("{$custName}.logicalref as customer_id","{$custName}.definition2 as customer_name","{$custName}.definition_ as market_name",
        "{$custName}.telnrs1 as first_phone","{$custName}.code as customer_code","{$custName}.addr2 as zone","lg_slsman.definition_ as salesman_name")
        ->where("{$custName}.active",2)
        ->orderby("{$custName}.logicalref","desc")
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'New customer list',
            'data' => $customer,
        ]);
    }
    //retrieve pending customer details
    public function pendingCustomerDetails(Request $request)
    {
        $code = $request->header('citycode');
        $customer = $request->header('customer');
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $limitName = str_replace('{code}', $code, (new LG_01_CLRNUMS)->getTable());
        $planName = str_replace('{code}', $code, (new LG_PAYPLANS)->getTable());
        $data = DB::table("{$custName}")
        ->join('lg_slsman', "{$custName}.mapid", '=', 'lg_slsman.logicalref')
        ->join("{$limitName}","{$limitName}.clcardref","=","{$custName}.logicalref")
        ->join("{$planName}","{$planName}.logicalref","=","{$custName}.paymentref")
        ->select("{$custName}.definition2 as customer_name","{$custName}.definition_ as market_name",'lg_slsman.definition_ as salesman_name',"{$custName}.telnrs1 as first_phone",
        "{$custName}.telnrs2 as second_phone","{$custName}.code","{$custName}.city","{$custName}.addr2 as zone","{$custName}.addr1 as address",
        "{$custName}.ppgroupcode as customer_type","{$planName}.code as payment_plan",DB::raw("COALESCE({$limitName}.accrisklimit, 0) as limit"))
        ->where("{$custName}.logicalref",$customer)
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Pending customer details',
            'data' => $data,
        ]);
    }
    //update pending customer
    public function UpdatePendingCustomer(Request $request, $id)
    {
        $code = $request->header('citycode');
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $limitName = str_replace('{code}', $code, (new LG_01_CLRNUMS)->getTable());
        $customer = DB::table("{$custName}")->where('logicalref',$id)->first();      
        $limit = DB::table("{$limitName}")->where('clcardref',$id)->first();
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
        DB::table("{$custName}")->where('logicalref',$id)->update($custData);
        DB::table("{$limitName}")->where('clcardref',$id)->update($limitData);
        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Customer updated successfully',
            'data' => $custData,
        ],200); 
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
            "{$custName}.country","{$custName}.telnrs1 as customer_phone",DB::raw("COALESCE({$clcName}.debit, 0)"),"{$ppName}.definition_ as payment_plan",
            DB::raw("COALESCE({$clcName}.debit, 0)"),DB::raw("COALESCE({$clrName}.accrisklimit, 0) as limit"))
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


     // retrieve  customers list depending on salesman logicalref (Accounting)
     public function accountingSalesmanCustomers(Request $request)
     {
        $slsman = $request->header('id');
        $code = $request->header('citycode');
        $isactive = $request->header('isactive');
        $relName = str_replace('{code}', $code, (new LG_SLSCLREL)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $clcName = str_replace('{code}', $code, (new LV_01_CLCARD)->getTable());
        $ppName = str_replace('{code}', $code, (new LG_PAYPLANS)->getTable());
        $clrName = str_replace('{code}', $code, (new LG_01_CLRNUMS)->getTable());
        $speName = str_replace('{code}', $code, (new LG_SPECODES)->getTable());
        $data = DB::table("{$custName}")
        ->join("{$relName}","{$relName}.clientref","=","{$custName}.logicalref")
        ->join("{$clcName}","{$relName}.clientref","=","{$clcName}.logicalref")
        ->join("{$ppName}","{$ppName}.logicalref","=","{$custName}.paymentref")
        ->leftjoin("{$clrName}","{$clrName}.clcardref","=","{$custName}.logicalref")
        ->join("{$speName}","{$speName}.specode","=","{$custName}.specode2")
        ->select("{$custName}.logicalref as customer_id","{$custName}.code as customer_code", "{$custName}.definition_ as customer_name","{$custName}.addr1 as address",
        "{$custName}.city","{$custName}.country","{$custName}.telnrs1 as customer_phone","{$custName}.specode2 as price_group",DB::raw("COALESCE({$clcName}.debit, 0) as debit"),
        DB::raw("COALESCE({$clcName}.credit, 0) as credit"),"{$ppName}.definition_ as payment_plan",DB::raw("COALESCE({$clrName}.accrisklimit, 0) as limit"),
        "{$speName}.definition_ as price_group")
        ->where(["{$relName}.salesmanref" => $slsman,"{$custName}.active" => $isactive,"{$speName}.codetype" => 1,"{$speName}.specodetype" => 26])
        ->get();
            // ->select("{$custName}.logicalref as customer_id","{$custName}.code as customer_code", "{$custName}.definition_ as customer_name", "{$custName}.addr1 as address",
            // "{$custName}.city","{$custName}.country","{$custName}.telnrs1 as customer_phone","{$custName}.specode2 as price_group","{$ppName}.definition_ as payment_plan",
            // )
            // ->where(['lg_slsman.logicalref' => $slsman,'lg_slsman.active' => '0',"{$custName}.active" => $isactive])
            // ->get();
            // DB::raw("COALESCE({$clrName}.accrisklimit, 0) as limit"),DB::raw("COALESCE({$clcName}.debit, 0) as debit"),DB::raw("COALESCE({$clcName}.credit, 0) as credit")
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
        ->leftJoin("{$clrName}", "{$clrName}.clcardref", '=', "{$custName}.logicalref")
        ->leftJoin("{$ppName}", "{$ppName}.logicalref", '=', "{$custName}.paymentref")
        ->leftJoin("{$clcName}", "{$clcName}.logicalref", '=', "{$clrName}.clcardref")
        ->leftJoin("{$invName}", "{$clcName}.logicalref", '=', "{$invName}.clientref")
        ->select(
        DB::raw("COALESCE({$clrName}.accrisklimit, 0) as limit"),
        DB::raw("COALESCE({$ppName}.code, '0') as payment_plan"),
        DB::raw("COALESCE({$clcName}.debit, 0) as debit"),
        DB::raw("COALESCE({$clcName}.credit, 0) as credit"),
        DB::raw("COALESCE(CONVERT(varchar(10), MAX({$invName}.date_), 120), 'No invoice found') as last_invoice_date"))
        ->where(["{$custName}.code" => $customer])
        ->groupBy("{$clrName}.accrisklimit", "{$ppName}.code", "{$clcName}.debit", "{$clcName}.credit")
        ->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Customer details',
                'data' => $results ,
            ]);
    }
}
