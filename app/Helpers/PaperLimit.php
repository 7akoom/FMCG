<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaperLimit
{
    public static function increaseNumber($tableName, $id, $number, $date)
    {
        $item = DB::table($tableName)
            ->select('printcnt')
            ->where('logicalref', $id)
            ->first();

        if ($item) {
            $counter = $item->printcnt + $number;

            DB::table($tableName)
                ->where('logicalref', $id)
                ->update([
                    'printcnt' => $counter,
                    'printdate' => DB::raw("convert(datetime,'$date',101)"),
                ]);

            return [
                'success' => true,
                'message' => 'Record updated successfully',
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Record not found',
            ];
        }
    }
}
