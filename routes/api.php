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
    Route::get('marketplace/active-campaigns', [CampaignController::class, 'activeCampaigns']);
    Route::post('marketplace/store-visit', [AuthController::class, 'recordMarketplaceStoreVisit']);
    Route::post('marketplace/product-view', [AuthController::class, 'recordProductView']);

    Route::get('privacy/{role}', [PrivacyController::class, 'index']);
    Route::get('terms-conditions/{role}', [TermsConditionController::class, 'index']);
});
// ===================================================================Public Routes End=====================================================================


// ================================================================== Protected Routes Start================================================================
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('bank/iban/verify', [BankController::class, 'verifyIban']);

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

    // Customer Role (0) Routes
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
        Route::get('service-requests', [GeneralContoller::class, 'service_requests']);
        Route::get('provider-home', [GeneralContoller::class, 'provider_home']);
        Route::get('view-all-direct-requests', [GeneralContoller::class, 'view_all_direct_requests']);
        Route::post('accept-reject-request', [GeneralContoller::class, 'accept_reject_request']);
        Route::get('provider-orders', [GeneralContoller::class, 'my_orders']);

        Route::post('submit-bid/{id}', [GeneralContoller::class, 'submit_bid']);
        Route::post('post-bid/{id}', [GeneralContoller::class, 'post_bid']);
        Route::get('my-bids', [GeneralContoller::class, 'my_bids']);
        Route::get('provider-reviews', [GeneralContoller::class, 'provider_reviews']);

        // Provider Bank Account & Financial Routes
        Route::post('provider/validate-iban', [ProviderBankAccountController::class, 'validateIban']);
        Route::get('provider/financial-summary', [ProviderBankAccountController::class, 'financialSummary']);
        Route::get('provider/bank-accounts', [ProviderBankAccountController::class, 'getBankAccounts']);
        Route::get('provider/bank-accounts/{id}', [ProviderBankAccountController::class, 'showBankAccount']);
        Route::post('provider/save-bank-account', [ProviderBankAccountController::class, 'saveBankAccount']);
        Route::post('provider/bank-accounts', [ProviderBankAccountController::class, 'saveBankAccount']);
        Route::post('provider/bank-accounts/{id}/update', [ProviderBankAccountController::class, 'updateBankAccount']);
        Route::put('provider/bank-accounts/{id}', [ProviderBankAccountController::class, 'updateBankAccount']);
        Route::delete('provider/bank-accounts/{id}', [ProviderBankAccountController::class, 'deleteBankAccount']);
    });

    Route::post('update-order-status/{id}', [GeneralContoller::class, 'update_order_status']);
    // ================================================================== Provider Routes End====================================================================


    // ================================================================== Marketplace Routes Start===============================================================
    Route::post('marketplace/product/add', [AuthController::class, 'addProduct']);
    Route::get('marketplace/products', [AuthController::class, 'getProducts']);
    Route::get('product/{id}', [AuthController::class, 'getProductDetail']);
    Route::post('marketplace/product/update/{id}', [AuthController::class, 'updateProduct']);
    Route::post('marketplace/post-campaigns', [CampaignController::class, 'store']);
    Route::get('marketplace/get-campaigns', [CampaignController::class, 'index']);
    Route::get('marketplace/get-all', [AuthController::class, 'getAllMarketplace']);
    Route::get('marketplace/get-detail/{id}', [AuthController::class, 'getMarketplaceDetail']);
    Route::get('marketplace/dashboard', [AuthController::class, 'marketplaceDashboard']);
    Route::post('add-to-cart', [AuthController::class, 'addToCart']);
    Route::get('cart', [AuthController::class, 'getCart']);
    Route::post('cart/update-quantity', [AuthController::class, 'updateCartQuantity']);
    Route::post('cart/clear', [AuthController::class, 'clearCart']);
    Route::post('marketplace/checkout', [AuthController::class, 'checkout']);
    Route::get('customer-orders', [AuthController::class, 'customerOrders']);
    Route::get('customer-orders/{id}', [AuthController::class, 'customerOrderDetail']);
    Route::post('customer-orders/{id}/delivery-response', [AuthController::class, 'updateCustomerDeliveryResponse']);
    Route::post('marketplace/shop-review', [AuthController::class, 'submitMarketplaceShopReview']);
    Route::get('marketplace/shop/analytics', [AuthController::class, 'shopAnalytics']);
    Route::get('marketplace/orders', [AuthController::class, 'marketplaceOrders']);
    Route::get('marketplace/orders/{id}', [AuthController::class, 'marketplaceOrderDetail']);
    Route::post('marketplace/orders/{id}/status', [AuthController::class, 'updateMarketplaceOrderStatus']);
    Route::get('marketplace/product/delete/{id}', [AuthController::class, 'deleteProduct']);

    // Marketplace Seller Bank Account Routes
    Route::post('marketplace/validate-iban', [MarketplaceBankAccountController::class, 'validateIban']);
    Route::get('marketplace/financial-summary', [MarketplaceBankAccountController::class, 'financialSummary']);
    Route::get('marketplace/bank-accounts', [MarketplaceBankAccountController::class, 'getBankAccounts']);
    Route::get('marketplace/bank-accounts/{id}', [MarketplaceBankAccountController::class, 'showBankAccount']);
    Route::post('marketplace/save-bank-account', [MarketplaceBankAccountController::class, 'saveBankAccount']);
    Route::post('marketplace/bank-accounts', [MarketplaceBankAccountController::class, 'saveBankAccount']);
    Route::post('marketplace/bank-accounts/{id}/update', [MarketplaceBankAccountController::class, 'updateBankAccount']);
    Route::put('marketplace/bank-accounts/{id}', [MarketplaceBankAccountController::class, 'updateBankAccount']);
    // ================================================================== Marketplace Routes End=================================================================


    // Notifications
    Route::post('store-fcm-token', [NotificationController::class, 'store_fcm_token']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('get-notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread', [NotificationController::class, 'unread']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unread_count']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'mark_as_read']);
    Route::post('notifications/read-all', [NotificationController::class, 'mark_all_as_read']);

    // Tap Payments API Routes
    Route::post('jobs/{job}/bids/{bid}/initiate-payment', [PaymentController::class, 'initiatePayment']);
    Route::post('payments/charge', [PaymentController::class, 'charge']);
    Route::get('payments/{payment}/status', [PaymentController::class, 'status']);
    Route::get('payments/callback', [PaymentController::class, 'callback']);

    // Marketplace Tap Payment Routes
    Route::post('marketplace/orders/{order}/initiate-payment', [MarketplacePaymentController::class, 'initiatePayment']);
    Route::post('marketplace/payments/charge', [MarketplacePaymentController::class, 'charge']);

    // Withdrawal & Transaction History Routes
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

    // Admin Withdrawal Action APIs
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

    // Dedicated Customer Refund Request & History Specification Routes
    Route::post('customer/refunds/request', [CustomerRefundController::class, 'requestRefund']);
    Route::post('orders/{order_id}/refund', [CustomerRefundController::class, 'requestRefund']);
    Route::post('refunds/request', [CustomerRefundController::class, 'requestRefund']);
    Route::get('customer/refunds', [CustomerRefundController::class, 'getRefunds']);
    Route::get('refunds', [CustomerRefundController::class, 'getRefunds']);
    Route::get('customer/refunds/{id}', [CustomerRefundController::class, 'showRefund']);

    // Admin Refund Action APIs
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
