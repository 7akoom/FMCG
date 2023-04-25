<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LG_ITEMS;
use App\Models\LG_PRCLIST;
use App\Models\LG_CLCARD;
use App\Models\LG_PAYLINES;
use App\Models\LG_PAYPLANS;
use App\Models\LG_UNITSETF;
use App\Models\LG_ITMUNITA;
use App\Models\LG_01_CLRNUMS;
use App\Models\LG_01_INVOICE;
use App\Models\LG_SLSCLREL;
use App\Models\LV_01_CLCARD;
use App\Models\LG_01_STLINE;
use App\Imports\ItemDefImport;
use App\Imports\ItemImport;
use Maatwebsite\Excel\Facades\Excel;



class TestController extends Controller
{
  public function index(Request $request)
  {
      $slsman = $request->header('id');
      $code = $request->header('citycode');
      $relName = str_replace('{code}', $code, (new LG_SLSCLREL)->getTable());
      $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
      $ppName = str_replace('{code}', $code, (new LG_PAYPLANS)->getTable());
      $clcName = str_replace('{code}', $code, (new LV_01_CLCARD)->getTable());
      $clrName = str_replace('{code}', $code, (new LG_01_CLRNUMS)->getTable());
      $invoiceName = str_replace('{code}', $code, (new LG_01_INVOICE)->getTable());
      $results = DB::table("{$relName}")
          ->join('lg_slsman', "{$relName}.salesmanref", '=', 'lg_slsman.logicalref')
          ->join("{$custName}", "{$relName}.clientref", '=', "{$custName}.logicalref")
          ->join("{$clcName}","{$relName}.clientref",'=',"{$clcName}.logicalref")
          ->join("{$ppName}","{$ppName}.logicalref",'=',"{$custName}.paymentref")
          ->join("{$clrName}","{$clrName}.clcardref",'=',"{$custName}.logicalref")
          ->join("{$invoiceName}","{$custName}.logicalref",'=',"{$invoiceName}.clientref")
          ->select("{$custName}.logicalref as customer_id",
          "{$custName}.code as customer_code", "{$custName}.definition_ as customer_name", "{$custName}.addr1 as address","{$custName}.city",
          "{$custName}.country","{$custName}.telnrs1 as customer_phone","{$clcName}.debit","{$ppName}.definition_ as payment_plan",
          "{$clcName}.credit","{$clrName}.accrisklimit as customer_limit",DB::raw("DATEADD(day, CAST(LEFT({$ppName}.code, PATINDEX('%[^0-9]%', {$ppName}.code + ' ') - 1) AS int),
          MAX({$invoiceName}.date_)) as limit_end_date"),)
          ->where("{$custName}.country" ,"!=", "stop")
          ->where(['lg_slsman.logicalref' => $slsman,'lg_slsman.active' => '0',"{$custName}.active" => '0'])
          ->groupBy("{$invoiceName}.clientref", "{$custName}.logicalref","{$custName}.code","{$custName}.definition_","{$custName}.addr1","{$custName}.city","{$custName}.country"
          ,"{$custName}.telnrs1","{$clcName}.debit","{$ppName}.definition_","{$clcName}.credit","{$clrName}.accrisklimit","{$ppName}.code")
          ->get();
      return response()->json([
          'status' => 'success',
          'message' => 'Customers list',
          'data' => $results,
      ]);
  }
//   public function index()
// {
//   $limitEndDate = DB::table('lg_329_01_orfiche')
//   ->join('lg_329_clcard', 'lg_329_clcard.logicalref', '=', 'lg_329_01_orfiche.clientref')
//   ->join('lg_329_payplans', 'lg_329_clcard.paymentref', '=', 'lg_329_payplans.logicalref')
//   ->select('lg_329_01_orfiche.clientref', DB::raw("DATEADD(day, CAST(LEFT(lg_329_payplans.code, PATINDEX('%[^0-9]%', lg_329_payplans.code + ' ') - 1) AS int), MAX(lg_329_01_orfiche.date_)) as limit_end_date"), 'lg_329_payplans.code')
//   ->groupBy('lg_329_01_orfiche.clientref', 'lg_329_payplans.code')
//   ->get();


//     return response()->json($limitEndDate);
// }

  public function customerinvoice(Request $request)
      {
        $slsman = $request->header('id');
        $code = $request->header('citycode');
        $invoicetype = $request->header('invoicetype');
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $data = DB::table("{$custName}")
        ->join('lg_325_01_invoice',"{$custName}.logicalref","=","lg_325_01_invoice.clientref")
        ->join('lg_325_01_clfline',"lg_325_01_invoice.logicalref","=","lg_325_01_clfline.sourcefref")
        ->join('lg_slsman',"lg_slsman.logicalref","=","lg_325_01_invoice.salesmanref")
        ->select("{$custName}.code as customer_code", "{$custName}.definition_ as customer_name","lg_325_01_invoice.ficheno",
        "lg_325_01_invoice.date_","lg_325_01_clfline.amount","lg_325_01_invoice.grosstotal as weight")
        ->orderby('date_','desc')
        ->where(['lg_325_01_invoice.trcode'=> $invoicetype, 'lg_slsman.logicalref' => $slsman])
        ->paginate(50);
        return response()->json([
          'status' => 'success',
          'message' => 'Customers list',
          'data' => $data,
        ]);
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
      public function test(Request $request)
      {
        $code = $request->header('citycode');
        $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
        $debitName = str_replace('{code}', $code, (new LV_01_CLCARD)->getTable());
        $clrName = str_replace('{code}', $code, (new LG_01_CLRNUMS)->getTable());
        $ppName = str_replace('{code}', $code, (new LG_PAYPLANS)->getTable());
        $test = DB::table("{$custName}")
        ->join("{$debitName}","{$custName}.logicalref",'=',"{$debitName}.logicalref")
        ->join("{$clrName}","{$custName}.logicalref",'=',"{$clrName}.clcardref")
        ->join("{$ppName}","{$ppName}.logicalref",'=',"{$custName}.paymentref")
        ->select("{$custName}.logicalref","{$custName}.code","{$custName}.definition_","{$debitName}.debit","{$debitName}.credit","{$ppName}.code as payplan","{$clrName}.accrisklimit")
        ->get();
        return response()->json($test);
      }
}
