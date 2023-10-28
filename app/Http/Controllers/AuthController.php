<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\LOG;

class AuthController extends Controller
{
    public function login()
    {
        return response()->json([
            'message' => 'login successful',
            'is_authenticated' => true
        ]);
    }

    public function salesManLogin()
    {
        request()->validate(['username' => 'required', 'password' => 'required']);

        $username = request()->input('username');
        $password = request()->input('password');

        $isExists = DB::connection('sqlsrv2')->select("
            select * from TNM_KULLANICILAR where KullaniciAdi = '$username' and Sifre = '$password';
        ");

        if (!$isExists) {
            return response()->json([
                'message' => 'login failed',
                'data' => []
            ]);
        }


        $salesMan = DB::select("
            select * from LG_slsman where active = 0 and firmnr = 888 and DEFINITION_ = '$username';
        ");

        if (!$salesMan || !isset($salesMan[0])) {
            return response()->json([
                'message' => 'login failed',
                'data' => []
            ]);
        }

        if(!$this->checkSalesManDevice()) {
              return response()->json([
                'message' => 'invalid device for the salesman',
                'data' => []
            ]);
        }

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'salesman_id' => $salesMan[0]->LOGICALREF,
                'salesman_position' => $salesMan[0]->POSITION_,
            ]
        ]);
    }

    private function checkSalesManDevice()
    {
        $username = request()->input('username');

        $deviceId = request()->header('deviceid');

        if(!$deviceId) {
            return false;
        }

        Log::debug("checking logging for user $username and device id $deviceId");

        $row =  DB::connection('sqlite')->select("SELECT * from users where username = '$username'");

        if(!$row) {
            DB::connection('sqlite')->select("Insert into users (username,device_id) values('$username', '$deviceId')");
            return true;
        }

        if($row[0]->device_id == $deviceId) {
            return true;
        }

        return false;
    }
}
