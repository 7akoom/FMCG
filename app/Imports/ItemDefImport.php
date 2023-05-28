<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use DB;

class ItemDefImport implements ToCollection
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            DB::table('lg_888_specodes')->insert([
                'codetype' => $row[0],
                'specodetype' => $row[1],
                'specode' => $row[2],
                'definition_' => $row[3],
                'color' => $row[4],
                'wincolor' => $row[5],
                'siteid' => $row[6],
                'recstatus' => $row[7],
                'orglogicref' => $row[8],
                'spetyp1' => $row[9],
                'spetyp2' => $row[10],
                'spetyp3' => $row[11],
                'spetyp4' => $row[12],
                'spetyp5' => $row[13],
                'globalid' => '',
                'definition2' => $row[15],
                'definition3' => $row[16],
            ]);
        }
    }
    
}
