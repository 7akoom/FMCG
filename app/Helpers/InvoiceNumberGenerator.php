<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class InvoiceNumberGenerator
{
    public static function generateInvoiceNumber($table)
    {
        $lastInvoice = DB::table($table)->orderByDesc('ficheno')->value('ficheno');
        $newInvoiceNumber = $lastInvoice ? intval($lastInvoice) + 1 : 1;
        return str_pad($newInvoiceNumber, 8, '0', STR_PAD_LEFT);
    }
}
