<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Http\Request;
use App\Models\LG_CLCARD;

class CustomerImport implements ToCollection
{
    private $code;

    public function __construct(Request $request)
    {
        $this->code = $request->header('code');
    }

    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        $tableName = str_replace('{code}', $this->code, (new LG_CLCARD)->getTable());
        $customer = new LG_CLCARD;
        $customer->setTable($tableName);
        foreach ($collection as $row) {
            $customer->create([
                'CODE' => $row[0],
                'DEFINITION_' => $row[1],
            ]);
        }
    }
}