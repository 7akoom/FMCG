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

class OrderController extends Controller
{
    // retrieve orders list
    public function index(Request $request)
    {
        $code = $request->header('citycode');
        $perpage = request()->input('per_page', 15);
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $orderName = str_replace('{code}', $code, (new LG_01_ORFICHE)->getTable());
        $order = DB::table('lg_slsman')
        ->join("{$orderName}","{$orderName}.salesmanref","=",'lg_slsman.logicalref')
        ->join("{$custName}","{$custName}.logicalref","=","{$orderName}.clientref")
        ->select("{$orderName}.capiblock_creadeddate as order_date","{$orderName}.ficheno as order_number",
        "{$orderName}.docode as invoice_number","{$custName}.definition_ as customer_name","{$custName}.addr1 as customer_address",
        'lg_slsman.code as salesman_code',"{$orderName}.nettotal as order_total_amount","{$orderName}.status as order_status")
        ->where('lg_slsman.firmnr',$code)
        ->orderby("{$orderName}.capiblock_creadeddate","desc")
        ->paginate($perpage);
        return response()->json([
            'status' => 'success',
            'message' => 'Order list',
            'data' => $order->items(),
            'current_page' => $order->currentPage(),
            'per_page' => $order->perPage(),
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
        ->select("{$ordName}.date_ as date","{$ordName}.ficheno as number","{$ordName}.grosstotal as order_amount","{$ordName}.totaldiscounts as order_discount",
        "{$ordName}.nettotal as order_total","{$ordName}.genexp1 as approved_by",
        'lg_slsman.definition_ as salesman_name',"{$custName}.code as customer_code","{$custName}.definition_ as customer_name",
        "{$custName}.addr1 as customer_address","{$custName}.telnrs1 as customer_phone","{$clcName}.debit as customer_debit",
        "{$clcName}.credit as customer_credit","{$ppName}.code as customer_payment_plan","{$ordName}.genexp2 as payment_type")
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
        $status = $request->header('status');
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $orderName = str_replace('{code}', $code, (new LG_01_ORFICHE)->getTable());
        $order = DB::table('lg_slsman')
        ->join("{$orderName}","{$orderName}.salesmanref","=",'lg_slsman.logicalref')
        ->join("{$custName}","{$custName}.logicalref","=","{$orderName}.clientref")
        ->select("{$orderName}.capiblock_creadeddate as order_date","{$orderName}.ficheno as order_number",
        "{$orderName}.docode as invoice_number","{$custName}.definition_ as customer_name","{$custName}.addr1 as customer_address",
        'lg_slsman.code as salesman_code',"{$orderName}.nettotal as order_total_amount","{$orderName}.status as order_status")
        ->where(['lg_slsman.firmnr'=> $code, "{$orderName}.status" => $status])
        ->orderby("{$orderName}.capiblock_creadeddate","desc")
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'orders list',
            'data' => $order,
        ], 200);
    }
    // retrieve orders based on date
   public function OrderDateFilter(Request $request)
{
    $code = $request->header('citycode');
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
    ->where('lg_slsman.firmnr', $code)
    ->whereBetween(DB::raw("CONVERT(date, {$orderName}.CAPIBLOCK_CREADEDDATE)"), [$startDate, $endDate])
    ->orderBy(DB::raw("CONVERT(date, {$orderName}.CAPIBLOCK_CREADEDDATE)"), "desc")
    ->get();

    return response()->json([
        'status' => 'success',
        'message' => 'orders list',
        'data' => $order,
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
   
}
