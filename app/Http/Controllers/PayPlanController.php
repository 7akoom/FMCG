<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LG_PAYPLANS;
use DB;

class PayPlanController extends Controller
{
    public function index(Request $request)
    {
        $code = $request->header('citycode');
        $planName = str_replace('{code}', $code, (new LG_PAYPLANS)->getTable());
        $plan = DB::table("{$planName}")->select('logicalref as id','code as name')->get();
        return response()->json([
            'status' => 'success',
            'message' => 'PayPlans list',
            'data' => $plan,
        ]);
    }
}
