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
use App\Models\LG_01_STLINE;
use App\Models\LG_ITEMS;
use App\Models\LG_ITMUNITA;

class InvoiceController extends Controller
{
    // retrieve current month invoices that realted to salesman 
    public function salesmanmonthlyinvoices(Request $request)
    {
        $code = $request->header('citycode');
        $slsman = $request->header('id');
        // $type = $request->header('type');
        $invoiceName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $invoices = DB::table('lg_slsman')
            ->join($invoiceName, "{$invoiceName}.salesmanref", "=", 'lg_slsman.logicalref')
            ->join($custName, "{$invoiceName}.clientref", "=", "{$custName}.logicalref")
            ->select("{$custName}.logicalref as customer_id","{$custName}.code as customer_code",
            "{$invoiceName}.logicalref as invoice_id","{$invoiceName}.capiblock_creadeddate as invoice_date_",
            "{$custName}.definition_ as customer_name",
            "{$invoiceName}.ficheno as invoice_number",
            "{$invoiceName}.nettotal as total_amount","{$invoiceName}.docode as from_p_invoice")
            ->where(["{$invoiceName}.salesmanref" => $slsman,])
            ->whereMonth("{$invoiceName}.capiblock_creadeddate", '=', now()->month)
            ->orderby("{$invoiceName}.capiblock_creadeddate","desc")
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Current month invoices',
            'data' => $invoices,
        ]);
    }
    //retrieve all salesman invoices
    public function salesmaninvoices(Request $request)
    {
        $code = $request->header('citycode');
        $slsman = $request->header('id');
        $type = $request->header('type');
        $invoiceName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $invoices = DB::table('lg_slsman')
            ->join($invoiceName, "{$invoiceName}.salesmanref", "=", 'lg_slsman.logicalref')
            ->join($custName, "{$invoiceName}.clientref", "=", "{$custName}.logicalref")
            ->select("{$custName}.logicalref as customer_id","{$custName}.code as customer_code",
            "{$invoiceName}.logicalref as invoice_id","{$invoiceName}.capiblock_creadeddate as invoice_date_",
            "{$custName}.definition_ as customer_name",
            "{$invoiceName}.ficheno as invoice_number", "{$invoiceName}.capiblock_creadeddate as invoice_date",
            "{$invoiceName}.nettotal as total_amount","{$invoiceName}.docode as from_p_invoice")
            ->where(["{$invoiceName}.salesmanref" => $slsman, "{$invoiceName}.trcode" => $type])
            ->orderby("{$invoiceName}.capiblock_creadeddate","desc")
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Current month invoices',
            'data' => $invoices,
        ]);
    }
     // retrieve invoice details according on salesman logicalref and invoice logicalref
     public function salesmaninvoicedetails(Request $request)
     {
       $slsman = $request->header('id');
       $code = $request->header('citycode');
       $invoice = $request->header('invoice');
       $stlName = str_replace('{code}', $code, (new LG_01_STLINE)->getTable());
       $itmName = str_replace('{code}', $code, (new LG_ITEMS)->getTable());
       $invName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
       $weightName = str_replace('{code}', $code, (new LG_ITMUNITA)->getTable());
       $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
       $clcName = str_replace('{code}', $code, (new LV_01_CLCARD)->getTable());
       $ppName = str_replace('{code}', $code, (new LG_PAYPLANS)->getTable());
       $info = DB::table("{$stlName}")
       ->join("{$itmName}","{$stlName}.stockref","=","{$itmName}.logicalref")
       ->join('lg_slsman',"{$stlName}.salesmanref","=",'lg_slsman.logicalref')
       ->join("{$weightName}","{$weightName}.itemref","=","{$itmName}.logicalref")
       ->join("{$invName}","{$stlName}.invoiceref","=","{$invName}.logicalref")
       ->join("{$custName}","{$stlName}.clientref","=","{$custName}.logicalref")
       ->join("{$clcName}","{$clcName}.logicalref","=","{$custName}.logicalref")
       ->join("{$ppName}","{$ppName}.logicalref",'=',"{$custName}.paymentref")
       ->select("{$invName}.capiblock_creadeddate as date","{$invName}.ficheno as number","{$invName}.genexp1 as approved_by",
       "{$invName}.grosstotal as invoice_amount","{$invName}.totaldiscounts as invoice_discount","{$invName}.nettotal as invoice_total",
       'lg_slsman.definition_ as salesman_name',"{$custName}.code as customer_code","{$custName}.definition_ as customer_name",
       "{$custName}.addr1 as customer_address","{$custName}.telnrs1 as customer_phone","{$clcName}.debit as customer_debit",
       "{$clcName}.credit as customer_credit","{$ppName}.code as customer_payment_plan","{$invName}.genexp2 as payment_type")
       ->where(["{$invName}.ficheno" => $invoice, 'lg_slsman.logicalref' => $slsman,"{$stlName}.iocode" => 4,])
       ->distinct()
       ->first();
       $item = DB::table("{$stlName}")
       ->join("{$itmName}","{$stlName}.stockref","=","{$itmName}.logicalref")
       ->join('lg_slsman',"{$stlName}.salesmanref","=",'lg_slsman.logicalref')
       ->join("{$weightName}","{$weightName}.itemref","=","{$itmName}.logicalref")
       ->join("{$invName}","{$stlName}.invoiceref","=","{$invName}.logicalref")
       ->join("{$custName}","{$stlName}.clientref","=","{$custName}.logicalref")
       ->select("{$stlName}.invoicelnno as line","{$itmName}.code as code","{$itmName}.name as name",
       "{$stlName}.amount as quantity","{$stlName}.price as price","{$stlName}.total as total",
       "{$stlName}.distdisc as discount","{$weightName}.grossweight as weight")
       ->where(["{$invName}.ficheno" => $invoice, 'lg_slsman.logicalref' => $slsman,"{$weightName}.linenr" => 1,"{$stlName}.iocode" => 4])
       ->orderby("{$stlName}.invoicelnno","asc")
       ->get();
       return response()->json([
           'status' => 'success',
           'message' => 'Invoice details',
           'invoice_info' => $info = (array) $info,
           'data' => $item,
       ]);
     }
      // retrieve orders based on date
   public function InvoiceDateFilter(Request $request)
   {
       $code = $request->header('citycode');
       $type = $request->header('type');
       $startDate = $request->input('start_date');
       $endDate = $request->input('end_date');
       $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
       $invoiceName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
       $invoice = DB::table('lg_slsman')
       ->join("{$invoiceName}","{$invoiceName}.salesmanref","=",'lg_slsman.logicalref')
       ->join("{$custName}","{$custName}.logicalref","=","{$invoiceName}.clientref")
       ->select(DB::raw("CONVERT(date, {$invoiceName}.capiblock_creadeddate) as order_date"), "{$invoiceName}.ficheno as order_number",
                "{$custName}.definition_ as customer_name", "{$custName}.addr1 as customer_address",
                'lg_slsman.code as salesman_code', "{$invoiceName}.nettotal as order_total_amount",)
       ->where(['lg_slsman.firmnr'=> $code,"{$invoiceName}.trcode" => $type])
       ->whereBetween(DB::raw("CONVERT(date, {$invoiceName}.CAPIBLOCK_CREADEDDATE)"), [$startDate, $endDate])
       ->orderBy(DB::raw("CONVERT(date, {$invoiceName}.CAPIBLOCK_CREADEDDATE)"), "desc")
       ->get();
   
       return response()->json([
           'status' => 'success',
           'message' => 'orders list',
           'data' => $invoice,
       ], 200);
   }
    // retrieve returned invoices that related to customer
    public function customerpreviousreturninvoices(Request $request)
    {
        $code = $request->header('citycode');
        $customer = $request->header('customer');
        $invoiceName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $invoices = DB::table("{$invoiceName}")
            ->join($custName, "{$invoiceName}.clientref", "=", "{$custName}.logicalref")
            ->select("{$invoiceName}.capiblock_creadeddate as date","{$invoiceName}.ficheno as invoice_number",
            "{$invoiceName}.grosstotal as amount","{$invoiceName}.totaldiscounts as discount","{$invoiceName}.nettotal as total",
            "{$invoiceName}.docode as from_p_invoice","{$invoiceName}.genexp1 as note",)
            ->where(["{$custName}.code" => $customer, "{$invoiceName}.trcode" => 3])
            ->orderby("{$invoiceName}.capiblock_creadeddate","desc")
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice list',
            'data' => $invoices,
        ]);
    }
    // retrieve returned invoices that related to customer
    public function customerpreviousinvoices(Request $request)
    {
        $code = $request->header('citycode');
        $customer = $request->header('customer');
        $invoiceName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $invoices = DB::table("{$invoiceName}")
            ->join($custName, "{$invoiceName}.clientref", "=", "{$custName}.logicalref")
            ->select("{$invoiceName}.capiblock_creadeddate as date","{$invoiceName}.ficheno as invoice_number",
            "{$invoiceName}.grosstotal as amount","{$invoiceName}.totaldiscounts as discount","{$invoiceName}.nettotal as total",
            "{$invoiceName}.docode as from_p_invoice","{$invoiceName}.genexp1 as note",)
            ->where(["{$custName}.code" => $customer, "{$invoiceName}.trcode" => 8])
            ->orderby("{$invoiceName}.capiblock_creadeddate","desc")
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice list',
            'data' => $invoices,
        ]);
    }
    //retrieve invoice by date
    public function searchreturnedinvoicebydate(Request $request)
    {
        $code = $request->header('citycode');
        $customer = $request->header('customer');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $invoiceName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $invoices = DB::table("{$invoiceName}")
            ->join($custName, "{$invoiceName}.clientref", "=", "{$custName}.logicalref")
            ->select(DB::raw("CONVERT(date, {$invoiceName}.capiblock_creadeddate) as invoice_date"),"{$invoiceName}.ficheno as invoice_number",
            "{$invoiceName}.grosstotal as amount","{$invoiceName}.totaldiscounts as discount","{$invoiceName}.nettotal as total",
            "{$invoiceName}.docode as from_p_invoice","{$invoiceName}.genexp1 as note",)
            ->where(["{$custName}.code" => $customer, /*"{$invoiceName}.trcode" => 3*/])
            ->whereBetween(DB::raw("CONVERT(date, {$invoiceName}.CAPIBLOCK_CREADEDDATE)"), [$startDate, $endDate])
            ->orderBy(DB::raw("CONVERT(date, {$invoiceName}.CAPIBLOCK_CREADEDDATE)"), "desc")
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice list',
            'data' => $invoices,
        ]);
    }
     //retrieve retail invoice by number
     public function searchreturnedinvoicebynumber(Request $request)
     {
         $code = $request->header('citycode');
         $customer = $request->header('customer');
         $invoice = $request->input('invoice_number');
         $invoiceName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
         $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
         $invoices = DB::table("{$invoiceName}")
         ->join($custName, "{$invoiceName}.clientref", "=", "{$custName}.logicalref")
         ->select("{$invoiceName}.capiblock_creadeddate as date","{$invoiceName}.ficheno as invoice_number",
         "{$invoiceName}.grosstotal as amount","{$invoiceName}.totaldiscounts as discount","{$invoiceName}.nettotal as total",
         "{$invoiceName}.docode as from_p_invoice","{$invoiceName}.genexp1 as note",)
         ->where("{$custName}.code", $customer)
        //  ->where("{$invoiceName}.trcode", 3)
         ->where("{$invoiceName}.ficheno", "LIKE", "%{$invoice}")
         ->orderby("{$invoiceName}.capiblock_creadeddate","desc")
         ->first();
         return response()->json([
             'status' => 'success',
             'message' => 'Invoice details',
             'data' => $invoices,
         ]);
     }

         // retrieve last 10 invoices that related to customer
    public function customerlastteninvoices(Request $request)
    {
        $code = $request->header('citycode');
        $customer = $request->header('customer');
        $invoiceName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $invoices = DB::table("{$invoiceName}")
            ->join($custName, "{$invoiceName}.clientref", "=", "{$custName}.logicalref")
            ->select("{$invoiceName}.capiblock_creadeddate as date","{$invoiceName}.ficheno as invoice_number",
            "{$invoiceName}.grosstotal as amount","{$invoiceName}.totaldiscounts as discount","{$invoiceName}.nettotal as total",)
            ->where(["{$custName}.code" => $customer,])
            ->orderby("{$invoiceName}.capiblock_creadeddate","desc")
            ->limit(10)
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice list',
            'data' => $invoices,
        ]);
    }
}
