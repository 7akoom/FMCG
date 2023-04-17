<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\ItemDefImport;
use Maatwebsite\Excel\Facades\Excel;
use DB;

class ItemDefController extends Controller
{
    public function import(Request $request){
        Excel::import(new ItemDefImport, $request->file('file')->store('files'));
        return response()->json();
    }
}
