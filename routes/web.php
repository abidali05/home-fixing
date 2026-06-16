<?php

use App\Http\Controllers\Admin\AppVersionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\JobsController;
use App\Http\Controllers\Admin\MarketplaceCampaignController;
use App\Http\Controllers\Admin\MarketplaceOrderController;
use App\Http\Controllers\Admin\MarketplaceProductController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PrivacyController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\SupportItemController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\SystemUserController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\AccountActiveRequestController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('dashboard');
});

Route::get('/clear-cache', function () {
    try {
        Artisan::call('optimize:clear');

        return response()->json([
            'status' => true,
            'message' => 'Cache cleared successfully'
        ]);
    } catch (\Throwable $e) {
        Log::error('Clear cache error: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::get('/run-queue-once', function () {
    try {
        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'notifications',
            '--tries' => 1,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Notifications queue executed once',
            'output' => Artisan::output(),
        ]);
    } catch (\Throwable $e) {
        Log::error('Queue run error: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::get('/fix-storage', function () {

    $publicStorage = public_path('storage');
    $realStorage = storage_path('app/public');

    // Function to delete a directory recursively
    function deleteDirectory($dir) {
        if (!file_exists($dir)) return;
        if (!is_dir($dir)) return unlink($dir);

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    // 1. If symlink/folder missing → create new
    if (!file_exists($publicStorage)) {
        Artisan::call('storage:link');
        return "✔ Storage link created (was missing).";
    }

    // 2. If exists BUT not a symlink → delete directory then create link
    if (!is_link($publicStorage)) {
        deleteDirectory($publicStorage);
        Artisan::call('storage:link');
        return "✔ Storage folder was not symlink → fixed by recreating!";
    }

    // 3. If symlink exists but points to wrong location → fix it
    if (realpath($publicStorage) !== realpath($realStorage)) {
        unlink($publicStorage);
        Artisan::call('storage:link');
        return "✔ Storage symlink incorrect → recreated successfully!";
    }

    return "✔ Storage link already correct.";
});

Route::get('/unauthorized', function () {
    return view('unauthorized');
})->name('unauthorized');
Route::post('send-otp', [LoginController::class, 'sendOtp'])->name('send.otp');
Route::post('verify-otp', [LoginController::class, 'verifyOtp'])->name('verify.otp');


Route::post('check-email-availability', [SystemUserController::class, 'checkEmailAvailability'])->name('system_users.check_email');
Route::post('check-phone-availability', [SystemUserController::class, 'checkPhoneAvailability'])->name('system_users.check_phone');



Route::middleware(['auth:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/chats', function () {
        return view('admin.chats.index');
    })->name('chats.index');

    Route::prefix('admin')->name('admin.')->middleware(['auth:admin'])->group(function () {
        Route::resource('privacy', PrivacyController::class);
        Route::get('app-versions', [AppVersionController::class, 'index'])->name('app_versions.index');
        Route::post('app-versions/save', [AppVersionController::class, 'save'])->name('app_versions.save');
    });


    // =====================================================================Service Category Start===============================================================================

    Route::get('service-category', [ServiceCategoryController::class, 'index'])->name('servicecategory.index')->middleware('CheckPermission:1');
    Route::get('service-category/create', [ServiceCategoryController::class, 'create'])->name('servicecategory.create')->middleware('CheckPermission:2');
    Route::post('service-category/store', [ServiceCategoryController::class, 'store'])->name('servicecategory.store')->middleware('CheckPermission:2');
    Route::get('service-category/edit/{id}', [ServiceCategoryController::class, 'edit'])->name('servicecategory.edit')->middleware('CheckPermission:3');
    Route::post('service-category/update/{id}', [ServiceCategoryController::class, 'update'])->name('servicecategory.update')->middleware('CheckPermission:3');
    Route::delete('service-category/delete/{id}', [ServiceCategoryController::class, 'destroy'])->name('servicecategory.delete')->middleware('CheckPermission:4');

    // =====================================================================Service Category End===============================================================================







    // =====================================================================System Settings Start===============================================================================
    Route::get('settings', [SystemSettingController::class, 'index'])->name('settings.index')->middleware('CheckPermission:5');
    Route::post('settings/update', [SystemSettingController::class, 'update'])->name('settings.update')->middleware('CheckPermission:5');
    Route::post('mobile_banners/update', [SystemSettingController::class, 'mobile_banners'])->name('mobile_banners.update')->middleware('CheckPermission:5');
    Route::get('mobile-banners/delete/{id}', [SystemSettingController::class, 'delete_mobile_banners'])->name('mobile_banners.delete')->middleware('CheckPermission:5');


    // =====================================================================System Settings Start===============================================================================

    // =====================================================================FAQs & Support Start===============================================================================
    Route::get('faqs', [FaqController::class, 'index'])->name('faqs.index');
    Route::get('faqs/create', [FaqController::class, 'create'])->name('faqs.create');
    Route::post('faqs/store', [FaqController::class, 'store'])->name('faqs.store');
    Route::get('faqs/edit/{id}', [FaqController::class, 'edit'])->name('faqs.edit');
    Route::post('faqs/update/{id}', [FaqController::class, 'update'])->name('faqs.update');
    Route::delete('faqs/delete/{id}', [FaqController::class, 'destroy'])->name('faqs.delete');

    Route::get('support-items', [SupportItemController::class, 'index'])->name('support_items.index');
    Route::get('support-items/create', [SupportItemController::class, 'create'])->name('support_items.create');
    Route::post('support-items/store', [SupportItemController::class, 'store'])->name('support_items.store');
    Route::get('support-items/edit/{id}', [SupportItemController::class, 'edit'])->name('support_items.edit');
    Route::post('support-items/update/{id}', [SupportItemController::class, 'update'])->name('support_items.update');
    Route::delete('support-items/delete/{id}', [SupportItemController::class, 'destroy'])->name('support_items.delete');
    // =====================================================================FAQs & Support End===============================================================================







    // ====================================================================Role and Permission Start===============================================================================
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index')->middleware('CheckPermission:6');
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('CheckPermission:7');
    Route::post('roles/store', [RoleController::class, 'store'])->name('roles.store')->middleware('CheckPermission:7');
    Route::get('roles/edit/{id}', [RoleController::class, 'edit'])->name('roles.edit')->middleware('CheckPermission:8');
    Route::post('roles/update/{id}', [RoleController::class, 'update'])->name('roles.update')->middleware('CheckPermission:8');
    Route::delete('roles/delete/{id}', [RoleController::class, 'destroy'])->name('roles.delete')->middleware('CheckPermission:9');







    // ====================================================================Role and Permission End===============================================================================







    // ====================================================================System Users Start===============================================================================
    Route::get('system-users', [SystemUserController::class, 'index'])->name('system_users.index')->middleware('CheckPermission:10');
    Route::get('system-users/create', [SystemUserController::class, 'create'])->name('system_users.create')->middleware('CheckPermission:11');
    Route::post('system-users/store', [SystemUserController::class, 'store'])->name('system_users.store')->middleware('CheckPermission:11');
    Route::get('system-users/edit/{id}', [SystemUserController::class, 'edit'])->name('system_users.edit')->middleware('CheckPermission:12');
    Route::post('system-users/update/{id}', [SystemUserController::class, 'update'])->name('system_users.update')->middleware('CheckPermission:12');
    Route::delete('system-users/delete/{id}', [SystemUserController::class, 'destroy'])->name('system_users.delete')->middleware('CheckPermission:13');




    // ====================================================================System Users End===============================================================================







    // ====================================================================Users Start===============================================================================

    Route::get('users', [UsersController::class, 'index'])->name('users.index')->middleware('CheckPermission:14');
    Route::get('users/create', [UsersController::class, 'create'])->name('users.create')->middleware('CheckPermission:15');
    Route::post('users/store', [UsersController::class, 'store'])->name('users.store')->middleware('CheckPermission:15');
    Route::get('users/edit/{id}', [UsersController::class, 'edit'])->name('users.edit')->middleware('CheckPermission:16');
    Route::post('users/update/{id}', [UsersController::class, 'update'])->name('users.update')->middleware('CheckPermission:16');
    Route::delete('users/delete/{id}', [UsersController::class, 'destroy'])->name('users.delete')->middleware('CheckPermission:17');

    Route::get('account-active-requests', [AccountActiveRequestController::class, 'index'])->name('account_active_requests.index');
    Route::post('account-active-requests/activate/{id}', [AccountActiveRequestController::class, 'activate'])->name('account_active_requests.activate');

    // ====================================================================Users End===============================================================================






    // ====================================================================Providers Start===============================================================================

    Route::get('providers', [ProviderController::class, 'index'])->name('providers.index')->middleware('CheckPermission:18');
    Route::get('providers/create', [ProviderController::class, 'create'])->name('providers.create')->middleware('CheckPermission:19');
    Route::post('providers/store', [ProviderController::class, 'store'])->name('providers.store')->middleware('CheckPermission:19');
    Route::get('providers/show/{id}', [ProviderController::class, 'show'])->name('providers.show');
    Route::get('providers/edit/{id}', [ProviderController::class, 'edit'])->name('providers.edit')->middleware('CheckPermission:20');
    Route::post('providers/update/{id}', [ProviderController::class, 'update'])->name('providers.update')->middleware('CheckPermission:20');
    Route::post('providers/status/{id}', [ProviderController::class, 'updateStatus'])->name('providers.status');
    Route::delete('providers/delete/{id}', [ProviderController::class, 'destroy'])->name('providers.delete')->middleware('CheckPermission:21');
    Route::get('delete-provider-gallery-image/{id}', [ProviderController::class, 'deleteProviderImage'])->name('providers.deleteProviderImage')->middleware('CheckPermission:20');

    // ====================================================================Providers End===============================================================================

    // ====================================================================Sellers Start===============================================================================
    Route::get('sellers', [SellerController::class, 'index'])->name('sellers.index');
    Route::get('sellers/show/{id}', [SellerController::class, 'show'])->name('sellers.show');
    Route::get('sellers/edit/{id}', [SellerController::class, 'edit'])->name('sellers.edit');
    Route::post('sellers/update/{id}', [SellerController::class, 'update'])->name('sellers.update');
    Route::post('sellers/status/{id}', [SellerController::class, 'updateStatus'])->name('sellers.status');
    // ====================================================================Sellers End===============================================================================

    // ====================================================================Marketplace Start===============================================================================
    Route::prefix('marketplace')->name('marketplace.')->group(function () {
        Route::get('orders', [MarketplaceOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{id}', [MarketplaceOrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{id}', [MarketplaceOrderController::class, 'update'])->name('orders.update');

        Route::get('products', [MarketplaceProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [MarketplaceProductController::class, 'create'])->name('products.create');
        Route::post('products/store', [MarketplaceProductController::class, 'store'])->name('products.store');
        Route::get('products/{id}', [MarketplaceProductController::class, 'show'])->name('products.show');
        Route::get('products/{id}/edit', [MarketplaceProductController::class, 'edit'])->name('products.edit');
        Route::post('products/{id}/update', [MarketplaceProductController::class, 'update'])->name('products.update');
        Route::post('products/{id}/status', [MarketplaceProductController::class, 'updateStatus'])->name('products.status');
        Route::delete('products/{id}', [MarketplaceProductController::class, 'destroy'])->name('products.destroy');

        Route::get('campaigns', [MarketplaceCampaignController::class, 'index'])->name('campaigns.index');
        Route::get('campaigns/create', [MarketplaceCampaignController::class, 'create'])->name('campaigns.create');
        Route::post('campaigns/store', [MarketplaceCampaignController::class, 'store'])->name('campaigns.store');
        Route::get('campaigns/{id}', [MarketplaceCampaignController::class, 'show'])->name('campaigns.show');
        Route::get('campaigns/{id}/edit', [MarketplaceCampaignController::class, 'edit'])->name('campaigns.edit');
        Route::post('campaigns/{id}/update', [MarketplaceCampaignController::class, 'update'])->name('campaigns.update');
        Route::post('campaigns/{id}/status', [MarketplaceCampaignController::class, 'updateStatus'])->name('campaigns.status');
        Route::delete('campaigns/{id}', [MarketplaceCampaignController::class, 'destroy'])->name('campaigns.destroy');
    });
    // ====================================================================Marketplace End===============================================================================






    // ====================================================================Job Request Start===============================================================================

    Route::get('job-requests', [JobsController::class, 'index'])->name('job_requests.index')->middleware('CheckPermission:22');
    Route::get('job-requests/create', [JobsController::class, 'create'])->name('job_requests.create')->middleware('CheckPermission:23');
    Route::post('job-requests/store', [JobsController::class, 'store'])->name('job_requests.store')->middleware('CheckPermission:23');
    Route::get('job-requests/edit/{id}', [JobsController::class, 'edit'])->name('job_requests.edit')->middleware('CheckPermission:24');
    Route::post('job-requests/update/{id}', [JobsController::class, 'update'])->name('job_requests.update')->middleware('CheckPermission:24');
    Route::delete('job-requests/delete/{id}', [JobsController::class, 'destroy'])->name('job_requests.delete')->middleware('CheckPermission:25');
    Route::get('job-requests/details/{id}', [JobsController::class, 'details'])->name('job_requests.details')->middleware('CheckPermission:22');
    Route::get('delete-job-gallery-image/{id}', [JobsController::class, 'deleteJobImage'])->name('job_requests.deleteJobImage')->middleware('CheckPermission:24');
    Route::get('delete-job-gallery-video/{id}', [JobsController::class, 'deleteJobVideo'])->name('job_requests.deleteJobVideo')->middleware('CheckPermission:24');

    // ====================================================================Job Request End===============================================================================






    // ====================================================================Bids Start===============================================================================

    // Route::get('bids', [BidController::class, 'index'])->name('bids.index')->middleware('CheckPermission:26');



    // ====================================================================Bids End===============================================================================




    // ====================================================================Orders Start===============================================================================

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index')->middleware('CheckPermission:26');
    Route::get('orders/details/{id}', [OrderController::class, 'details'])->name('orders.details')->middleware('CheckPermission:26');
});



Route::get('clear-cache', function () {
    Artisan::call('optimize:clear');

    return 'Cache cleared';
})->name('clear.cache');


require __DIR__ . '/auth.php';
