<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SalesManController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MarkController;
use App\Http\Controllers\SafeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemDefController;
use App\Http\Controllers\PayPlanController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\WareHouseController;
use App\Http\Controllers\CollectionController;
use App\Http\Middleware\AuthMiddleware;

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

Route::controller(OrderController::class)
->group(function () {
    Route::prefix('order')
    ->middleware(AuthMiddleware::class)
    ->group(function () {
        Route::prefix('accounting')->group(function () {
            Route::post('/orders', 'index');
            Route::post('/bystatus', 'ordersStatusFilter');
            Route::post('/bydate', 'OrderDateFilter');
            Route::post('/orderdetails', 'orderdetails');
            Route::post('/previousorderdetails', 'previousorderdetails');
            Route::patch('/orders/update-status/{orderId}', 'updateOrderStatus');
            // Route::post('/customercurrentorder', 'customerCurrentOrder');
        });
        Route::prefix('salesman')
        ->middleware(AuthMiddleware::class)
        ->group(function () {
            Route::post('/previousorder', 'salesmanlacurrentmonthorder');
            Route::post('/previousorderdetails', 'previousorderdetails');
            Route::post('/neworder', 'store');
        });
    });
});
