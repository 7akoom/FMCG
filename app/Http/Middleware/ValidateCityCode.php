<?php

namespace App\Http\Middleware;

use Closure;
use DB;

class ValidateCityCode
{
    private function isValidCityCode($code)
    {
        $tables = ['lg_clcard', 'lv_01_clcard', 'lg_01_invoice', 'lg_slsclrel', 'lg_payplans', 'lg_01_clrnums', 'lg_items', 'lg_prclist', 'lg_unitsetf', 'lg_itmunita', 'lg_01_clfline'];
        foreach ($tables as $table) {
            $result = DB::table($table)->where('code', $code)->first();
            if ($result) {
                return true;
            }
        }
        return false;
    }

    public function handle($request, Closure $next)
    {
        $cityCode = $request->header('city-code');
        if (!$this->isValidCityCode($cityCode)) {
            return response()->json(['message' => 'Invalid city code'], 400);
        }
        
        return $next($request);
    }
}
