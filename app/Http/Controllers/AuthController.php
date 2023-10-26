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

    public function login2()
    {
        request()->validate([ 'username' => 'required','password' => 'required']);
           
        $username = request()->input('username');
        $password = request()->input('password');

        $row = DB::connection('sqlsrv2')->select("
            select * from TNM_KULLANICILAR where KullaniciAdi = '$username' and Sifre = '$password';
        ");

        return response()->json([
            'data' => $row
        ]);
        
    }
}
