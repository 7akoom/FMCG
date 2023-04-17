<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use DB;
use Illuminate\Http\Request;


class CustomerExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    private $code;

    public function __construct(Request $request)
    {
        $this->code = $request->header('code');
    }

    public function collection()
    {
        $tableName = DB::table('LG_'.$this->code.'_CLCARD') ;
        return $tableName->get(); 
    }
}
