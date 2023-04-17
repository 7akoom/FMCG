<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $code = $request->header('citycode');
        $employee = Employee::select(
            'name','firmnr as city',
        )
        ->where('FIRMNR', $code)->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Employee list',
            'data' => $employee,
        ]);
    }
}
