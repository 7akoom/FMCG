<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use App\Models\SalesMan;

class SalesmanImport implements ToCollection
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        foreach ($collection as $row) {
            SalesMan::create([
                'CODE' => $row[0],
                'DEFINITION_' => $row[1],
                'TELNUMBER' => $row[2],
            ]);
        }
    }
}
