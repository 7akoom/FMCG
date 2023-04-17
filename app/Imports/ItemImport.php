<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use DB;


class ItemImport implements ToCollection
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            DB::table('lg_325_items')->insert([
                // 'logicalref' => $row[0],
                // 'active' => $row[1],
                // 'CODE' => $row[2],
                // 'name' => $row[3],
                // 'STGRPCODE' => $row[4],
                // 'specode' => $row[5],
                'classtype' => $row[6],
                // 'unitsetref' => $row[7],
                // 'capiblock_createdby' => $row[8],
                // 'capiblock_creadeddate' => $row[9],
                // 'capiblock_modifiedby' => $row[10],
                // 'capiblock_modifieddate' => $row[11],
                'markref' => $row[12],
                // 'name3' => $row[13],
                'categoryid' => $row[14],
                // 'name4' => $row[15],
            ]);
        }
    }
}
