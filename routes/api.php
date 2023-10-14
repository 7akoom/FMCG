<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MarkController;
use App\Http\Controllers\SafeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemDefController;
use App\Http\Controllers\PayPlanController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\SalesManController;
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

Route::controller(ItemDefController::class)->group(function () {
    Route::prefix('itemdef')->group(function () {
        Route::prefix('salesman')->group(function () {
            Route::post('/category', 'categories');
            Route::post('/subcategory', 'subcategories');
        });
        Route::prefix('accounting')->group(function () {
            Route::post('/categoriestree', 'catAndSubCategory');
        });
    });
});


Route::controller(MarkController::class)->group(function () {
    Route::prefix('mark')->group(function () {
        Route::prefix('salesman')->group(function () {
            Route::post('/brands', 'brands');
        });
        Route::prefix('accounting')->group(function () {
            Route::post('/brands', 'brands');
        });
        Route::prefix('management')->group(function () {
            Route::post('/new', 'store');
        });
    });
});

Route::controller(PayPlanController::class)->group(function () {
    Route::prefix('payplans')->group(function () {
        Route::prefix('accounting')->group(function () {
            Route::post('/payplans', 'index');
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

Route::controller(InvoiceController::class)
    ->group(function () {
        Route::prefix('invoice')
            ->middleware(AuthMiddleware::class)
            ->group(function () {
                Route::prefix('accounting')->group(function () {
                    Route::post('/previoussalesreturninvoices', 'customerprevioussalesreturninvoices');
                    Route::post('/customerprevioussalesinvoices', 'customerprevioussalesinvoices');
                    Route::post('/searchinvoicebydate', 'searchreturnedinvoicebydate');
                    Route::post('/searchinvoicebynumber', 'searchinvoicebynumber');
                    Route::post('/salesmaninvoices', 'salesmaninvoices');
                    Route::post('/invoicedetails', 'accountingSalesmanInvoiceDetails');
                    Route::post('/newsalesinvoice', 'doSalesInvoice');
                    Route::post('/newpurchaseinvoice', 'doPurchaseInvoice');
                    Route::post('/invoices', 'InvoicesList');
                    Route::post('/salesinvoicedetails', 'salesinvoicedetails');
                    Route::post('/salesreturninvoice', 'salesReturnInvoicesList');
                    Route::post('/salesreturninvoicedetails', 'salesReturnInvoiceDetails');
                    Route::post('/purchaseinvoicedetails', 'purchaseinvoicedetails');
                    Route::post('/purchasereturninvoices', 'purchaseReturnInvoicesList');
                    Route::post('/purchasereturninvoicesdetails', 'purchaseReturnInvoicesDetails');
                    Route::post('/purchasedservicesinvoice', 'purchaseServicesInvoicesList');
                    Route::post('/purchasedservicesinvoicedetails', 'purchasedServicesInvoicedetails');
                });
                Route::prefix('salesman')
                    ->middleware(AuthMiddleware::class)
                    ->group(function () {
                        Route::post('/previousinvoices', 'salesmanmonthlyinvoices');
                        Route::post('/invoicedetails', 'salesmaninvoicedetails');
                        Route::post('/customerpreviousinvoices', 'customerlastteninvoices');
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

Route::controller(SafeController::class)
    ->group(function () {
        Route::prefix('safes')
            ->middleware(AuthMiddleware::class)
            ->group(function () {
                Route::prefix('accounting')->group(function () {
                    Route::post('/safes', 'index');
                    Route::post('/safetransaction', 'accountingsalesmanSafeTransaction');
                    Route::post('/transactiondetails/{id}', 'fetchTransactionDetails');
                });
                Route::prefix('salesman')
                    ->middleware(AuthMiddleware::class)
                    ->group(function () {
                        Route::post('/safetransaction', 'salesmanSafeTransaction');
                        Route::post('/collection/{id}', 'collectionDetails');
                    });
            });
    });

Route::controller(CollectionController::class)
    ->group(function () {
        Route::prefix('collection')
            ->middleware(AuthMiddleware::class)
            ->group(function () {
                Route::prefix('accounting')->group(function () {
                    Route::post('/transferofreceivables', 'transferFromSalesmanToAccountant');
                    Route::post('/newcurrentaccountcollection', 'currentAccountCollections');
                    Route::post('/newcurrentaccountpayment', 'currentAccountPayment');
                });
                Route::prefix('salesman')
                    ->middleware(AuthMiddleware::class)
                    ->group(function () {
                        Route::post('/newcurrentaccountcollection', 'currentAccountCollections');
                    });
            });
    });
