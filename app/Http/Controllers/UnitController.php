<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    protected $code;
    protected $unitsTable;

    public function __construct(Request $request)
    {
        $this->code = $request->header('citycode');
        $this->unitsTable = 'LG_' . $this->code . '_UNITSETL';
    }

    public function itemUnit(Request $request)
    {
        $item = $request->item_unit;
        $unit = DB::table("$this->unitsTable")
            ->select('logicalref as id', 'name as unit_name')
            ->where('unitsetref', $item)
            ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Units list',
            'data' => $unit,
        ], 200);
    }
}
