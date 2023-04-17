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

class InvoiceController extends Controller
{
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
            ->where(["{$custName}.code" => $customer, "{$invoiceName}.trcode" => 3])
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
         ->where("{$invoiceName}.trcode", 3)
         ->where("{$invoiceName}.ficheno", "LIKE", "%{$invoice}")
         ->orderby("{$invoiceName}.capiblock_creadeddate","desc")
         ->first();
         return response()->json([
             'status' => 'success',
             'message' => 'Invoice details',
             'data' => $invoices,
         ]);
     }
}
