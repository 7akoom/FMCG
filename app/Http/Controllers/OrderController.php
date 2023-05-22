<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LG_CLCARD;
use App\Models\LG_01_ORFICHE;
use App\Models\LV_01_CLCARD;
use App\Models\LG_01_ORFLINE;
use App\Models\LG_PAYPLANS;
use App\Models\LG_SLSCLREL;
use App\Models\LG_01_CLRNUMS;
use App\Models\LG_ITEMS;
use App\Models\LG_ITMUNITA;
use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;



class OrderController extends Controller
{
    // retrieve orders list
    public function index(Request $request)
    {
        $code = $request->header('citycode');
        $type = $request->header('type');
        $perpage = request()->input('per_page', 50);
        $page = request()->input('page', 1);
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $orderName = str_replace('{code}', $code, (new LG_01_ORFICHE)->getTable());
        $order = DB::table('lg_slsman')
        ->join("{$orderName}","{$orderName}.salesmanref","=","lg_slsman.logicalref")
        ->join("{$custName}","{$custName}.logicalref","=","{$orderName}.clientref")
        ->select("{$orderName}.capiblock_creadeddate as order_date","{$orderName}.ficheno as order_number",
        "{$orderName}.docode as invoice_number","{$custName}.definition_ as customer_name","{$custName}.addr1 as customer_address",
        'lg_slsman.code as salesman_code',"{$orderName}.nettotal as order_total_amount","{$orderName}.status as order_status")
        ->where(['lg_slsman.firmnr' => $code, "{$orderName}.trcode" => $type])
        ->orderby("{$orderName}.capiblock_creadeddate","desc")
        ->paginate($perpage);
        return response()->json([
            'status' => 'success',
            'message' => 'Order list',
            'data' => $order->items(),
            'current_page' => $order->currentPage(),
            'per_page' => $order->perPage(),
            'next_page' => $order->nextPageUrl($page),
            'previous_page' => $order->previousPageUrl($page),
            'last_page' => $order->lastPage(),
            'total' => $order->total(),
        ], 200);
    }
    //retrieve order details based on order number
    public function orderdetails(Request $request)
    {   
        $code = $request->header('citycode');
        $order = $request->header('order');
        $orlName = str_replace('{code}', $code, (new LG_01_ORFLINE)->getTable());
        $itmName = str_replace('{code}', $code, (new LG_ITEMS)->getTable());
        $ordName = str_replace('{code}', $code, (new LG_01_ORFICHE)->getTable());
        $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $clcName = str_replace('{code}', $code, (new LV_01_CLCARD)->getTable());
        $ppName = str_replace('{code}', $code, (new LG_PAYPLANS)->getTable());
        $info = DB::table("{$orlName}")
        ->join("{$itmName}","{$orlName}.stockref","=","{$itmName}.logicalref")
        ->join('lg_slsman',"{$orlName}.salesmanref","=",'lg_slsman.logicalref')
        ->join("{$weightName}","{$weightName}.itemref","=","{$itmName}.logicalref")
        ->join("{$ordName}","{$orlName}.ordficheref","=","{$ordName}.logicalref")
        ->join("{$custName}","{$orlName}.clientref","=","{$custName}.logicalref")
        ->join("{$clcName}","{$clcName}.logicalref","=","{$custName}.logicalref")
        ->join("{$ppName}","{$ppName}.logicalref",'=',"{$custName}.paymentref")
        ->select("{$ordName}.date_ as date","{$ordName}.ficheno as number","{$ordName}.nettotal as order_total",
        "{$custName}.code as customer_code","{$custName}.definition_ as customer_name","{$clcName}.debit as customer_debit",
        "{$clcName}.credit as customer_credit","{$ppName}.code as customer_payment_plan")
        ->where(["{$ordName}.ficheno" => $order])
        ->distinct()
        ->first();
        $item = DB::table("{$orlName}")
        ->join("{$itmName}","{$orlName}.stockref","=","{$itmName}.logicalref")
        ->join('lg_slsman',"{$orlName}.salesmanref","=",'lg_slsman.logicalref')
        ->join("{$weightName}","{$weightName}.itemref","=","{$itmName}.logicalref")
        ->join("{$ordName}","{$orlName}.ordficheref","=","{$ordName}.logicalref")
        ->join("{$custName}","{$orlName}.clientref","=","{$custName}.logicalref")
        ->select("{$orlName}.lineno_ as line","{$ordName}.capiblock_creadeddate as date","{$itmName}.code as code","{$itmName}.name as name",
        "{$orlName}.amount as quantity","{$orlName}.price as price","{$orlName}.total as total",
        "{$orlName}.distdisc as discount","{$weightName}.grossweight as weight")
        ->where(["{$ordName}.ficheno" => $order,"{$weightName}.linenr" => 1])
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Order details',
            'order_info' => $info = (array) $info,
            'data' => $item,
        ]);
      }
      // retrieve orders based on status
    public function ordersStatusFilter(Request $request)
    {
        $code = $request->header('citycode');
        $perpage = request()->input('per_page', 50);
        $page = request()->input('page', 1);
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $orderName = str_replace('{code}', $code, (new LG_01_ORFICHE)->getTable());
        $order = DB::table('lg_slsman')
        ->join("{$orderName}","{$orderName}.salesmanref","=",'lg_slsman.logicalref')
        ->join("{$custName}","{$custName}.logicalref","=","{$orderName}.clientref")
        ->select("{$orderName}.capiblock_creadeddate as order_date","{$orderName}.ficheno as order_number",
        "{$orderName}.docode as invoice_number","{$custName}.definition_ as customer_name","{$custName}.addr1 as customer_address",
        'lg_slsman.code as salesman_code',"{$orderName}.nettotal as order_total_amount","{$orderName}.status as order_status")
        ->orderby("{$orderName}.capiblock_creadeddate","desc");
        if ($request->hasHeader('status')) {
            $status = $request->header('status');
            if($status == -1)
            {
                $order->get();
            }
            else
            {
                $order->where(["{$orderName}.status" => $status]);
            }
        }
        $result = $order->paginate($perpage);
        return response()->json([
            'status' => 'success',
            'message' => 'Order list',
            'data' => $result->items(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'next_page' => $result->nextPageUrl($page),
            'previous_page' => $result->previousPageUrl($page),
            'last_page' => $result->lastPage(),
            'total' => $result->total(),
        ], 200);
    }
    // retrieve orders based on date
   public function OrderDateFilter(Request $request)
{
    $code = $request->header('citycode');
    $perpage = request()->input('per_page', 50);
    $page = request()->input('page', 1);
    $startDate = $request->input('start_date');
    $endDate = $request->input('end_date');
    $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
    $orderName = str_replace('{code}', $code, (new LG_01_ORFICHE)->getTable());
    $order = DB::table('lg_slsman')
    ->join("{$orderName}","{$orderName}.salesmanref","=",'lg_slsman.logicalref')
    ->join("{$custName}","{$custName}.logicalref","=","{$orderName}.clientref")
    ->select(DB::raw("CONVERT(date, {$orderName}.capiblock_creadeddate) as order_date"), "{$orderName}.ficheno as order_number",
             "{$orderName}.docode as invoice_number", "{$custName}.definition_ as customer_name", "{$custName}.addr1 as customer_address",
             'lg_slsman.code as salesman_code', "{$orderName}.nettotal as order_total_amount", "{$orderName}.status as order_status")
    ->where('lg_slsman.firmnr', $code);
    if ($startDate !== '-1' && $endDate !== '-1') {
        $order->whereBetween(DB::raw("CONVERT(date, {$orderName}.CAPIBLOCK_CREADEDDATE)"), [$startDate, $endDate]);
    }
    $order->orderBy(DB::raw("CONVERT(date, {$orderName}.CAPIBLOCK_CREADEDDATE)"), "desc");
    $result = $order->paginate($perpage);
    return response()->json([
        'status' => 'success',
        'message' => 'Order list',
        'data' => $result->items(),
        'current_page' => $result->currentPage(),
        'per_page' => $result->perPage(),
        'next_page' => $result->nextPageUrl($page),
        'previous_page' => $result->previousPageUrl($page),
        'last_page' => $result->lastPage(),
        'total' => $result->total(),
    ], 200);
}
//retrieve previous orders that related to customer
public function salesmanlacurrentmonthorder(Request $request)
{
    $code = $request->header('citycode');
    $salesman = $request->header('id');
    $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
    $orderName = str_replace('{code}', $code, (new LG_01_ORFICHE)->getTable());
    $order = DB::table("{$orderName}")
    ->join("{$custName}", "{$custName}.logicalref", "=", "{$orderName}.clientref")
    ->select(
        "{$custName}.definition_ as customer_name",
        "{$orderName}.capiblock_creadeddate as date",
        "{$orderName}.ficheno as number",
        "{$orderName}.grosstotal as amount",
        "{$orderName}.totaldiscounts as discount",
        "{$orderName}.nettotal as total",
        "{$orderName}.status"
    )
    ->where(["{$orderName}.salesmanref" => $salesman,"{$orderName}.status" => 1])
    ->orwhere("{$orderName}.status",2)
    ->whereYear("{$orderName}.capiblock_creadeddate", "=", now()->year)
    ->whereMonth("{$orderName}.capiblock_creadeddate", "=", now()->month)
    ->orderby("{$orderName}.capiblock_creadeddate", "desc")
    ->get();
    return response()->json([
        'status' => 'success',
        'message' => 'orders list',
        'data' => $order,
    ], 200);
}



//retrieve previous order details based on order number
public function previousorderdetails(Request $request)
    {   
        $code = $request->header('citycode');
        $order = $request->header('order');
        $orlName = str_replace('{code}', $code, (new LG_01_ORFLINE)->getTable());
        $itmName = str_replace('{code}', $code, (new LG_ITEMS)->getTable());
        $ordName = str_replace('{code}', $code, (new LG_01_ORFICHE)->getTable());
        $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
        $info = DB::table("{$orlName}")
        ->join("{$itmName}","{$orlName}.stockref","=","{$itmName}.logicalref")
        ->join("{$weightName}","{$weightName}.itemref","=","{$itmName}.logicalref")
        ->join("{$ordName}","{$orlName}.ordficheref","=","{$ordName}.logicalref")
        ->select("{$ordName}.ficheno as number","{$ordName}.grosstotal as order_amount","{$ordName}.totaldiscounts as order_discount",
        "{$ordName}.nettotal as order_total","{$ordName}.genexp1 as approved_by","{$ordName}.genexp2 as payment_type")
        ->where(["{$ordName}.ficheno" => $order])
        ->distinct()
        ->first();
        $item = DB::table("{$orlName}")
        ->join("{$itmName}","{$orlName}.stockref","=","{$itmName}.logicalref")
        ->join("{$weightName}","{$weightName}.itemref","=","{$itmName}.logicalref")
        ->join("{$ordName}","{$orlName}.ordficheref","=","{$ordName}.logicalref")
        ->select("{$orlName}.lineno_ as line","{$ordName}.capiblock_creadeddate as date","{$itmName}.code as code","{$itmName}.name as name",
        "{$orlName}.amount as quantity","{$orlName}.price as price","{$orlName}.total as total",
        "{$orlName}.distdisc as discount","{$weightName}.grossweight as weight")
        ->where(["{$ordName}.ficheno" => $order,"{$weightName}.linenr" => 1])
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Order details',
            'order_info' => $info = (array) $info,
            'data' => $item,
        ]);
      }
       //retrieve order details based on order number
    public function customerCurrentOrder(Request $request)
    {   
        $code = $request->header('citycode');
        $customer = $request->header('customer');
        $orlName = str_replace('{code}', $code, (new LG_01_ORFLINE)->getTable());
        $itmName = str_replace('{code}', $code, (new LG_ITEMS)->getTable());
        $ordName = str_replace('{code}', $code, (new LG_01_ORFICHE)->getTable());
        $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $clcName = str_replace('{code}', $code, (new LV_01_CLCARD)->getTable());
        $ppName = str_replace('{code}', $code, (new LG_PAYPLANS)->getTable());
        $info = DB::table("{$orlName}")
        ->join("{$itmName}","{$orlName}.stockref","=","{$itmName}.logicalref")
        ->join('lg_slsman',"{$orlName}.salesmanref","=",'lg_slsman.logicalref')
        ->join("{$weightName}","{$weightName}.itemref","=","{$itmName}.logicalref")
        ->join("{$ordName}","{$orlName}.ordficheref","=","{$ordName}.logicalref")
        ->join("{$custName}","{$orlName}.clientref","=","{$custName}.logicalref")
        ->join("{$clcName}","{$clcName}.logicalref","=","{$custName}.logicalref")
        ->join("{$ppName}","{$ppName}.logicalref",'=',"{$custName}.paymentref")
        ->select("{$ordName}.date_ as date","{$ordName}.ficheno as number","{$ordName}.grosstotal as order_amount","{$ordName}.totaldiscounts as order_discount",
        "{$ordName}.nettotal as order_total","{$ordName}.genexp1 as approved_by",
        'lg_slsman.definition_ as salesman_name',"{$custName}.code as customer_code","{$custName}.definition_ as customer_name",
        "{$custName}.addr1 as customer_address","{$custName}.telnrs1 as customer_phone","{$clcName}.debit as customer_debit",
        "{$clcName}.credit as customer_credit","{$ppName}.code as customer_payment_plan","{$ordName}.genexp2 as payment_type")
        ->where(["{$custName}.code" => $customer])
        ->distinct()
        ->first();
        $item = DB::table("{$orlName}")
        ->join("{$itmName}","{$orlName}.stockref","=","{$itmName}.logicalref")
        ->join('lg_slsman',"{$orlName}.salesmanref","=",'lg_slsman.logicalref')
        ->join("{$weightName}","{$weightName}.itemref","=","{$itmName}.logicalref")
        ->join("{$ordName}","{$orlName}.ordficheref","=","{$ordName}.logicalref")
        ->join("{$custName}","{$orlName}.clientref","=","{$custName}.logicalref")
        ->select("{$orlName}.lineno_ as line","{$ordName}.capiblock_creadeddate as date","{$itmName}.code as code","{$itmName}.name as name",
        "{$orlName}.amount as quantity","{$orlName}.price as price","{$orlName}.total as total",
        "{$orlName}.distdisc as discount","{$weightName}.grossweight as weight")
        ->where(["{$weightName}.linenr" => 1, "{$custName}.code" => $customer,"{$ordName}.status" => 1])
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Order details',
            'order_info' => $info = (array) $info,
            'data' => $item,
        ]);
      }
    public function store(Request $request)
    {
        try 
        {
            $code = $request->header('citycode');
            $slsman = $request->header('id');
            $customer = $request->header('customer');
            $ordName = str_replace('{code}', $code, (new LG_01_ORFICHE)->getTable());
            $ordLine = str_replace('{code}', $code, (new LG_01_ORFLINE)->getTable());
            $ordNameColumns = Schema::getColumnListing($ordLine);
            $OrdLineValues = [];
            $orderNumber = DB::table($ordName)
                ->orderBy('logicalref', 'desc')
                ->value('ficheno');
            $fixedLengthOrderNumber = str_pad($orderNumber, 8, '0', STR_PAD_LEFT);
            $nextOrderNumber = str_pad((int)$orderNumber + 1, strlen($fixedLengthOrderNumber), '0', STR_PAD_LEFT);
            $OrderValues = [
                'trcode' => '1',
                'ficheno' => $nextOrderNumber,
                'date_' => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
                'time_' => sprintf("%09d", mt_rand(1, 999999999)),
                'clientref' => $customer,
                'TOTALDISCOUNTS' => $request->total_discount,
                'totaldiscounted' => $request->after_discount,
                'grosstotal' => $request->total_amount,
                'nettotal' => $request->net_total,
                'paydefref' => $request->payment_plan,
                'status' => 1,
                'capiblock_createdby' => $slsman,
                'capiblock_creadeddate' => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
                'capiblock_createdhour' => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
                'capiblock_createdmin' => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
                'capiblock_createdsec' => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
                'salesmanref' => $slsman,
                'recstatus' => 1,
                'trnet' => $request->net_total, 
                'guid' => Str::uuid()->toString(),
                'rechash' => Str::random(64),
                'docode' => $request->note ?? '',
                'genexp1' => $request->note ?? '',
                "ACCEPTEINVPUBLIC" => 0 ,            
                "ACCOUNTREF" => 0 ,
                "ACTRENTING" => 0 ,
                "ADDDISCOUNTS" => 0 ,
                "ADDDISCOUNTSVAT" => 0 ,
                "ADDEXPENSES" => 0 ,
                "ADDEXPENSESVAT" => 0 ,
                "ADVANCEPAYM" => 0 ,
                "AFFECTCOLLATRL" => 0 ,
                "AFFECTRISK" => 0 ,
                "ALTNR" => 0 ,
                "APPROVE" => 0 ,
                "APPROVEDATE" => '' ,
                "ATAXEXCEPTCODE" => 0 ,
                "ATAXEXCEPTREASON" => 0 ,
                "BRANCH" => 0 ,
                "CAMPAIGNCODE" => '' ,
                "CANCELLED" => 0 ,
                "CANTCREDEDUCT" => 0 ,
                "CAPIBLOCK_MODIFIEDBY" => 0,
                "CAPIBLOCK_MODIFIEDDATE" => 0,
                "CAPIBLOCK_MODIFIEDHOUR" => 0,
                "CAPIBLOCK_MODIFIEDMIN" => 0,
                "CAPIBLOCK_MODIFIEDSEC" => 0,
                "CENTERREF" => 0,
                "CHECKAMOUNT" => 0,
                "CHECKPRICE" => 0,
                "CHECKTOTAL" => 0,
                "CUSTORDNO" => '',
                "CYPHCODE" => '',
                "DEDUCTIONPART1" => 2,
                "DEDUCTIONPART2" => 3,
                "DEFAULTFICHE" => 0,
                "DELIVERYCODE" => '',
                "DEPARTMENT" => 0,
                "DEVIR" => 0,
                "DLVCLIENT" => 0,
                "DOCTRACKINGNR" => '',
                "EINVOICE" => 0,
                "EINVOICETYP" => 0,
                "EXTENREF" => 0,
                "FACTORYNR" => 0,
                "FCSTATUSREF" => 0,
                "GENEXCTYP" => 0,
                "GENEXP2" => '',
                "GENEXP3" => '',
                "GENEXP4" => '',
                "GENEXP5" => '',
                "GENEXP6" => '',
                "GLOBALID" => '',
                "INSTEADOFDESP" => 0,
                "LASTREVISION" => 0,
                "LEASINGREF" => 0,
                "LINEEXCTYP" => 0,
                "OFFALTREF" => 0,
                "OFFERREF" => 0,
                "ONLYONEPAYLINE" => 0,
                "OPSTAT" => 0,
                "ORGDATE" => '',
                "ORGLOGICREF" => 0,
                "ORGLOGOID" => '',
                "PAYMENTTYPE" => 0,
                "POFFERBEGDT" => '',
                "POFFERENDDT" => '',
                "PRINTCNT" => 0,
                "PRINTDATE" => '',
                "PROJECTREF" => 0,
                "PUBLICBNACCREF" => 0,
                "RECVREF" => 0,
                "REPORTNET" => 0,
                "REPORTRATE" => 0,
                "REVISNR" => '',
                "SENDCNT" => 0,
                "SHIPINFOREF" => 0,
                "SHPAGNCOD" => '',
                "SHPTYPCOD" => '',
                "SITEID" => 0,
                "SLSACTREF" => 0,
                "SLSCUSTREF" => 0,
                "SLSOPPRREF" => 0,
                "SOURCECOSTGRP" => 0,
                "SOURCEINDEX" => 0,
                "SPECODE" => '',
                "TAXFREECHX" => 0,
                "TEXTINC" => 0,
                "TOTALADDTAX" => 0,
                "TOTALEXADDTAX" => 0,
                "TOTALEXPENSES" => 0,
                "TOTALEXPENSESVAT" => 0,
                "TOTALPROMOTIONS" => 0,
                "TOTALVAT" => 0,
                "TRADINGGRP" => '',
                "TRANSFERWITHPAY" => 0,
                "TRCURR" => 0,
                "TRRATE" => 0,
                "TYP" => 0,
                "WITHPAYTRANS" => 0,
                "UPDCURR" => 0,
                "UPDTRCURR" => 0,
                "VATEXCEPTCODE" => '',
                "VATEXCEPTREASON" => '',
                "WFLOWCRDREF" => 0,
                "WFSTATUS" => 0,

            ];
            DB::beginTransaction();
            $logicalref = DB::table($ordName)->insertGetId($OrderValues);
            $items =[];
            foreach ($request->input('items') as $item) {
                $items[] = [
                    'ordficheref' => $logicalref,
                    'stockref' => $item['item_id'],
                    'clientref' => $customer,
                    'lineno_' => $item['line_number'],
                    'date_' => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
                    'time_' => sprintf("%09d", mt_rand(1, 999999999)),
                    'amount' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total_amount'],
                    'uomref' => $item['unit'],
                    'distcost' => $item['discount'],
                    'distdisc' => $item['discount'],
                    'duedate' => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
                    'paydefref' => $request->payment_plan,
                    'linenet' => $item['after_discount'],
                    'salesmanref' => $slsman,
                    'status' => 1,
                    'recstatus' =>1,
                    'guid' => Str::uuid()->toString(),
                     "ACCOUNTREF"  => 0,
                     "ADDTAXACCREF" => 0,
                     "ADDTAXAMNTISUPD" => 0,
                     "ADDTAXAMOUNT" => 0,
                     "ADDTAXCENTERREF" => 0,
                     "ADDTAXCONVFACT" => 0,
                     "ADDTAXDISCAMOUNT" => 0,
                     "ADDTAXRATE" => 0,
                     "ADDTAXVATMATRAH" => 0,
                     "AFFECTCOLLATRL" => 0,
                     "AFFECTRISK" => 0,
                     "ALTPROMFLAG" => 0,
                     "ATAXEXCEPTCODE" => '',
                     "ATAXEXCEPTREASON" => '',
                     "BILLEDITEM" => 0,
                     "BOMREF" => 0,
                     "BOMREVREF" => 0,
                     "BOMTYPE" => 0,
                     "BRANCH" => 0,
                     "CALCTYPE" => 0,
                     "CAMPAIGNREFS1" => 0,
                     "CAMPAIGNREFS2" => 0,
                     "CAMPAIGNREFS3" => 0,
                     "CAMPAIGNREFS4" => 0,
                     "CAMPAIGNREFS5" => 0,
                     "CAMPPAYDEFREF" => 0,
                     "CAMPPOINT" => 0,
                     "CAMPPOINTS1" => 0,
                     "CAMPPOINTS2" => 0,
                     "CAMPPOINTS3" => 0,
                     "CAMPPOINTS4" => 0,
                     "CANCELLED" => 0,
                     "CANDEDUCT" => 0,
                     "CENTERREF" => 0,
                     "CLOSED" => 0,
                     "CMPGLINEREF" => 0,
                     "CMPGLINEREFS1" => 0,
                     "CMPGLINEREFS2" => 0,
                     "CMPGLINEREFS3" => 0,
                     "CMPGLINEREFS4" => 0,
                     "CONDITIONREF" => 0,
                     "CPACODE" => 0,
                     "CPSTFLAG" => 0,
                     "DEDUCTCODE" => 0,
                     "DEDUCTIONPART1" => 0,
                     "DEDUCTIONPART2" => 0,
                     "DELVRYCODE" => '',
                     "DEMFICHEREF" => 0,
                     "DEMPEGGEDAMNT" => 0,
                     "DEMTRANSREF" => 0,
                     "DEPARTMENT" => 0,
                     "DETLINE" => 0,
                     "DEVIR" => 0,
                     "DISCPER" => 0,
                     "DISTDISCVAT" => 0,
                     "DISTEXP" => 0,
                     "DISTEXPVAT" => 0,
                     "DISTPROM" => 0,
                     "DISTRESERVED" => 0,
                     "DORESERVE" => 0,
                     "DREF" => 0,
                     "EUVATSTATUS" => 0,
                     "EXADDTAXAMNT" => 0,
                     "EXADDTAXCONVF" => 0,
                     "EXADDTAXRATE" => 0,
                     "EXIMAMOUNT" => 0,
                     "EXTENREF" => 0,
                     "FACTORYNR" => 0,
                     "FAREGREF" => 0,
                     "FCTYP" => 0,
                     "GLOBALID" => 0,
                     "GLOBTRANS" => 0,
                     "GROSSUINFO1" => 0,
                     "GROSSUINFO2" => 0,
                     "GTIPCODE" => 0,
                     "INUSE" => 0,
                     "ITEMASGREF" => 0,
                     "LINEEXP" => '',
                     "LINETYPE" => 0,
                     "NETDISCAMNT" => 0,
                     "NETDISCFLAG" => 0,
                     "NETDISCPERC" => 0,
                     "OFFERREF" => 0,
                     "OFFTRANSREF" => 0,
                     "ONVEHICLE" => 0,
                     "OPERATIONREF" => 0,
                     "ORDEREDAMOUNT" => 0,
                     "ORDERPARAM" => 0,
                     "ORGAMOUNT" => 0,
                     "ORGDUEDATE" => 0,
                     "ORGLOGICREF" => 0,
                     "ORGLOGOID" => 0,
                     "ORGPRICE" => 0,
                     "PARENTLNREF" => 0,
                     "POINTCAMPREF" => 0,
                     "POINTCAMPREFS1" => 0,
                     "POINTCAMPREFS2" => 0,
                     "POINTCAMPREFS3" => 0,
                     "POINTCAMPREFS4" => 0,
                     "PRACCREF" => 0,
                     "PRCENTERREF" => 0,
                     "PRCLISTREF" => 0,
                     "PRCURR" => 30,
                     "PREVLINENO" => 0,
                     "PREVLINEREF" => 0,
                     "PRIORITY" => 0,
                     "PROJECTREF" => 0,
                     "PROMCLASITEMREF" => 0,
                     "PROMREF" => 0,
                     "PRPRICE" => 0,
                     "PRRATE" => 0,
                     "PRVATACCREF" => 0,
                     "PRVATCENREF" => 0,
                     "PUBLICCOUNTRYREF" => 0,
                     "PURCHOFFNR" => 0,
                     "REASONFORNOTSHP" => 0,
                     "REFLVATACCREF" => 0,
                     "REFLVATOTHACCREF" => 0,
                     "REPORTRATE" => 0,
                     "RESERVEAMOUNT" => 0,
                     "RESERVEDATE" => 0,
                     "ROUTINGREF" => 0,
                     "RPRICE" => 0,
                     "SHIPPEDAMNTSUGG" => 0,
                     "SHIPPEDAMOUNT" => 0,
                     "SITEID" => 0,
                     "SOURCECOSTGRP" => 0,
                     "SOURCEINDEX" => 0,
                     "SPECODE" => '',
                     "SPECODE2" => 0,
                     "TEXTINC" => 0,
                     "TRCODE" => 1,
                     "TRCURR" => 0,
                     "TRGFLAG" => 0,
                     "TRRATE" => 0,
                     "UINFO1" => 1,
                     "UINFO2" => 1,
                     "UINFO3" => 0,
                     "UINFO4" => 0,
                     "UINFO5" => 0,
                     "UINFO6" => 0,
                     "UINFO7" => 0,
                     "UINFO8" => 0,
                     "UNDERDEDUCTLIMIT" => 0,
                     "USREF" => $request->weight,
                     "VARIANTREF" => 0,
                     "VAT" => 0,
                     "VATACCREF" => 0,
                     "VATAMNT" => 0,
                     "VATCENTERREF" => 0,
                     "VATEXCEPTCODE" => 0,
                     "VATEXCEPTREASON" => 0,
                     "VATINC" => 0,
                     "VATMATRAH" => 0,
                     "WFSTATUS" => 0,
                     "WITHPAYTRANS" => 0,
                     "WSREF" => 0
                ];
            }
            DB::table("{$ordLine}")->insert($items);
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Order sent successfully',
            ],200);
        }
        catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e,
            ], 422);
        }
    }
}

