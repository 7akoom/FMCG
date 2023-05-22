<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use App\Models\LG_CLCARD;
use App\Models\LG_01_KSLINES;
use App\Models\LG_KSCARD;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Str;


class CollectionController extends Controller
{
    public function newCustomerPayment (Request $request)
    {
        try {
                $code = $request->header("citycode");
                $salesman = $request->header("id");
                $customer = $request->header("customer");
                $custName = str_replace('{code}', $code, (new LG_CLCARD)->getTable());
                $ksLines = str_replace('{code}', $code, (new LG_01_KSLINES)->getTable());
                $ksName = str_replace('{code}', $code, (new LG_KSCARD)->getTable());
                $salesmanSafe = DB::table("{$ksName}")->where(['specode' => 1 , 'cyphcode' => $salesman])->value('logicalref');
                $lastTransaction = DB::table("{$ksLines}")->where('cardref' , $salesmanSafe)->orderBy('logicalref', 'DESC')->value('transref');
                $salesmanCode = DB::table('lg_slsman')->where('logicalref',$salesman)->value('code');
                $customerName = DB::table("{$custName}")->where('logicalref',$customer)->value('definition_');
                $kslinesColumns = Schema::getColumnListing($ksLines);
                $data = [ 
                    "ACCFICHEREF" => 0,
                    "ACCOUNTED" => 0,
                    "ACCREF" => 0,
                    "AFFECTCOLLATRL" => 0,
                    "AFFECTCOST" => 0,
                    "AFFECTRISK" => 0,
                    "AMOUNT" => $request->amount,
                    "APPROVE" => 0,
                    "APPROVEDATE" => 0,
                    "BRANCH" => 0,
                    "BRANCH" => 0,
                    "BRUTAMOUNT" => 0,
                    "BRUTAMOUNTREP" => 0,
                    "BRUTAMOUNTTR" => 0,
                    "CANCELLED" => 0,
                    "CANCELLEDACC" => 0,
                    "CANCELLEDREFLACC" => 0,
                    "CANDEDUCT" => 0,
                    "CANTCREDEDUCT" => 0,
                    "CAPIBLOCK_CREADEDDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
                    "CAPIBLOCK_CREATEDBY" => $salesman,
                    "CAPIBLOCK_CREATEDHOUR" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
                    "CAPIBLOCK_CREATEDMIN" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
                    "CAPIBLOCK_CREATEDSEC" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
                    "CAPIBLOCK_MODIFIEDBY" => 0,
                    "CAPIBLOCK_MODIFIEDDATE" => '',
                    "CAPIBLOCK_MODIFIEDHOUR" => 0,
                    "CAPIBLOCK_MODIFIEDMIN" => 0,
                    "CAPIBLOCK_MODIFIEDSEC" => 0,
                    "CARDREF" => $salesmanSafe,
                    "CASHACCREF" => 0,
                    "CASHCENREF" => 0,
                    "CENTERREF" => 0,
                    "CLTRCURR" => 0,
                    "CLTRNET" => 0,
                    "CLTRRATE" => 0,
                    "CRCARDWZD" => 0,
                    "CSACCREF" => 0,
                    "CSCENTERREF" => 0,
                    "CSTRANSREF" => 0,
                    "CUSTTITLE" => $customerName,
                    "CUSTTITLE2" => '',
                    "CYPHCODE" => 0,
                    "DATE_" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
                    "DEDUCTCODE" => '',
                    "DEDUCTIONPART1" => 0,
                    "DEDUCTIONPART2" => 0,
                    "DEPARTMENT" => 0,
                    "DESTBRANCH" => 0,
                    "DESTDEPARTMENT" => 0,
                    "DOCDATE" => '',
                    "DOCODE" => '',
                    "EIDISTFLNNR" => 0,
                    "EINVOICE" => 0,
                    "EISRVDSTTYP" => 0,
                    "ELECTDOC" => 0,
                    "EMFLINEREF" => 0,
                    "EXIMDISTTYP" => 0,
                    "EXIMFILEREF" => 0,
                    "EXIMPROCNR" => 0,
                    "EXIMTYPE" => 0,
                    "FICHENO" => $salesmanCode.'-'.Carbon::now()->timezone('Asia/Baghdad')->format('siHdmY'),
                    "GENEXCTYP" => 0,
                    "GPADDR" => '',
                    "GPFUNDACC" => 0,
                    "GPFUNDSHARERAT" => 0,
                    "GPINCOMETACRAT" => 0,
                    "GPOPTYPE" => 0,
                    "GPPLATE" => '',
                    "GPTAXACC" => 0,
                    "GRPFIRMTRANS" => 0,
                    "GUID" => Str::uuid()->toString(),
                    "HOUR_" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
                    "INCDEDUCTAMNT" => 0,
                    "INFIDX" => 0,
                    "ISPERSCOMP" => 0,
                    "LINEEXCTYP" => 0,
                    "LINEEXP" => $request->deposit_description,
                    "MINUTE_" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
                    "NETAMOUNT" => 0,
                    "NETAMOUNTREP" => 0,
                    "NETAMOUNTTR" => 0,
                    "OFFERREF" => 0,
                    "ORGLOGICREF" => 0,
                    "ORGLOGOID" => '',
                    "PRINTCNT" => 0,
                    "PRINTDATE" => 0,
                    "PROJECTREF" => 0,
                    "RECSTATUS" => 0,
                    "REFLACCFICHEREF" => 0,
                    "REFLECTED" => 0,
                    "REPORTNET" => 0,
                    "REPORTRATE" => 0,
                    "SALESMANREF" => 0,
                    "SERVREASONDEF" => '',
                    "SIGN" => 0,
                    "SITEID" => 0,
                    "SMMDOCODE" => '',
                    "SMMSERIALCODE" => '',
                    "SMMVATACREF" => 0,
                    "SMMVATCENTREF" => 0,
                    "SMMVATRATE" => 0,
                    "SPECODE" => 0,
                    "STATUS" => 0,
                    "TAXNR" => '',
                    "TCKNO" => '',
                    "TEXTINC" => 0,
                    "TIME_" => 0,
                    "TRADINGGRP" => 0,
                    "TRANGRPLINENO" => 0,
                    "TRANGRPNO" => '', 
                    "TRANNO" => '',
                    "TRANSREF" => $lastTransaction +1,
                    "TRCODE" => 11,
                    "TRCURR" => 30,
                    "TRNET" => 0,
                    "TRRATE" => 0,
                    "TYPECODE" => '',
                    "UNDERDEDUCTLIMIT" => 0,
                    "VATACCREF" => 0,
                    "VATDEDUCTACCREF" => 0,
                    "VATDEDUCTCENREF" => 0,
                    "VATDEDUCTOTHACCREF" => 0,
                    "VATDEDUCTOTHCENREF" => 0,
                    "VATDEDUCTRATE" => 0,
                    "VATINC" => 0,
                    "VATRAT" => 0,
                    "VATTOT" => 0,
                    "VCARDREF" => 0,
                    "WFLOWCRDREF" => 0,
                    "WFSTATUS" => 0,
                  ];
                DB::table("{$ksLines}")->insert($data);
                return response()->json([
                    'status' => 'success',
                    'message' => $data['AMOUNT'].'IQD has been successfully paid',
                ],200);
            }
            catch (Throwable $e) {
                return response()->json([
                    'status' => 'Payment failed',
                    'message' => $e,
                ], 422);
            }
    }
}
