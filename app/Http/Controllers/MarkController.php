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
use Illuminate\Support\Facades\Http;
use Throwable;


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
            ->select(
                "$this->brandsTable.logicalref as id",
                "$this->brandsTable.code as name",
                DB::raw("REVERSE(SUBSTRING(REVERSE($this->brandsTable.descr), 1, CHARINDEX('/', REVERSE($this->brandsTable.descr)) - 1)) as brand_image")
            );

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

    public function store()
    {
        $brand = [
            'CODE' => request('name'),
            'SPECODE' => request('type'),
        ];
        if (request()->file('image')) {
    		$file = request()->file('image');
    		$filename = date('YmdHi').$file->getClientOriginalName();
    		$file->move(public_path('media/mark'),$filename);
    		$brand['DESCR'] = 'public/media/mark/'.$filename;
    	}
         try {
            $response = Http::withOptions([
                'verify' => false,
            ])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => request()->header('authorization')
                ])
                ->withBody(json_encode($brand), 'application/json')
                ->post('https://10.27.0.109:32002/api/v1/itemBrands');
                return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'data' => $response->json(),
            ], $response->status());
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
