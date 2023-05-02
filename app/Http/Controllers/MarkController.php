<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\MarkImport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use App\Models\LG_MARK;

class MarkController extends Controller
{
    //retrieve brands by type
    public function brands(Request $request){
        $code = $request->header("citycode");
        $type = $request->header("type");
        $markName = str_replace('{code}', $code, (new LG_MARK)->getTable());
        $brand = DB::table("{$markName}")
        ->select("{$markName}.logicalref as id","{$markName}.code as name","{$markName}.descr as image")
        ->where("{$markName}.specode",$type)
        ->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Brands list',
            'data' => $brand,
        ], 200);
     }
     // insert new brand
    public function store(Request $request){
        $code = $request->header("citycode");
        $markName = str_replace('{code}', $code, (new LG_MARK)->getTable());
        $validateData = $request->validate([
            'name' => 'required',
            'type' => 'required |exists:'.$markName .',logicalref',
            'image' => 'required',
        ],
        [
            'type.exists' => 'This type is not exist',
        ]);
        $data = array();
 	    $data['code'] = $request->name;
        $data['specode'] = $request->type;
 	    $image = $request->file('image');
 	    if ($image) {
 	        $image_name = date('dmy_H_s_i');
 	        $ext = strtolower($image->getClientOriginalExtension());
 	        $image_full_name = $image_name.'.'.$ext;
 	        $upload_path = 'public/media/mark/';
 	        $image_url = $upload_path.$image_full_name;
            $image->storeAs('public/media/mark/',$image_full_name);
            $data['descr'] = $image_url;
 	        $brand = DB::table("{$markName}")->insert($data);
             return response()->json($brand);
        }
        else
        {
            $brand = DB::table("{$markName}")->insert($data);
             return response()->json($brand);
       }
        return response()->json([
            'status' => 'success',
            'message' => 'Brand inserted successfully',
            'data' => $brand,
        ], 200);
     }
}
