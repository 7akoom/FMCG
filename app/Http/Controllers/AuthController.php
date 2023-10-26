<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

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
        request()->validate([ 'username' => 'required','password' => 'required']);
           
        $username = request()->input('username');
        $password = request()->input('password');

        $isExists = DB::connection('sqlsrv2')->select("
            select * from TNM_KULLANICILAR where KullaniciAdi = '$username' and Sifre = '$password';
        ");

        if(!$isExists){
            return response()->json([
                'message' => 'Login failed',
            ], 422);
        }

        
        $salesMan = DB::select("
            select * from LG_slsman where active = 0 and firmnr = 888 and DEFINITION_ = '$username'; 
        ");

        if(!$salesMan){
            return response()->json([
                'message' => 'Login failed',
            ], 422);
        }

        return response()->json([
            'message' => 'Login successful',
            'data' => $salesMan[0]
        ]);

    }
}
