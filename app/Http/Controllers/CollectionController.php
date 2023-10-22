<?php

namespace App\Http\Controllers;

use Throwable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;


class CollectionController extends Controller
{
    protected $code;
    protected $salesman_id;
    protected $salesmansTable;
    protected $customersTable;
    protected $safesTable;
    protected $url;



    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->salesman_id = $request->header('id');
        $this->salesmansTable = 'LG_SLSMAN';
        $this->customersTable = 'LG_' . $this->code . '_CLCARD';
        $this->safesTable = 'LG_' . $this->code . '_KSCARD';
        $this->url = '/safeDepositSlips';
    }

    private function fetchValueFromTable($table, $column, $value1, $value2)
    {
        if ($table == $this->salesmansTable) {
            return DB::table($table)
                ->where([$column => $value1, 'ACTIVE' => 0, 'FIRMNR' => $this->code])
                ->value($value2);
        }
        return DB::table($table)
            ->where([$column => $value1, 'ACTIVE' => 0])
            ->value($value2);
    }

    public function currentAccountCollections(Request $request)
    {
        $customer = $request->header('customer');
        $salesman_specode = $this->fetchValueFromTable($this->salesmansTable, 'logicalref', $this->salesman_id, 'specode');
        $salesman_code = $this->fetchValueFromTable($this->salesmansTable, 'logicalref', $this->salesman_id, 'code');
        $salesman_safe = $this->fetchValueFromTable($this->safesTable, 'specode', $salesman_specode, 'code');
        $customer_name = $this->fetchValueFromTable($this->customersTable, 'code', $customer, 'definition_');
        $data = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 11,
            "SD_CODE" => $salesman_safe,
            "CROSS_DATA_REFERENCE" => 0,
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "HOUR" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MINUTE" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "NUMBER" => "~",
            "MASTER_TITLE" => $customer_name,
            "DESCRIPTION" => $request->note,
            "AMOUNT" => $request->amount,
            "RC_XRATE" => 1,
            "RC_AMOUNT" => $request->amount,
            "TC_XRATE" => 1,
            "TC_AMOUNT" => $request->amount,
            "CURR_TRANS" => 30,
            "CREATED_BY" => 139,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "DATA_REFERENCE" => 0,
            "POS_TRANSFER_INFO" => $salesman_code,
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SALESMANREF" => $this->salesman_id,
            'TIME' => calculateTime(),
        ];
        $ATTACHMENT_ARP = [
            "INTERNAL_REFERENCE" => 0,
            "ARP_CODE" => $customer,
            "TRANNO" => '~',
            "DESCRIPTION" => $request->note,
            "CREDIT" => $request->amount,
            "CURR_TRANS" => 30,
            "TC_XRATE" => 1,
            "TC_AMOUNT" => $request->amount,
            "RC_XRATE" => 1,
            "RC_AMOUNT" => $request->amount,
            "MONTH" => Carbon::now()->timezone('Asia/Baghdad')->format('m'),
            "YEAR" => Carbon::now()->timezone('Asia/Baghdad')->format('Y'),
            "AFFECT_RISK" => 1,
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SALESMANREF" => $this->salesman_id,
            "DISTRIBUTION_TYPE_FNO" => 0,
        ];
        $PAYMENT_LIST = [
            "INTERNAL_REFERENCE" => 0,
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "MODULENR" => 10,
            "SIGN" => 1,
            "TRCODE" => 1,
            "TOTAL" => $request->amount,
            "PROCDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TRCURR" => 30,
            "TRRATE" => 1,
            "REPORTRATE" => 1,
            "DATA_REFERENCE" => 0,
            "DISCOUNT_DUEDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "PAY_NO" => 1,
            "DISCTRDELLIST" => 0,
            "TRNET" => $request->amount,
            "LINE_EXP" => $request->note,
        ];
        $ATTACHMENT_ARP['PAYMENT_LIST']['items'][] = $PAYMENT_LIST;
        $data['ATTACHMENT_ARP']['items'][] = $ATTACHMENT_ARP;
        try {

            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/safeDepositSlips');
            return response()->json([
                'status' => 'success',
                'message' => 'Order sent successfully',
                'Order' => json_decode($response),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Payment failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function transferFromSalesmanToAccountant(Request $request)
    {
        $source_safe = $request->header('sourcesafe');
        $destination_safe = $request->input('destination_safe');
        $name = $this->fetchValueFromTable($this->safesTable, 'code', $destination_safe, 'name');
        $data = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 73,
            "SD_CODE" => $source_safe,
            "SD_CODE_CROSS" => $destination_safe,
            "SD_NUMBER_CROSS" => "~",
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MINUTE" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "NUMBER" => "~",
            "MASTER_TITLE" => $name,
            "AMOUNT" => $request->amount,
            "RC_XRATE" => 1,
            "RC_AMOUNT" => $request->amount,
            "TC_XRATE" => 1,
            "TC_AMOUNT" => $request->amount,
            "DESCRIPTION" => $request->description,
            "CURR_TRANS" => 30,
            "CREATED_BY" => 137,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TIME" => strtotime(Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v')),
            "CROSS_TC_XRATE" => 1,
            "CROSS_TC_CURR" => 30,
            "CROSS_TC_AMOUNT" => $request->amount,
        ];
        // dd($data['SD_CODE']);
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/safeDepositSlips');
            return response()->json([
                'status' => 'success',
                'message' => 'Transaction completed successfully',
                'transaction' => json_decode($response),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Payment failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    public function currentAccountPayment(Request $request)
    {
        $source_safe = $request->header('sourcesafe');
        $destination_safe = $request->input('destination_safe');
        $name = $this->fetchValueFromTable($this->safesTable, 'code', $destination_safe, 'name');
        $data = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 12,
            "SD_CODE" => $source_safe,
            "CROSS_DATA_REFERENCE" => $destination_safe,
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MINUTE" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "NUMBER" => "~",
            "MASTER_TITLE" => $name,
            "AMOUNT" => $request->amount,
            "SIGN" => 1,
            "RC_XRATE" => 1,
            "RC_AMOUNT" => $request->amount,
            "TC_AMOUNT" => $request->amount,
            "CREATED_BY" => 137,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "CURRSEL_TOTALS" => 1,
            "TIME" => strtotime(Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v')),
        ];
        $TRANSACTION = [
            "INTERNAL_REFERENCE" => 0,
            "ARP_CODE" => $request->customer_code,
            "TRANNO" => "~",
            "DEBIT" => $request->amount,
            "TC_AMOUNT" => $request->amount,
            "RC_XRATE" => 1,
            "RC_AMOUNT" => $request->amount,
        ];
        $PAYMENT_LIST = [
            "INTERNAL_REFERENCE" => 0,
            "CARDREF" => $request->custoemr_id,
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "MODULENR" => 10,
            "FICHEREF" => "~",
            "TRCODE" => 2,
            "TOTAL" => $request->amount,
            "PAID" => $request->amount,
            "PROCDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "REPORTRATE" => 1,
            "CROSSTOTAL" => $request->amount,
            "CLOSINGRATE" => 1,
            "DISCOUNT_DUEDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "PAY_NO" => 1,
            "PAYMENT_TYPE" => 1,
            "DISCTRDELLIST" => 1,
        ];
        $TRANSACTION["PAYMENT_LIST"] = $PAYMENT_LIST;
        $ATTACHMENT_ARP["TRANSACTION"] = $TRANSACTION;
        $data["ATTACHMENT_ARP"] = $ATTACHMENT_ARP;
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => $request->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/safeDepositSlips');
            return response()->json([
                'status' => 'success',
                'message' => 'Order sent successfully',
                'Order' => json_decode($response),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Payment failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
