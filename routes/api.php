<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MarkController;
use App\Http\Controllers\SafeController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemDefController;
use App\Http\Controllers\PayPlanController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SalesManController;
use App\Http\Controllers\WareHouseController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CollectionController;

Route::controller(SalesmanController::class)->group(function () {
    Route::prefix('salesman')->group(function () {
        Route::prefix('accounting')->group(function () {
            Route::post('/salesmans', 'index');
            Route::post('/newsalesman', 'store');
            Route::post('/edit/{salesmanId}', 'edit');
            Route::post('/update/{salesmanId}', 'update');
            Route::post('/delete/{salesmanId}', 'destroy');

            // Route::post('/previousorders', 'previousorders');
        });
    });
});

Route::controller(CustomerController::class)->group(function () {
    Route::prefix('customer')->group(function () {
        Route::prefix('accounting')->group(function () {
            Route::post('/customers', 'index');
            Route::post('/getcustomerbycode', 'getcustomerByCode');
            Route::post('/debitandpayment', 'debitandpayment');
            // Route::post('/newcustomer', 'pendingCustomerList');
            Route::post('/pendingcustomer', 'pendingCustomerDetails');
            Route::post('/pending-customer-list', 'getPendingCustomerList');
            Route::post('/updatecustomerstatus/{id}', 'updatePendingCustomerAccounting');
            Route::post('/salesmancustomers', 'accountingSalesmanCustomers');
            Route::post('/allcustomers', 'allCustomers');
            Route::post('/newcustomer', 'storeFromAccounting');
            Route::post('/addCustomerData', 'addCustomerData');
            Route::post('/customer/delete/{customerId}', 'destroy');
        });
        Route::prefix('salesman')->group(function () {
            Route::post('/customers', 'salesmancustomers');
            Route::post('/customerdetails', 'customerDetails');
            Route::post('/debitandpayment', 'debitandpayment');
            Route::post('/getcustomerbycode', 'getcustomerByCode');
            Route::post('/newcustomer', 'store');
            Route::post('/editcustomerinfo/{id}', 'editCustomer');
            Route::post('/updatecustomer/{id}', 'updateCustomer');
        });
    });
});

Route::controller(ItemController::class)->group(function () {
    Route::prefix('item')->group(function () {
        Route::prefix('salesman')->group(function () {
            Route::post('/items', 'itemMap');
            Route::post('getUnitWithPrice', 'getUnitWithPrice');
            Route::post('/itemdetails', 'getItemDetails');
        });
        Route::prefix('accounting')->group(function () {
            Route::post('/items', 'index');
            Route::post('/item', 'finalItem');
            Route::post('/filterbybrand', 'searchbybrand');
            Route::post('/itemdetails', 'getItemDetails');
            Route::post('/scrap-slip', 'scrapSlip');
            Route::post('/whouse-transfer-notic-slip', 'wHouseTransferNoticSlip');
            Route::post('/notic-of-use-slip', 'noticOfUseSlip');
            Route::post('/beginning-balance-note-slip', 'beginningBalanceNoteSlip');
            Route::post('/inventory-excess-voucher-slip', 'inventoryExcessVoucherSlip');
            Route::post('/inventory-deficiency-voucher-slip', 'inventoryDeficiencyVoucherSlip');
        });
    });
});

Route::controller(ItemDefController::class)->group(function () {
    Route::prefix('itemdef')->group(function () {
        Route::prefix('salesman')->group(function () {
            Route::post('/category', 'categories');
            Route::post('/subcategory', 'subcategories');
            Route::post('/test', 'itemMap');
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
            Route::post('/store', 'store');
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
            ->group(function () {
                Route::prefix('accounting')->group(function () {
                    Route::post('/orders', 'index');
                    Route::post('/previousorders', 'previousorders');
                    Route::post('/bystatus', 'ordersStatusFilter');
                    Route::post('/bydate', 'OrderDateFilter');
                    Route::post('/orderdetails', 'orderdetails');
                    Route::post('/previousorderdetails', 'previousorderdetails');
                    Route::post('/orders/accept/{orderId}', 'acceptOrder');
                    Route::post('/orders/reject/{orderId}', 'rejectOrder');
                    Route::post('/orders/update/{orderId}', 'update');
                    Route::post('/orders/delete/{orderId}', 'destroy');
                    Route::post('/orders/{orderId}', 'getOrderToBill');
                    // Route::post('/customercurrentorder', 'customerCurrentOrder');
                });
                Route::prefix('salesman')
                    ->group(function () {
                        Route::post('/previousorder', 'salesmanlacurrentmonthorder');
                        Route::post('/previousorderdetails', 'previousorderdetails');
                        Route::post('/neworder', 'store');
                        Route::post('/paper-limit', 'increasePaper');
                    });
            });
    });

Route::controller(InvoiceController::class)
    ->group(function () {
        Route::prefix('invoice')
            ->group(function () {
                Route::prefix('accounting')->group(function () {
                    Route::post('/invoices', 'InvoicesList');
                    Route::post('/invoicedetails', 'accountingSalesmanInvoiceDetails');

                    Route::post('/newsalesinvoice', 'doSalesInvoice');

                    Route::post('/salesmaninvoices', 'salesmaninvoices');
                    Route::post('/salesinvoicedetails', 'salesinvoicedetails');

                    Route::post('/previoussalesreturninvoices', 'customerprevioussalesreturninvoices');
                    Route::post('/customerprevioussalesinvoices', 'customerprevioussalesinvoices');
                    Route::post('/searchinvoicebydate', 'searchreturnedinvoicebydate');
                    Route::post('/searchinvoicebynumber', 'searchinvoicebynumber');
                    Route::post('/salesreturninvoice', 'salesReturnInvoicesList');
                    Route::post('/salesreturninvoicedetails', 'salesReturnInvoiceDetails');
                    // Route::post('/newpurchaseinvoice', 'doPurchaseInvoice');
                    // Route::post('/purchaseinvoicedetails', 'purchaseinvoicedetails');
                    // Route::post('/purchasereturninvoices', 'purchaseReturnInvoicesList');
                    // Route::post('/purchasereturninvoicesdetails', 'purchaseReturnInvoicesDetails');
                    // Route::post('/purchasedservicesinvoice', 'purchaseServicesInvoicesList');
                    // Route::post('/purchasedservicesinvoicedetails', 'purchasedServicesInvoicedetails');
                });
                Route::prefix('salesman')
                    ->group(function () {
                        Route::post('/previousinvoices', 'salesmanLastTwoMonthsInvoices');
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
        Route::post('/whlist', 'wHouseList');
        Route::post('/ware-house', 'wHouse');
    });
});

Route::controller(SafeController::class)
    ->group(function () {
        Route::prefix('safes')
            ->group(function () {
                Route::prefix('accounting')->group(function () {
                    Route::post('/safes', 'index');
                    Route::post('/store', 'store');
                    Route::post('/edit/{safeID}', 'edit');
                    Route::post('/update/{safeID}', 'update');
                    Route::post('/delete/{safeID}', 'destroy');
                    Route::post('/addingsafedata', 'addSafeData');
                    Route::post('/safesinformation/{safe_code}', 'safesInformation');
                    Route::post('/safetransaction', 'accountingsalesmanSafeTransaction');
                    Route::post('/generalsafetransaction', 'accountingSafeTransaction');
                    Route::post('/transactiondetails/{id}', 'fetchTransactionDetails');
                });
                Route::prefix('salesman')
                    ->group(function () {
                        Route::post('/safetransaction', 'salesmanSafeTransaction');
                        Route::post('/collection/{id}', 'collectionDetails');
                    });
            });
    });

Route::controller(CollectionController::class)
    ->group(function () {
        Route::prefix('collection')
            ->group(function () {
                Route::prefix('accounting')->group(function () {
                    Route::post('/addtransactiondata', 'addTransactionData');
                    Route::post('/transferofreceivables', 'transferFromSalesmanToAccountant');
                    Route::post('/newcurrentaccountcollection', 'currentAccountCollections');
                    Route::post('/newcurrentaccountpayment', 'currentAccountPayment');
                });
                Route::prefix('salesman')
                    ->group(function () {
                        Route::post('/newcurrentaccountcollection', 'currentAccountCollections');
                        Route::post('/makeinvoice', 'cashVan');
                    });
            });
    });

Route::controller(CurrencyController::class)->group(function () {
    Route::prefix('currencies')->group(function () {
        Route::prefix('accounting')->group(function () {
            Route::post('/currencylist', 'index');
        });
    });
});

Route::controller(UnitController::class)->group(function () {
    Route::prefix('units')->group(function () {
        Route::prefix('salesman')->group(function () {
            Route::post('/unitslist', 'itemUnit');
        });
    });
});

Route::controller(AuthController::class)->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', 'login');
        Route::get('check', 'check');
        Route::post('salesManLogin', 'salesManLogin');
    });
});
