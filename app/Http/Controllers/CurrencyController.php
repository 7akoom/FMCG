<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    protected $code;
    protected $currenciesTable;

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->currenciesTable = 'L_CURRENCYLIST';
    }

    public function index()
    {
        $currency = DB::table($this->currenciesTable)
            ->select('logicalref as id', 'curcode as currency_code', 'curname as currency_name')
            ->where('firmnr', 500)
            ->get();
        if ($currency->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'customer updated successfully',
            'data' => $currency
        ]);
    }
}
