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
            DB::table('lg_325_specodes')->insert([
                'definition_' => $row[0],
                'definition2' => $row[1],
                'definition3' => $row[2],
                'codetype' => $row[3],
            ]);
        }
    }
    
}
