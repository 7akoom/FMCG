<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TestController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/',[TestController::class,'invoicedetails']);


Route::get('/file-import',[TestController::class,'invoicedetails']);
Route::post('/import',[CustomerController::class,'import'])->name('import');
Route::get('/export-users',[CustomerController::class,'exportUsers'])->name('export-users');
Route::get('/categories',[TestController::class,'catAndSubCategory']);



