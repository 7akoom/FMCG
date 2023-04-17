<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesMan;
use App\Imports\SalesmanImport;
use App\Exports\SalesmanExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use App\Models\LG_CLCARD; 
use App\Models\LG_SLSCLREL; 
use App\Models\LG_PAYLINES; 
use App\Models\LG_PAYPLANS; 
use App\Models\LV_01_CLCARD;
use App\Models\LG_01_CLRNUMS;
use App\Models\LG_01_INVOICE;
use App\Models\LG_01_CLFLINE;
use App\Models\LG_01_ORFICHE;
use App\Models\LG_01_STLINE;
use App\Models\LG_ITEMS;
use App\Models\LG_ITMUNITA;

class SalesManController extends Controller
{
    public function index(Request $request)
    {
        // retrieve salesmans list
        $code = $request->header('citycode');
        $position = $request->header('position');
        $salesman = DB::table('LG_SLSMAN')->select('LOGICALREF as id','code','DEFINITION_ as name','TELNUMBER as phone')
        ->WHERE(['FIRMNR' => $code, 'ACTIVE' => '0','position_' => $position])->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Salesman list',
            'data' => $salesman,
        ]);
    }

    // retrieve current month invoices that realted to salesman 
    public function previousinvoices(Request $request)
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
            ->whereMonth("{$invoiceName}.capiblock_creadeddate", '=', now()->month)
            ->where(["{$invoiceName}.salesmanref" => $slsman, "{$invoiceName}.trcode" => $type])
            ->orderby("{$invoiceName}.capiblock_creadeddate","desc")
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Salesman list',
            'data' => $invoices,
        ]);
    }
    // retrieve current month orders that related to salesman
    public function previousorders(Request $request)
    {
        $code = $request->header('citycode');
        $slsman = $request->header('id');
        $orderName = str_replace('{code}', $code, (new LG_01_ORFICHE)->getTable());
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $orders = DB::table('lg_slsman')
            ->join($orderName, "{$orderName}.salesmanref", "=", 'lg_slsman.logicalref')
            ->join($custName, "{$orderName}.clientref", "=", "{$custName}.logicalref")
            ->select("{$orderName}.logicalref as order_id","{$orderName}.ficheno as order_number", "{$orderName}.capiblock_creadeddate as order_date",
                "{$orderName}.status as order_status", "{$orderName}.nettotal as order_amount", "{$custName}.definition_ as customer_name")
            ->whereMonth("{$orderName}.capiblock_creadeddate", '=', now()->month)
            ->where("{$orderName}.salesmanref" , $slsman)
        ->orderby("{$orderName}.capiblock_creadeddate","desc")

            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Salesman list',
            'data' => $orders,
        ]);
    }
    // retrieve invoice details according on salesman logicalref and invoice logicalref
    public function invoicedetails(Request $request)
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
        ->where(["{$invName}.ficheno" => $invoice, 'lg_slsman.logicalref' => $slsman,"{$weightName}.linenr" => 1,"{$stlName}.iocode" => 4,])
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice details',
            'invoice_info' => $info = (array) $info,
            'data' => $item,
        ]);
      }

    public function salesmaninvoice(Request $request)
    {
      $slsman = $request->header('id');
      $code = $request->header('citycode');
      $slsman = $request->header('id');
      $perpage = $request->input('per_page', 15);
      $invoicetype = $request->header('invoicetype');
      $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
      $invName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
      $clfName = str_replace('{code}', $code, (new LG_01_CLFLINE)->getTable());
      $invoice = DB::table("{$custName}")
      ->join("{$invName}","{$custName}.logicalref","=","{$invName}.clientref")
      ->join("{$clfName}","{$invName}.logicalref","=","{$clfName}.sourcefref")
      ->join('lg_slsman',"lg_slsman.logicalref","=","{$invName}.salesmanref")
      ->select("{$custName}.code as customer_code", "{$custName}.definition_ as customer_name",
      "{$invName}.ficheno as invoice_number","{$invName}.date_ as invoice_date","{$clfName}.amount as total_amount",
      "{$invName}.grosstotal as weight")
      ->orderBy("{$invName}.date_", 'desc')
      ->where(["{$invName}.trcode"=> $invoicetype, 'lg_slsman.logicalref' => $slsman])
      ->paginate($perpage);
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

    public function order_items(Request $request)
      {
        $order = DB::table('lg_325_01_orfiche')
        ->join('lv_325_order_items','lg_325_01_orfiche.logicalref','=','lv_325_order_items.orfline_ordficheref')
        ->select('lv_325_order_items.items_code','lv_325_order_items.items_name','lv_325_order_items.unitsetl_name as unit',
        'lv_325_order_items.itmunita_grossweight as weight','lv_325_order_items.orfline_output_amount as qty',
        'lv_325_order_items.orfline_output_price as price','lv_325_order_items.orfline_output_total as total_price',
        'lv_325_order_items.orfiche_input_grosstotal as price_before_discount','lv_325_order_items.orfiche_input_nettotal as total_amount',
        'lv_325_order_items.capiwhouse_name as warehouse')
        ->where('lg_325_01_orfiche.ficheno','1222152733')
        ->get();
        return response()->json([
          'status' => 'success',
          'message' => 'Customers list',
          'data' => $order,
        ]);
      }
    // public function store(Request $request)
    // {
    //     $salesman = SalesMan::create([
    //         'CODE' => $request->code,
    //         'DEFINITION_' => $request->definition,
    //         'TELNUMBER' => $request->phone,
    //     ]);
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Salesman added successfully',
    //         'data' => $salesman,
    //     ]);
    // }
    // public function update(Request $request, $id)
    // {
    //     $slsman = SalesMan::where('LOGICALREF', $id)->first();
    //     if (!$slsman) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Salesman not found',
    //         ], 404);
    //     }
    //     $oldValues = [];
    //     if ($request->has('code')) {
    //         $oldValues['code'] = $slsman->CODE;
    //         $slsman->CODE = $request->input('code');
    //     }
    //     if ($request->has('definition')) {
    //         $oldValues['definition'] = $slsman->DEFINITION_;
    //         $slsman->DEFINITION_ = $request->input('definition');
    //     }
    //     if ($request->has('phone')) {
    //         $oldValues['phone'] = $slsman->TELNUMBER;
    //         $slsman->TELNUMBER = $request->input('phone');
    //     }
    //     $slsman->save();
    //     foreach ($oldValues as $key => $value) {
    //         $slsman->$key = $value;
    //     }
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Salesman updated successfully',
    //         'data' => $slsman,
    //     ]);
    // }
    // public function destroy($id)
    // {
    //     $slsman = SalesMan::where('LOGICALREF', $id)->first();
    //     if (!$slsman) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Salesman not found',
    //         ],404);
    //     }
    //     $slsman->delete();
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Salesman deleted successfully',
    //     ],200);
    // }
    public function import(Request $request){
        $type = Excel::import(new SalesmanImport, $request->file('file')->store('files'));
        return response()->json([
            'status' => 'success',
            'message' => 'Salesman imported successfully',
            'data' => $type,
        ],200);
     }

     public function export(Request $request){
        return Excel::download(new SalesmanExport, 'Salesman.xlsx');
     }
}
