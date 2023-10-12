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

class MarkController extends Controller
{
    protected $code;
    protected $brandsTable;
    protected $type;

    public function __construct(Request $request)
    {
        $this->code = $request->header("citycode");
        $this->type = $request->header("type");
        $this->brandsTable = 'LG_' . $this->code . '_MARK';
    }

    public function brands(Request $request)
    {
        $brand = DB::table("$this->brandsTable")
            ->select("$this->brandsTable.logicalref as id", "$this->brandsTable.code as name", "$this->brandsTable.descr as image");
        if ($request->hasHeader('type')) {
            if ($this->type == -1) {
                $data = $brand->get();
            } else {
                $brand->where("$this->brandsTable.specode", $this->type);
            }
        }
        $data = $brand->get();
        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'There is no data',
                'data' => $data
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Brands list',
            'data' => $data,
        ], 200);
    }
}
