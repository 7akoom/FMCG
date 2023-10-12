<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SalesManController;

Route::controller(SalesmanController::class)->group(function () {
    Route::prefix('salesman')->group(function () {
        Route::prefix('accounting')->group(function () {
            Route::post('/salesmans', 'index');
            Route::post('/previousorders', 'previousorders');
            Route::post('/newsalesman', 'store');
        });
    });
});

Route::controller(CustomerController::class)->group(function () {
    Route::prefix('customer')->group(function () {
        Route::prefix('accounting')->group(function () {
            Route::post('/customers', 'index');
            Route::post('/getcustomerbycode', 'getcustomerByCode');
            Route::post('/debitandpayment', 'debitandpayment');
            Route::post('/newcustomer', 'newCustomer');
            Route::post('/pendingcustomer', 'pendingCustomerDetails');
            Route::post('/updatecustomerstatus/{id}', 'UpdatePendingCustomer');
            Route::post('/salesmancustomers', 'accountingSalesmanCustomers');
        });
        Route::prefix('salesman')->group(function () {
            Route::post('/customers', 'salesmancustomers');
            Route::post('/customerdetails', 'customerDetails');
            Route::post('/debitandpayment', 'debitandpayment');
            Route::post('/getcustomerbycode', 'getcustomerByCode');
            Route::post('/newcustomer', 'store');
        });
    });
});

Route::controller(ItemController::class)->group(function () {
    Route::prefix('item')->group(function () {
        Route::prefix('salesman')->group(function () {
            Route::post('/items', 'index');
            Route::post('/itemdetails', 'getItemDetails');
        });
        Route::prefix('accounting')->group(function () {
            Route::post('/items', 'index');
            Route::post('/item', 'finalItem');
            Route::post('/filterbybrand', 'searchbybrand');
            Route::post('/itemdetails', 'getItemDetails');
        });
    });
});
