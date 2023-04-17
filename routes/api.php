<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SalesManController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemDefController;
use App\Http\Controllers\MarkController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\WareHouseController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TestController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::controller(SalesmanController::class)->group(function () {
    Route::prefix('salesman')->group(function () {
        Route::post('/', 'index');
        Route::post('/cashvan', 'cashvan');
        Route::post('/previousinvoices', 'previousinvoices');
        Route::post('/invoicedetails', 'invoicedetails');
        Route::post('/previousorders', 'previousorders');
    });
});

Route::controller(CustomerController::class)->group(function () {
    Route::prefix('customer')->group(function () {
        Route::post('/', 'index');
        Route::post('/getcustomerbycode', 'getcustomerByCode');
        Route::post('/customerdetails', 'customerDetails');
        Route::post('/salesmancustomers', 'salesmancustomers');
        Route::post('/debitandpayment', 'debitandpayment');
    });
});

Route::controller(ItemController::class)->group(function () {
    Route::prefix('item')->group(function () {
        Route::post('/', 'index');
    });
});

// Route::controller(ItemDefController::class)->group(function () {
//     Route::prefix('item')->group(function () {
//         Route::post('/def', 'import');
//     });
// });

Route::controller(MarkController::class)->group(function () {
    Route::prefix('mark')->group(function () {
        Route::post('/', 'import');
    });
});

Route::controller(EmployeeController::class)->group(function () {
    Route::prefix('emp')->group(function () {
        Route::post('/', 'index');
    });
});

Route::controller(OrderController::class)->group(function () {
    Route::prefix('order')->group(function () {
        Route::post('/', 'index');
        Route::post('/bystatus', 'ordersStatusFilter');
        Route::post('/bydate', 'OrderDateFilter');
        Route::post('/orderdetails', 'orderdetails');
        Route::post('/customerpreviousorder', 'customerpreviousorder');
        Route::post('/previousorderdetails', 'previousorderdetails');

    });
});

Route::controller(InvoiceController::class)->group(function () {
    Route::prefix('invoice')->group(function () {
        Route::prefix('customer')->group(function () {
            Route::post('/previousreturninvoices', 'customerpreviousreturninvoices');
            Route::post('/searchinvoicebydate', 'searchreturnedinvoicebydate');
            Route::post('/searchinvoicebynumber', 'searchreturnedinvoicebynumber');
        });
    });
});

Route::controller(WareHouseController::class)->group(function () {
    Route::prefix('whouse')->group(function () {
        Route::post('/merkez', 'mainWHouse');
        Route::post('/cashvan', 'cashvanWHouse');
        Route::post('/wastage', 'wastageWHouse');
    });
});

Route::controller(TestController::class)->group(function () {
    Route::prefix('test')->group(function () {
        Route::post('/', 'import');

    });
});
