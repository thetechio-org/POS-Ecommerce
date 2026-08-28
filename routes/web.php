<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\PurchaseController ;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\SalesPaymentController;
use App\Http\Controllers\SalesDiscountTaxController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\POSController;
use App\Http\Middleware\CheckOpenRegister;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\DiscountRuleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PurchaseReturnController;

// Store
use App\Http\Controllers\Frontend\StoreController;
use App\Http\Controllers\Frontend\CartController;
use App\Http\Controllers\Frontend\CustomerAuth\AuthenticatedSessionController as CustomerAuthenticatedSessionController;
use App\Http\Controllers\Frontend\CustomerAuth\RegisteredUserController as CustomerRegisteredUserController;
use App\Http\Controllers\Frontend\CustomerAuth\CustomerPasswordResetController;
use App\Http\Controllers\Frontend\CheckoutController; // New controller for checkout process
use App\Http\Controllers\Frontend\CustomerProfileController; 

// Route::get('/', function () {
//     return view('welcome');
// });

// Route::get('/dashboard', function () {
//     $title = "POS - Dashboard"; 
//     return view('dashboard', compact('title'));
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::get('/dashboard', [POSController::class, 'dashboard'])->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth', 'verified')->group(function () {
    
    Route::get('/dashboard', [POSController::class, 'dashboard'])->name('dashboard');

    // Users — Admin only
    Route::middleware('role:Admin')->group(function () {
        Route::get('/user/list', [UserController::class, 'index'])->name('user.list');
        Route::get('/user/create', [UserController::class, 'create'])->name('user.create');
        Route::post('/user/store', [UserController::class, 'store'])->name('user.store');
        Route::get('/user/edit/{id}', [UserController::class, 'edit'])->name('user.edit');
        Route::post('/user/edit/{id}', [UserController::class, 'update'])->name('user.update');
        Route::delete('/user/delete/{id}', [UserController::class, 'destroy'])->name('user.delete');
    });



    // Role — Admin only
    Route::middleware('role:Admin')->group(function () {
        Route::get('/role/list', [RoleController::class, 'index'])->name('role.list');
        Route::get('/role/create', [RoleController::class, 'create'])->name('role.create');
        Route::post('/role/store', [RoleController::class, 'store'])->name('role.store');
        Route::get('/role/edit/{id}', [RoleController::class, 'edit'])->name('role.edit');
        Route::post('/role/edit/{id}', [RoleController::class, 'update'])->name('role.update');
        Route::delete('/role/delete/{id}', [RoleController::class, 'destroy'])->name('role.delete');
    });



    // Warehouse
    Route::prefix('warehouse')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->name('warehouse.list');
        Route::get('/create', [WarehouseController::class, 'create'])->name('warehouse.create');
        Route::post('/store', [WarehouseController::class, 'store'])->name('warehouse.store');
        Route::get('/edit/{id}', [WarehouseController::class, 'edit'])->name('warehouse.edit');
        Route::put('/update/{id}', [WarehouseController::class, 'update'])->name('warehouse.update');
        Route::delete('/delete/{id}', [WarehouseController::class, 'destroy'])->name('warehouse.destroy');
    });



    //Branch
    Route::prefix('branch')->name('branch.')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->name('list');
        Route::get('/create', [BranchController::class, 'create'])->name('create');
        Route::post('/store', [BranchController::class, 'store'])->name('store');
        Route::get('/edit/{id}', [BranchController::class, 'edit'])->name('edit');
        Route::post('/update/{id}', [BranchController::class, 'update'])->name('update');
        Route::delete('/delete/{id}', [BranchController::class, 'destroy'])->name('delete');
    });



    //Units
    Route::prefix('units')->name('units.')->group(function () {
        Route::get('/', [UnitController::class, 'index'])->name('list');
        Route::get('/create', [UnitController::class, 'create'])->name('create');
        Route::post('/store', [UnitController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [UnitController::class, 'edit'])->name('edit');
        Route::post('/{id}', [UnitController::class, 'update'])->name('update');
        Route::delete('/{id}', [UnitController::class, 'destroy'])->name('destroy');
    });



    //Categories
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('list');
        Route::get('/create', [CategoryController::class, 'create'])->name('create');
        Route::post('/store', [CategoryController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [CategoryController::class, 'edit'])->name('edit');
        Route::post('/{id}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('destroy');
    });



    //Products
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('list');
        Route::get('/create', [ProductController::class, 'create'])->name('create');
        Route::post('/store', [ProductController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [ProductController::class, 'edit'])->name('edit');
        Route::post('/{id}', [ProductController::class, 'update'])->name('update');
        Route::delete('/{id}', [ProductController::class, 'destroy'])->name('destroy');
        Route::get('/{id}/variants', [ProductController::class, 'viewVariants'])->name('variants');
    });
    Route::get('/api/search-products', [ProductController::class, 'search']);




    //Suppliers
    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('list');
        Route::get('/create', [SupplierController::class, 'create'])->name('create');
        Route::post('/store', [SupplierController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [SupplierController::class, 'edit'])->name('edit');
        Route::post('/{id}', [SupplierController::class, 'update'])->name('update');
        Route::delete('/{id}', [SupplierController::class, 'destroy'])->name('destroy');
    });
    // Supplier Product
    Route::get('/reports/supplier-products', [StockAdjustmentController::class, 'supplierProductReport'])->name('reports.supplier_products');


    //Customers
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('list');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/store', [CustomerController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::post('/{id}', [CustomerController::class, 'update'])->name('update');
        Route::delete('/{id}', [CustomerController::class, 'destroy'])->name('destroy');

        Route::get('/card/{id}', [CustomerController::class, 'showCard'])->name('card');

    });



    //Purchases
    Route::prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])->name('list');
        Route::get('/create', [PurchaseController::class, 'create'])->name('create');
        Route::post('/store', [PurchaseController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [PurchaseController::class, 'edit'])->name('edit');
        Route::post('/{id}', [PurchaseController::class, 'update'])->name('update');
        Route::delete('/{id}', [PurchaseController::class, 'destroy'])->name('destroy');
        // Purchase Items View
        // Route::get('/purchase/items/{id}', [PurchaseController::class, 'showItems'])->name('items');
        Route::get('/invoice/{id}', [PurchaseController::class, 'invoice'])->name('invoice');
        Route::get('/{id}/pdf', [PurchaseController::class, 'downloadPdf'])->name('pdf');

    });

    // Purchase Returns
    Route::prefix('purchase-returns')->name('purchase_returns.')->group(function () {
        Route::get('/', [PurchaseReturnController::class, 'index'])->name('index');
        Route::get('/purchases/{purchase}/return', [PurchaseReturnController::class, 'create'])->name('create');
        Route::post('/purchases/{purchase}', [PurchaseReturnController::class, 'store'])->name('store');
        Route::get('/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('show');
    });


    //Sales
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [SaleController::class, 'index'])->name('list');
        Route::get('/create', [SaleController::class, 'create'])->name('create');
        Route::post('/checkout', [SaleController::class, 'process'])->name('checkout.process');
        Route::delete('/{id}', [SaleController::class, 'destroy'])->name('destroy');
        // Purchase Items View
        // Route::get('/purchase/items/{id}', [SaleController::class, 'showItems'])->name('items');
        Route::get('/invoice/{id}', [SaleController::class, 'invoice'])->name('invoice');
        Route::get('/{id}/pdf', [SaleController::class, 'downloadPdf'])->name('pdf');

    });
    // E-commerce orders — Admin and Manager only
    Route::middleware('role:Admin,Manager')->group(function () {
        Route::get('e-commerce/orders', [SaleController::class, 'orders'])->name('orders.index');
        Route::get('orders/{order}', [SaleController::class, 'show'])->name('orders.show');
        Route::put('orders/{order}/status', [SaleController::class, 'updateStatus'])->name('orders.updateStatus');
    });


    //Sales Return
    Route::prefix('sale_return')->name('sale_return.')->group(function () {
        Route::get('/', [SalesReturnController::class, 'list'])->name('list');
        Route::get('/create/{sale}', [SalesReturnController::class, 'create'])->name('create');
        Route::post('/{sale}/store', [SalesReturnController::class, 'store'])->name('store');
        Route::delete('/{id}', [SalesReturnController::class, 'destroy'])->name('destroy');

        Route::get('/details/{id}', [SalesReturnController::class, 'details'])->name('details');
        Route::get('/{sales_return}', [SalesReturnController::class, 'show'])->name('show');
    });



    //Payments
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('list');
        Route::get('/create', [FinanceController::class, 'create'])->name('create');
        Route::post('/store', [FinanceController::class, 'store'])->name('store');
        Route::delete('/{id}', [FinanceController::class, 'destroy'])->name('destroy');

        Route::get('/invoice/{id}', [SaleController::class, 'invoice'])->name('invoice');
    });


    // Stocks
    Route::get('/stock/list', [StockAdjustmentController::class, 'stockIndex'])->name('stock.list');
    Route::get('/stock-ledger', [StockAdjustmentController::class, 'stockLedger'])->name('stock.ledger');
    Route::get('/stock/adjustment/create', [StockAdjustmentController::class, 'adjustmentCreate'])->name('stock.adjustment.create');
    Route::post('/stock/adjustment', [StockAdjustmentController::class, 'adjustmentStore'])->name('stock.adjustment.store');

    // Stock Transfers
    Route::prefix('stock/transfers')->name('stock.transfers.')->group(function () {
        Route::get('/', [StockTransferController::class, 'index'])->name('index');
        Route::get('/create', [StockTransferController::class, 'create'])->name('create');
        Route::post('/', [StockTransferController::class, 'store'])->name('store');
    });


    // POS
    Route::middleware(['auth', CheckOpenRegister::class])->group(function () {
        Route::get('/pos', [SaleController::class, 'pos'])->name('pos.index');
        Route::post('/pos/checkout', [SaleController::class, 'posProcess'])->name('checkout.pos');
    });



    // Expense Category
    Route::get('/expense_categories/list', [ExpenseController::class, 'index'])->name('expense_categories.list');
    Route::post('/expense_categories/store', [ExpenseController::class, 'store'])->name('expense_categories.store');
    Route::put('/expense_categories/{id}', [ExpenseController::class, 'update'])->name('expense_categories.update');
    Route::delete('/expense_categories/{id}', [ExpenseController::class, 'destroy'])->name('expense_categories.destroy');



    // Expense
    Route::get('/expense/list', [ExpenseController::class, 'list'])->name('expense.list');
    Route::get('/expense/create', [ExpenseController::class, 'expenseCreate'])->name('expense.create');
    Route::post('/expense/store', [ExpenseController::class, 'expenseStore'])->name('expense.store');
    Route::get('/expenses/edit/{id}', [ExpenseController::class, 'expenseEdit'])->name('expense.edit');
    Route::put('/expenses/update/{id}', [ExpenseController::class, 'expenseUpdate'])->name('expense.update');
    Route::delete('/expenses/delete/{id}', [ExpenseController::class, 'expenseDestroy'])->name('expense.destroy');
    


    //Cash Register
    Route::post('/pos/open-register', [POSController::class, 'openRegister'])->name('pos.openRegister');
    Route::post('/pos/close-register', [POSController::class, 'closeRegister'])->name('pos.closeRegister');
    Route::get('/pos/check-register', [POSController::class, 'checkRegister'])->name('pos.checkRegister');
    Route::get('/pos/register-details', [POSController::class, 'getRegisterDetails'])->name('pos.getRegisterDetails');

    // POS Settings — Admin only
    Route::middleware('role:Admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings/save', [SettingController::class, 'saveSettings'])->name('settings.save');
        Route::post('/mail-settings/save', [SettingController::class, 'saveMailSettings'])->name('mail-settings.save');
    });



    // Quotation
    Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
    Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
    Route::get('/quotations/show/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
    Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
    Route::delete('/quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');
    Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'downloadPdf'])->name('quotations.pdf');
    Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convertToSale'])->name('quotations.convert');


    // Route::post('/quotations/{quotation}/restore', [QuotationController::class, 'restore'])->name('quotations.restore');
    // Route::delete('/quotations/{quotation}/forceDelete', [QuotationController::class, 'forceDelete'])->name('quotations.forceDelete');



    // Prodfile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    // Reports — Admin, Manager, Accountant only
    Route::middleware('role:Admin,Manager,Accountant')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('/purchases', [ReportController::class, 'purchases'])->name('purchases');
        Route::get('/expenses', [ReportController::class, 'expenses'])->name('expenses');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/inventory-valuation', [ReportController::class, 'inventoryValuation'])->name('inventory-valuation');
    });


    // Discount Rules
    Route::get('discount-rules', [DiscountRuleController::class, 'index'])->name('discount_rules.index');
    Route::get('discount-rules/create', [DiscountRuleController::class, 'create'])->name('discount_rules.create');
    Route::post('discount-rules', [DiscountRuleController::class, 'store'])->name('discount_rules.store');
    Route::get('discount-rules/{id}/edit', [DiscountRuleController::class, 'edit'])->name('discount_rules.edit');
    Route::put('discount-rules/{id}', [DiscountRuleController::class, 'update'])->name('discount_rules.update');
    Route::delete('discount-rules/{id}', [DiscountRuleController::class, 'destroy'])->name('discount_rules.destroy');
});


// Store Frontend
// The shop is the public face of the site, so it owns "/". Everything else stays
// under /store/* — dropping the prefix entirely would collide with the admin
// routes for /profile, /orders and the password-reset paths.
Route::get('/', [StoreController::class, 'landing'])->name('store.landing');
Route::redirect('/store', '/');

Route::prefix('store')->group(function () {
    Route::get('/shop', [StoreController::class, 'shop'])->name('store.shop');
    Route::get('/product/{id}', [StoreController::class, 'product'])->name('store.product');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::get('/cart', [CartController::class, 'view'])->name('cart.view');
    Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');


    Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon'])->name('cart.apply_coupon');
    Route::post('/cart/remove-coupon', [CartController::class, 'removeCoupon'])->name('cart.remove_coupon');

    // Customer Authentication for Store
    Route::get('/login', [CustomerAuthenticatedSessionController::class, 'create'])->name('customer.login');
    Route::post('/login', [CustomerAuthenticatedSessionController::class, 'store']);
    Route::post('/logout', [CustomerAuthenticatedSessionController::class, 'destroy'])->name('customer.logout');

    Route::get('/register', [CustomerRegisteredUserController::class, 'create'])->name('customer.register');
    Route::post('/register', [CustomerRegisteredUserController::class, 'store']);

    // Customer Password Reset
    Route::get('/forgot-password', [CustomerPasswordResetController::class, 'create'])->name('customer.password.request');
    Route::post('/forgot-password', [CustomerPasswordResetController::class, 'store'])->name('customer.password.email');
    Route::get('/reset-password/{token}', [CustomerPasswordResetController::class, 'resetForm'])->name('customer.password.reset');
    Route::post('/reset-password', [CustomerPasswordResetController::class, 'resetPassword'])->name('customer.password.update');

    Route::post('/product-variant', [StoreController::class, 'getProductVariant'])->name('product.getVariant');

    // Protected routes for authenticated customers
    Route::middleware('auth:customer')->group(function () {
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('store.checkout');
        Route::post('/checkout/process', [CheckoutController::class, 'process'])->name('store.checkout.process');
        Route::get('/thank-you', [CheckoutController::class, 'thankYou'])->name('store.thankyou');

        // Order History
        Route::get('/orders', [CheckoutController::class, 'orders'])->name('store.orders');
        Route::get('/orders/{id}', [CheckoutController::class, 'orderDetail'])->name('store.order.detail');

        // Customer Profile
        Route::get('/profile', [CustomerProfileController::class, 'edit'])->name('customer.profile.edit');
        Route::put('/profile', [CustomerProfileController::class, 'update'])->name('customer.profile.update');
        Route::delete('/profile', [CustomerProfileController::class, 'destroy'])->name('customer.profile.destroy');

        // Payment Gateway — Redirect (authenticated customer)
        Route::get('/payment/jazzcash/{sale}', [PaymentGatewayController::class, 'redirectJazzCash'])->name('payment.jazzcash');
        Route::get('/payment/easypaisa/{sale}', [PaymentGatewayController::class, 'redirectEasyPaisa'])->name('payment.easypaisa');
    });

    // Payment Gateway Callbacks (public — posted by the gateway)
    // CSRF is excluded for these paths in bootstrap/app.php — the gateway's
    // signature is verified inside the controller instead.
    Route::post('/payment/jazzcash/callback', [PaymentGatewayController::class, 'callbackJazzCash'])->name('payment.jazzcash.callback');
    Route::post('/payment/easypaisa/callback', [PaymentGatewayController::class, 'callbackEasyPaisa'])->name('payment.easypaisa.callback');
});





require __DIR__.'/auth.php';
