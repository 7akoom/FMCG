<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayPlanController extends Controller
{
    protected $code;
    protected $payplansTable;

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->payplansTable = 'LG_' . $this->code . '_PAYPLANS';
    }

    public function index(Request $request)
    {
        $plan = DB::table("$this->payplansTable")
            ->select('logicalref as id', 'code as name')
            ->where("$this->payplansTable.active", 0)
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'PayPlans list',
            'data' => $plan,
        ]);
    }
}
