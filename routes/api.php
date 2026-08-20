<?php

use App\Http\Controllers\Admin\RefundAdminController;
use App\Http\Controllers\Admin\WithdrawalAdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\Customer\CustomerBankAccountController;
use App\Http\Controllers\Api\Customer\CustomerRefundController;
use App\Http\Controllers\Api\Customer\CustomerTransactionController;
use App\Http\Controllers\Api\GeneralContoller;
use App\Http\Controllers\Api\Marketplace\MarketplaceBankAccountController;
use App\Http\Controllers\Api\Marketplace\MarketplacePaymentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderCancellationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Provider\ProviderBankAccountController;
use App\Http\Controllers\Api\TapWebhookController;
use App\Http\Controllers\Api\User\HiringController;
use App\Http\Controllers\Api\User\OrdersController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\TermsConditionController;
use Illuminate\Support\Facades\Route;

// ===================================================================Public Routes Start===================================================================
Route::prefix('v1')->group(function () {
    Route::post('check-phone-availability', [AuthController::class, 'check_phone_availability']);
    Route::post('check-phone-registered', [AuthController::class, 'check_phone_registered']);
    Route::post('send-otp', [AuthController::class, 'send_otp']);
    Route::post('verify-otp', [AuthController::class, 'verify_otp']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('become-provider', [AuthController::class, 'becomeProvider']);
    Route::post('become-seller', [AuthController::class, 'becomeSeller']);
    Route::post('login', [AuthController::class, 'login']);

    Route::get('system-settings', [GeneralContoller::class, 'system_settings']);
    Route::get('app-version', [GeneralContoller::class, 'app_version']);
    Route::get('user-home', [GeneralContoller::class, 'user_home']);
    Route::get('view-all-services', [GeneralContoller::class, 'view_all_services']);
    Route::get('view-all-providers', [GeneralContoller::class, 'view_all_providers']);
    Route::get('view-all-providers-data', [GeneralContoller::class, 'view_all_providers_data']);
    Route::get('get-providers-by-service/{id}', [GeneralContoller::class, 'get_providers_by_service']);
    Route::get('providers-details/{id}', [GeneralContoller::class, 'get_providers_details']);
    Route::get('autocomplete-search', [GeneralContoller::class, 'autocomplete_search']);
    Route::post('search', [GeneralContoller::class, 'search']);
    Route::get('cities', [GeneralContoller::class, 'cities']);
    Route::get('faqs', [GeneralContoller::class, 'faqs_list']);
    Route::get('support-items', [GeneralContoller::class, 'support_list']);
    Route::get('/marketplace/active-campaigns', [CampaignController::class, 'activeCampaigns']);
    Route::post('/marketplace/store-visit', [AuthController::class, 'recordMarketplaceStoreVisit']);
    Route::post('/marketplace/product-view', [AuthController::class, 'recordProductView']);

    Route::get('/privacy/{role}', [PrivacyController::class, 'index']);
    Route::get('/terms-conditions/{role}', [TermsConditionController::class, 'index']);
});


// ===================================================================Public Routes End=====================================================================


// ================================================================== Protected Routes Start================================================================

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post(
        '/bank/iban/verify',
        [BankController::class, 'verifyIban']
    );

    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('active-account-request', [AuthController::class, 'activeAccountRequest']);
    Route::get('profile', [AuthController::class, 'get_profile']);
    Route::post('switch-role', [AuthController::class, 'switchRole']);
    Route::post('delete-user', [AuthController::class, 'deleteUser']);
    Route::post('update-profile', [AuthController::class, 'update_profile']);
    Route::post('update-profession', [AuthController::class, 'update_profession']);
    Route::post('update-fcm', [AuthController::class, 'update_fcm']);
    Route::get('service-request-details/{id}', [HiringController::class, 'service_request_details']);
    Route::get('orders/{id}/receipt', [OrdersController::class, 'getReceipt']);
    Route::get('marketplace/orders/{id}/receipt', [OrdersController::class, 'getMarketplaceReceipt']);

    Route::get('track-order/{id}', [GeneralContoller::class, 'track_order']);
    Route::post('cancel-order/{id}', [GeneralContoller::class, 'cancel_order']);
    Route::middleware('Role:0')->group(function () {
        Route::post('direct-hire', [HiringController::class, 'direct_hire']);
        Route::post('post-service-request', [HiringController::class, 'post_service_request']);
        Route::get('my-service-requests', [HiringController::class, 'my_service_requests']);
        Route::get('view-bids-by-request/{id}', [HiringController::class, 'view_bids_by_request']);
        Route::post('accept-bid/{id}', [HiringController::class, 'accept_bid']);
        Route::get('my-orders', [OrdersController::class, 'my_orders']);
        Route::post('submit-feedback', [OrdersController::class, 'submit_feedback']);
        Route::post('favorite-providers/toggle', [GeneralContoller::class, 'toggle_favorite_provider']);
        Route::get('favorite-providers', [GeneralContoller::class, 'get_favorite_provider_ids']);
        Route::get('get-favorite-providers', [GeneralContoller::class, 'get_favorite_provider']);

        Route::post('favorite-marketplaces/toggle', [GeneralContoller::class, 'toggle_favorite_marketplace']);
        Route::get('favorite-marketplaces', [GeneralContoller::class, 'get_favorite_marketplace_ids']);
        Route::get('get-favorite-marketplaces', [GeneralContoller::class, 'get_favorite_marketplace']);
    });


    // ================================================================== Provider Routes Start==================================================================
    Route::get('view-all-post-requests', [GeneralContoller::class, 'view_all_post_requests']);
    Route::get('job/{id}', [GeneralContoller::class, 'getJobDetail']);
    Route::middleware('Role:1')->group(function () {
        Route::post('submit-bid/{id}', [GeneralContoller::class, 'submit_bid']);
        Route::post('update-order-status/{id}', [GeneralContoller::class, 'update_order_status']);
        Route::get('provider-orders', [GeneralContoller::class, 'provider_orders']);
        Route::get('my-bids', [GeneralContoller::class, 'my_bids']);

        // Provider Bank Account Routes
        Route::get('provider/bank-accounts', [ProviderBankAccountController::class, 'getBankAccounts']);
        Route::get('provider/bank-accounts/{id}', [ProviderBankAccountController::class, 'showBankAccount']);
        Route::post('provider/save-bank-account', [ProviderBankAccountController::class, 'saveBankAccount']);
        Route::post('provider/bank-accounts', [ProviderBankAccountController::class, 'saveBankAccount']);
        Route::post('provider/bank-accounts/{id}/update', [ProviderBankAccountController::class, 'updateBankAccount']);
        Route::put('provider/bank-accounts/{id}', [ProviderBankAccountController::class, 'updateBankAccount']);
    });
    // ================================================================== Provider Routes End====================================================================

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('notifications', [NotificationController::class, 'clearAll']);

    // Tap Payment Gateway Routes
    Route::post('jobs/{job}/bids/{bid}/initiate-payment', [PaymentController::class, 'initiatePayment']);
    Route::post('marketplace/orders/{order}/initiate-payment', [MarketplacePaymentController::class, 'initiatePayment']);
    Route::post('payments/charge', [PaymentController::class, 'charge']);
    Route::get('payments/{payment}/status', [PaymentController::class, 'status']);
    Route::get('payments/callback', [PaymentController::class, 'callback']);

    // Marketplace Bank Account Routes
    Route::get('marketplace/bank-accounts', [MarketplaceBankAccountController::class, 'getBankAccounts']);
    Route::get('marketplace/bank-accounts/{id}', [MarketplaceBankAccountController::class, 'showBankAccount']);
    Route::post('marketplace/save-bank-account', [MarketplaceBankAccountController::class, 'saveBankAccount']);
    Route::post('marketplace/bank-accounts', [MarketplaceBankAccountController::class, 'saveBankAccount']);
    Route::post('marketplace/bank-accounts/{id}/update', [MarketplaceBankAccountController::class, 'updateBankAccount']);
    Route::put('marketplace/bank-accounts/{id}', [MarketplaceBankAccountController::class, 'updateBankAccount']);

    // Withdrawal & Transaction History Routes (Document Specs Page 18)
    Route::post('withdrawals/request', [WithdrawalController::class, 'requestWithdrawal']);
    Route::post('withdrawals', [WithdrawalController::class, 'requestWithdrawal']);
    Route::get('transactions', [WithdrawalController::class, 'transactionHistory']);

    // Provider Wallet & Withdrawal Specification Routes
    Route::get('provider/wallet', [WithdrawalController::class, 'walletSummary']);
    Route::get('provider/wallet/summary', [WithdrawalController::class, 'walletSummary']);
    Route::get('provider/wallet/transactions', [WithdrawalController::class, 'transactionHistory']);
    Route::post('provider/withdrawals', [WithdrawalController::class, 'requestWithdrawal']);
    Route::get('provider/withdrawals', [WithdrawalController::class, 'transactionHistory']);

    // Marketplace Wallet & Withdrawal Specification Routes
    Route::get('marketplace/wallet/summary', [WithdrawalController::class, 'walletSummary']);
    Route::get('marketplace/wallet/transactions', [WithdrawalController::class, 'transactionHistory']);
    Route::post('marketplace/withdrawals', [WithdrawalController::class, 'requestWithdrawal']);

    // Admin Withdrawal Action APIs (Spec Doc Page 13 & 18)
    Route::get('admin/withdrawals', [WithdrawalAdminController::class, 'index']);
    Route::match(['patch', 'post'], 'admin/withdrawals/{id}/accept', [WithdrawalAdminController::class, 'accept']);
    Route::match(['patch', 'post'], 'admin/withdrawals/{id}/complete', [WithdrawalAdminController::class, 'complete']);
    Route::match(['patch', 'post'], 'admin/withdrawals/{id}/reject', [WithdrawalAdminController::class, 'reject']);

    // Order Cancellation & Refund Specification Routes
    Route::post('orders/{order_id}/cancel', [OrderCancellationController::class, 'cancelOrder']);

    // Customer Bank Account Routes (Max 3 Limit)
    Route::get('customer/bank-accounts', [CustomerBankAccountController::class, 'getBankAccounts']);
    Route::post('customer/bank-accounts', [CustomerBankAccountController::class, 'saveBankAccount']);
    Route::post('customer/save-bank-account', [CustomerBankAccountController::class, 'saveBankAccount']);
    Route::put('customer/bank-accounts/{id}', [CustomerBankAccountController::class, 'updateBankAccount']);
    Route::post('customer/bank-accounts/{id}/update', [CustomerBankAccountController::class, 'updateBankAccount']);
    Route::delete('customer/bank-accounts/{id}', [CustomerBankAccountController::class, 'deleteBankAccount']);
    Route::post('customer/bank-accounts/{id}/delete', [CustomerBankAccountController::class, 'deleteBankAccount']);

    // Customer Transaction History & Wallet Summary Routes
    Route::get('customer/transactions', [CustomerTransactionController::class, 'transactionHistory']);
    Route::get('customer/wallet/transactions', [CustomerTransactionController::class, 'transactionHistory']);

    // Dedicated Standalone Customer Refund Request APIs (After Order Cancellation)
    Route::post('customer/refunds/request', [CustomerRefundController::class, 'requestRefund']);
    Route::post('orders/{order_id}/refund', [CustomerRefundController::class, 'requestRefund']);
    Route::post('refunds/request', [CustomerRefundController::class, 'requestRefund']);

    // Admin Refund Action APIs (Spec Doc Page 6)
    Route::get('admin/refunds', [RefundAdminController::class, 'index']);
    Route::match(['patch', 'post'], 'admin/refunds/{id}/accept', [RefundAdminController::class, 'accept']);
    Route::match(['patch', 'post'], 'admin/refunds/{id}/complete', [RefundAdminController::class, 'complete']);
    Route::match(['patch', 'post'], 'admin/refunds/{id}/reject', [RefundAdminController::class, 'reject']);
});

// Public Tap Webhook Routes (Both root and v1)
Route::post('webhooks/tap', [TapWebhookController::class, 'handleWebhook']);
Route::post('v1/webhooks/tap', [TapWebhookController::class, 'handleWebhook']);

// Root level aliases for payment endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::post('jobs/{job}/bids/{bid}/initiate-payment', [PaymentController::class, 'initiatePayment']);
    Route::post('payments/charge', [PaymentController::class, 'charge']);
    Route::get('payments/{payment}/status', [PaymentController::class, 'status']);
});

// ================================================================== Protected Routes End==================================================================
