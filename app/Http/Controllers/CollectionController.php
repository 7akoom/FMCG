<?php

namespace App\Http\Controllers;

use App\Helpers\InvoiceNumberGenerator;
use Throwable;
use Carbon\Carbon;
use App\Helpers\TimeHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;


class CollectionController extends Controller
{
    protected $code;
    protected $salesman_id;
    protected $customer;
    protected $username;
    protected $salesmansTable;
    protected $customersTable;
    protected $customersViewsTable;
    protected $customerTransactionsTable;
    protected $safesTransactionsTable;
    protected $LedgersTable;
    protected $safesTable;
    protected $url;



    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->salesman_id = $request->header('id');
        $this->username = $request->header('username');
        $this->customer = $request->header('customer-code');
        $this->salesmansTable = 'LG_SLSMAN';
        $this->customersTable = 'LG_' . $this->code . '_CLCARD';
        $this->customersViewsTable = 'LV_' . $this->code . '_01_CLCARD';
        $this->customerTransactionsTable = 'LG_' . $this->code . '_01_CLFLINE';
        $this->safesTransactionsTable = 'LG_' . $this->code . '_01_KSLINES';
        $this->LedgersTable = 'LG_' . $this->code . '_01_PAYTRANS';
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
            'TIME' => TimeHelper::calculateTime(),
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
            $responseData = $response->json();
            $payment = DB::table("$this->safesTransactionsTable")
                ->leftjoin("$this->customersViewsTable", "$this->customersViewsTable.definition_", "=", "$this->safesTransactionsTable.custtitle")
                ->select(
                    "$this->safesTransactionsTable.CAPIBLOCK_CREADEDDATE as date",
                    "$this->safesTransactionsTable.CUSTTITLE as customer_name",
                    "$this->safesTransactionsTable.FICHENO as payment_number",
                    "$this->safesTransactionsTable.AMOUNT",
                    "$this->customersViewsTable.debit",
                    "$this->customersViewsTable.credit"
                )
                ->where([
                    "$this->safesTransactionsTable.logicalref" => $responseData['INTERNAL_REFERENCE'],
                ])
                ->first();
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'Order' => $payment,
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Payment failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function accountingCurrentAccountCollections($id)
    {   
        $customer_code = request()->input('customer_code');
        $customer_name = $this->fetchValueFromTable($this->customersTable,'code', $customer_code,'definition_');
        $safe_code = $this->fetchValueFromTable($this->safesTable,'logicalref', $id,'code');
        $data = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 11,
            "SD_CODE" => $safe_code,
            "CROSS_DATA_REFERENCE" => 0,
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "HOUR" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MINUTE" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "NUMBER" => InvoiceNumberGenerator::generateInvoiceNumber($this->safesTransactionsTable),
            "MASTER_TITLE" => $customer_name,
            "DESCRIPTION" => request()->note,
            "AMOUNT" => request()->amount,
            "RC_XRATE" => 1,
            "RC_AMOUNT" => request()->amount,
            "TC_XRATE" => 1,
            "TC_AMOUNT" => request()->amount,
            "CURR_TRANS" => 30,
            "CREATED_BY" => 139,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "DATA_REFERENCE" => 0,
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            'TIME' => TimeHelper::calculateTime(),
        ];
        $ATTACHMENT_ARP = [
            "INTERNAL_REFERENCE" => 0,
            "ARP_CODE" => $customer_code,
            "TRANNO" => InvoiceNumberGenerator::generateSafeNumber($this->customerTransactionsTable),
            "DESCRIPTION" => request()->note,
            "CREDIT" => request()->amount,
            "CURR_TRANS" => 30,
            "TC_XRATE" => 1,
            "TC_AMOUNT" => request()->amount,
            "RC_XRATE" => 1,
            "RC_AMOUNT" => request()->amount,
            "MONTH" => Carbon::now()->timezone('Asia/Baghdad')->format('m'),
            "YEAR" => Carbon::now()->timezone('Asia/Baghdad')->format('Y'),
            "AFFECT_RISK" => 1,
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "SALESMANREF" => request()->salesman_code,
            "DISTRIBUTION_TYPE_FNO" => 0,
        ];
        $PAYMENT_LIST = [
            "INTERNAL_REFERENCE" => 0,
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d H:i:s.v'),
            "MODULENR" => 10,
            "SIGN" => 1,
            "TRCODE" => 1,
            "TOTAL" => request()->amount,
            "PROCDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TRCURR" => 30,
            "TRRATE" => 1,
            "REPORTRATE" => 1,
            "DATA_REFERENCE" => 0,
            "DISCOUNT_DUEDATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "PAY_NO" => 1,
            "DISCTRDELLIST" => 0,
            "TRNET" => request()->amount,
            "LINE_EXP" => request()->note,
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
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/safeDepositSlips');
            $responseData = $response->json();
            $payment = DB::table("$this->safesTransactionsTable")
                ->leftjoin("$this->customersViewsTable", "$this->customersViewsTable.definition_", "=", "$this->safesTransactionsTable.custtitle")
                ->select(
                    "$this->safesTransactionsTable.CAPIBLOCK_CREADEDDATE as date",
                    "$this->safesTransactionsTable.CUSTTITLE as customer_name",
                    "$this->safesTransactionsTable.FICHENO as payment_number",
                    "$this->safesTransactionsTable.AMOUNT",
                    "$this->customersViewsTable.debit",
                    "$this->customersViewsTable.credit"
                )
                ->where([
                    "$this->safesTransactionsTable.logicalref" => $responseData['INTERNAL_REFERENCE'],
                ])
                ->first();
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'Order' => $payment,
            ], $response->status());
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
            "CREATED_BY" => request()->header('username'),            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('H'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "CURRSEL_TOTALS" => 1,
            "TIME" => TimeHelper::calculateTime(),
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
                'status' => $response->successful() ? 'success' : 'failed',
                'Order' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Payment failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cashVan()
    {
        $salesman_specode = $this->fetchValueFromTable($this->salesmansTable, 'definition_', $this->username, 'specode');
        $safe_code = $this->fetchValueFromTable($this->safesTable, 'specode', $salesman_specode, 'code');
        $salesman_code = $this->fetchValueFromTable($this->salesmansTable, 'definition_', $this->username, 'code');
        $customer_name = $this->fetchValueFromTable($this->customersTable, 'code', $this->customer, 'definition_');
        $number = $salesman_code . '-' . TimeHelper::calculateTime();
        $data = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 37,
            "SD_CODE" => $safe_code,
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "NUMBER" => $number,
            "MASTER_TITLE" => $customer_name,
            "DESCRIPTION" => request()->description,
            "AMOUNT" => request()->amount,
            "TC_AMOUNT" => request()->amount,
            "CREATED_BY" => 139,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('m'),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
        ];
        $ATTACHMENT_INVOICE = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 8,
            "NUMBER" => '~',
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TIME" => TimeHelper::calculateTime(),
            "ARP_CODE" => $this->customer,
            "SOURCE_WH" => 10,
            "POST_FLAGS" => 247,
            "TOTAL_DISCOUNTED" => request()->amount,
            "TOTAL_GROSS" => request()->amount,
            "TOTAL_NET" => request()->amount,
            "NOTES1" => request()->description,
            "CREATED_BY" => 139,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('m'),
            "SALESMAN_CODE" => $salesman_code,
            "EDURATION_TYPE" => 0,
            "EXIMVAT" => 0,
            "EARCHIVEDETR_INTPAYMENTTYPE" => 0,
        ];
        $DISPATCHES = [
            "INTERNAL_REFERENCE" => 0,
            "TYPE" => 8,
            "NUMBER" => '~',
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TIME" => TimeHelper::calculateTime(),
            "INVOICE_NUMBER" => $ATTACHMENT_INVOICE['NUMBER'],
            "ARP_CODE" => $this->customer,
            "SOURCE_WH" => 10,
            "INVOICED" => 1,
            "TOTAL_DISCOUNTED" => request()->amount,
            "TOTAL_GROSS" => request()->amount,
            "TOTAL_NET" => request()->amount,
            "CREATED_BY" => 139,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('m'),
            "SALESMAN_CODE" => $salesman_code,
            "DEDUCTIONPART1" => 0,
            "DEDUCTIONPART2" => 0,
            "AFFECT_RISK" => 1,
            "DISP_STATUS" => 1,
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "DOC_TIME" => TimeHelper::calculateTime(),
        ];

        $transactions = request()->input('TRANSACTIONS.items');
        foreach ($transactions as $item) {
            $itemData = [
                "INTERNAL_REFERENCE" => 0,
                "TYPE" => 0,
                "MASTER_CODE" => $item['item_code'],
                "SOURCEINDEX" => 10,
                "QUANTITY" => $item['item_quantity'],
                "PRICE" => $item['item_price'],
                "TOTAL" => $item['item_total'],
                "UNIT_CODE" => $item['item_unit'],
                "UNIT_CONV1" => 1,
                "UNIT_CONV2" => 1,
                "VAT_BASE" => $item['item_total'],
                "BILLED" => 1,
                "DISPATCH_NUMBER" => $DISPATCHES['NUMBER'],
                "MULTI_ADD_TAX" => 0,
                "EDT_CURR" => 30,
                "SALESMANCODE" => $salesman_code,
                "MONTH" => Carbon::now()->timezone('Asia/Baghdad')->format('m'),
                "YEAR" => Carbon::now()->timezone('Asia/Baghdad')->format('Y'),
                "AFFECT_RISK" => 1,
                "FOREIGN_TRADE_TYPE" => 0,
                "DISTRIBUTION_TYPE_WHS" => 0,
                "DISTRIBUTION_TYPE_FNO" => 0,
            ];
            $ATTACHMENT_INVOICE['INVOICE']['TRANSACTIONS']['items'][] = $itemData;
        }
        $ATTACHMENT_INVOICE['INVOICE']['DESPATCHES'] = $DISPATCHES;
        $data['ATTACHMENT_INVOICE'] = $ATTACHMENT_INVOICE;

        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/safeDepositSlips');
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'Order' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Payment failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function transferDebt($id)
    {
        $safe_source = $this->fetchValueFromTable($this->safesTable, 'logicalref', $id, 'code');
        $safe_destination = request()->destination_safe;
        $creator = DB::table('L_CAPIUSER')->where('name', request()->header('username'))->value('logicalref');
        $safe_destination_name = $this->fetchValueFromTable($this->safesTable, 'code', $safe_destination, 'name');
        $data = [
            'INTERNAL_REFERENCE' => 0,
            'TYPE' => 73,
            'SD_CODE' => $safe_source,
            'SD_CODE_CROSS' => request()->destination_safe,
            'SD_NUMBER_CROSS' => InvoiceNumberGenerator::generateSafeNumber($this->customerTransactionsTable),
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MINUTE" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "NUMBER" => InvoiceNumberGenerator::generateInvoiceNumber($this->safesTransactionsTable),
            "MASTER_TITLE" => $safe_destination_name,
            "DESCRIPTION" => request()->description,
            "AMOUNT" => request()->amount,
            "RC_XRATE" => 1,
            "RC_AMOUNT" => request()->amount,
            "TC_XRATE" => 1,
            "TC_AMOUNT" => request()->amount,
            "CURR_TRANS" => 30,
            "CREATED_BY" => $creator,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TIME" => TimeHelper::calculateTime(),
            "CROSS_TC_XRATE" => 1,
            "CROSS_TC_CURR" => 30,
            "CROSS_TC_AMOUNT" => request()->amount,
        ];
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/safeDepositSlips');
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'Order' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Payment failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function transferDues($id)
    {
        $safe_source = $this->fetchValueFromTable($this->safesTable, 'logicalref', $id, 'code');
        $safe_destination = request()->destination_safe;
        $creator = DB::table('L_CAPIUSER')->where('name', request()->header('username'))->value('logicalref');
        $safe_destination_name = $this->fetchValueFromTable($this->safesTable, 'code', $safe_destination, 'name');
        $data = [
            'INTERNAL_REFERENCE' => 0,
            'TYPE' => 73,
            'SD_CODE' => request()->destination_safe,
            'SD_CODE_CROSS' => $safe_source,
            'SD_NUMBER_CROSS' => InvoiceNumberGenerator::generateInvoiceNumber($this->safesTransactionsTable),
            "DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MINUTE" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "NUMBER" => InvoiceNumberGenerator::generateSafeNumber($this->customerTransactionsTable),
            "MASTER_TITLE" => $safe_destination_name,
            "DESCRIPTION" => request()->description,
            "AMOUNT" => request()->amount,
            "RC_XRATE" => 1,
            "RC_AMOUNT" => request()->amount,
            "TC_XRATE" => 1,
            "TC_AMOUNT" => request()->amount,
            "CURR_TRANS" => 30,
            "CREATED_BY" => $creator,
            "DATE_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "HOUR_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('h'),
            "MIN_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('i'),
            "SEC_CREATED" => Carbon::now()->timezone('Asia/Baghdad')->format('s'),
            "DOC_DATE" => Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d'),
            "TIME" => TimeHelper::calculateTime(),
            "CROSS_TC_XRATE" => 1,
            "CROSS_TC_CURR" => 30,
            "CROSS_TC_AMOUNT" => request()->amount,
        ];
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($data), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/safeDepositSlips');
            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'Order' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'Payment failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    public function newTransactionData($id)
    {
        $source_safe = $this->fetchValueFromTable($this->safesTable, 'logicalref', $id, 'code');
        $safe_transaction_number = InvoiceNumberGenerator::generateSafeNumber($this->customerTransactionsTable);
        $transaction_number = InvoiceNumberGenerator::generateInvoiceNumber($this->safesTransactionsTable);
        $date = Carbon::now()->timezone('Asia/Baghdad')->format('Y-m-d');
        $hour = Carbon::now()->timezone('Asia/Baghdad')->format('h');
        $minute = Carbon::now()->timezone('Asia/Baghdad')->format('i');
        $data = [
            'source_safe' => $source_safe,
            'safe_transaction_number' => $safe_transaction_number,
            'transaction_number' => $transaction_number,
            'date' => $date,
            'hour' => $hour,
            'minute' => $minute,
        ];
        return response()->json([
            'data' => $data
        ]);
    }
}
