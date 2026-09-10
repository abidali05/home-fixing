# Extra Work & Post-Completion Payment: Module-Wise & API-Wise Implementation Plan

Is document me complete flow ko **Module-Wise** aur **API-Wise** divide kiya gaya hai taake aapki instructions ke mutabiq step-by-step implement kiya ja sake.

---

## 📌 Financial Calculation Rules (Summary):
- **Customer Side**:
  - `Base Amount`: Original Bid Price + Accepted Extra Amount (agar customer ne accept kiya).
  - `Customer App Fee`: **3 SAR** (Fixed).
  - `Customer Total Charged`: `Base Amount + 3 SAR` (e.g. 100 + 3 = 103 SAR).
- **Provider Side**:
  - `Provider Commission Fee`: **Fixed SAR (e.g. 5 SAR)** - *Percentage nahi hai, Admin setting me fixed SAR rahega*.
  - `Payment Gateway Fee + VAT`: ~2.961 SAR (2.5% + 15% VAT).
  - `Provider Net Earning`: `Base Amount - Provider Fee (5 SAR) - Gateway Fee (~3 SAR)`.

---

## 📦 Module 1: Database & Model Foundation
Add schema columns and model helpers to store and cast extra work attributes.

### Database Changes:
- **Migration**: `database/migrations/xxxx_add_extra_amount_columns_to_orders_table.php`
  - `extra_amount` (decimal 10,2, default `0.00`)
  - `extra_amount_reason` (text, nullable)
  - `extra_amount_status` (enum: `'none'`, `'pending'`, `'accepted'`, `'rejected'`, default `'none'`)
  - `total_amount` (decimal 10,2, default `0.00`)

### Model Changes:
- **File**: [`app/Models/Orders.php`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Models/Orders.php)
  - Attributes casting: `extra_amount` => `'decimal:2'`, `total_amount` => `'decimal:2'`.
  - Helper method `getFinalBasePriceAttribute()`: returns `price + (extra_amount_status === 'accepted' ? extra_amount : 0)`.

---

## ⚙️ Module 2: Admin System Settings (Provider Fixed Fee in SAR)
Revert Provider Commission Fee from percentage back to Fixed SAR.

### Changes:
- **Blade View**: [`resources/views/admin/system_setting/index.blade.php`](file:///c:/laragon/www/stack-buffers/home-fixing/resources/views/admin/system_setting/index.blade.php)
  - Label: `"Provider Commission Fee (SAR)"` (not `%`).
  - Input field placeholder: `"5.00 SAR"`, remove max="100" restriction.
  - Summary preview: show `SAR` instead of `%`.
- **Backend Controller**: [`app/Http/Controllers/Admin/SystemSettingController.php`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Http/Controllers/Admin/SystemSettingController.php)
  - Validation: `numeric|min:0` (allow fixed SAR values).

---

## 🔔 Module 3: Push Notifications (FCM)
Create dedicated FCM notifications matching PDF Spec Section 06.

### Components:
- **Notification 1**: `App\Notifications\ExtraPaymentRequestedNotification`
  - **Recipient**: Customer.
  - **Title**: `"Extra Work Approval Request"`
  - **Body**: `"Provider requested SAR {extra_amount} for additional work."`
  - **FCM Data**:
    ```json
    {
      "type": "EXTRA_PAYMENT_REQUEST",
      "order_id": "123",
      "extra_amount": "50.00",
      "extra_amount_reason": "Replaced damaged valve"
    }
    ```
- **Notification 2**: `App\Notifications\ExtraPaymentDecisionNotification`
  - **Recipient**: Provider.
  - **Title**: `"Extra Charge Decision"`
  - **Body**: `"Customer has {accepted/rejected} your extra charge of SAR {extra_amount}."`
  - **FCM Data**:
    ```json
    {
      "type": "EXTRA_PAYMENT_RESPONSE",
      "order_id": "123",
      "decision": "accepted"
    }
    ```

---

## 🚀 Module 4: Provider Order Completion & Extra Work Declaration
Provider marks work completed and optionally declares extra charges.

### API 4.1: `POST /api/v1/update-order-status/{id}`
- **Controller**: [`app/Http/Controllers/Api/GeneralContoller.php`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Http/Controllers/Api/GeneralContoller.php) -> `update_order_status`
- **Inputs**:
  - `status`: `'provider_completed'`
  - `extra_amount`: numeric, min:0 (optional, default `0.00`)
  - `extra_amount_reason`: string (required if `extra_amount > 0`)
- **Logic**:
  - Agar `extra_amount > 0`:
    - `order->extra_amount = $request->extra_amount`
    - `order->extra_amount_reason = $request->extra_amount_reason`
    - `order->extra_amount_status = 'pending'`
    - Dispatch `ExtraPaymentRequestedNotification` to Customer.
  - Agar `extra_amount == 0` ya missing:
    - `order->extra_amount_status = 'none'`
- **Response**:
  - Extends existing response with: `id`, `status`, `price`, `extra_amount`, `extra_amount_reason`, `extra_amount_status`, `total_amount`.

---

## ⚖️ Module 5: Customer Extra Amount Decision (New API)
Customer accepts or rejects provider's extra amount request.

### API 5.1: `POST /api/v1/orders/{order_id}/extra-amount-action`
- **Route**: Added in [`routes/api.php`](file:///c:/laragon/www/stack-buffers/home-fixing/routes/api.php)
- **Controller**: `OrdersController::extraAmountAction` or `GeneralContoller::extraAmountAction`
- **Inputs**:
  - `action`: `'accept'` ya `'reject'` (required)
  - `rejection_reason`: string (optional if reject)
- **Guards**:
  - Customer ownership check (`auth()->id() == order->user_id`).
  - Status guard: strictly allowed only if `order->extra_amount_status === 'pending'`.
- **Logic**:
  - If `accept`:
    - `order->extra_amount_status = 'accepted'`
    - `order->total_amount = $order->price + $order->extra_amount`
    - Dispatch `ExtraPaymentDecisionNotification` (decision: `'accepted'`) to Provider.
  - If `reject`:
    - `order->extra_amount_status = 'rejected'`
    - `order->total_amount = $order->price`
    - Dispatch `ExtraPaymentDecisionNotification` (decision: `'rejected'`) to Provider.
- **Response**:
  ```json
  {
    "status": true,
    "message": "Extra charge decision recorded successfully.",
    "data": {
      "order_id": 123,
      "extra_amount_status": "accepted",
      "original_amount": "150.00",
      "extra_amount": "50.00",
      "final_total": "200.00"
    }
  }
  ```

---

## 🔍 Module 6: Order Details, Tracking & Receipt APIs (4 Keys Integration)
Return `extra_amount`, `extra_amount_reason`, `extra_amount_status`, and `total_amount` across order inspection endpoints.

### API 6.1: `GET /api/v1/track-order/{id}`
- **Controller**: [`GeneralContoller::track_order`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Http/Controllers/Api/GeneralContoller.php)
- **Details**:
  - Return the 4 extra keys on `order` object.
  - Payment breakdown calculation:
    - `repair_price`: original bid price
    - `extra_amount`: extra amount
    - `extra_amount_status`: status
    - `accepted_extra`: extra amount agar status accepted ho, warna 0.00
    - `customer_app_fee`: 3.00 SAR
    - `total`: `repair_price + accepted_extra + 3.00 SAR`

### API 6.2: `GET /api/v1/service-request-details/{id}`
- **Controller**: [`HiringController::service_request_details`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Http/Controllers/Api/User/HiringController.php)
- **Details**:
  - Attach the 4 extra keys to the associated order.

### API 6.3: `GET /api/v1/orders/{id}/receipt`
- **Controller**: [`OrdersController::getReceipt`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Http/Controllers/Api/User/OrdersController.php)
- **Details**:
  - Include extra work line item in financial breakdown:
    - `original_price`
    - `extra_amount`
    - `customer_app_fee`: 3.00 SAR
    - `provider_commission_fee`: 5.00 SAR (Fixed)
    - `total_paid_by_customer`: `Final Base + 3.00 SAR`

---

## 📋 Module 7: Home & Order List APIs
Return the 4 extra keys in customer and provider lists.

### API 7.1: `GET /api/v1/user-home`
- **Controller**: [`GeneralContoller::user_home`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Http/Controllers/Api/GeneralContoller.php)
- Include the 4 extra keys in `active_orders`.

### API 7.2: `GET /api/v1/provider-home`
- **Controller**: [`GeneralContoller::provider_home`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Http/Controllers/Api/GeneralContoller.php)
- Include the 4 extra keys in `orders`.

### API 7.3: `GET /api/v1/my-orders`
- **Controller**: [`OrdersController::my_orders`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Http/Controllers/Api/User/OrdersController.php)
- Include the 4 extra keys in customer order cards.

### API 7.4: `GET /api/v1/provider-orders`
- **Controller**: [`GeneralContoller::my_orders`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Http/Controllers/Api/GeneralContoller.php)
- Include the 4 extra keys in provider order cards.
- Net earnings calculation based on fixed Provider Fee (5 SAR).

---

## 💳 Module 8: Post-Completion Payment Flow & Tap Settlement
Update payment flow so customer pays **after** completion.

### API 8.1: `POST /api/v1/jobs/{job}/bids/{bid}/initiate-payment`
- **Controller**: [`PaymentController::initiatePayment`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Http/Controllers/Api/PaymentController.php)
- **Changes**:
  - Do NOT reject if job is in `quoted` or order is in `completed` status.
  - Final Base Price = `bid->price + (order->extra_amount_status === 'accepted' ? order->extra_amount : 0)`.
  - Customer Total Payable = `Final Base Price + 3.00 SAR (App Fee)`.
  - Amount stored in Payment record.

### API 8.2: `POST /api/v1/payments/charge` & `TapPaymentService::verifyCharge`
- **Service**: [`app/Services/Payment/TapPaymentService.php`](file:///c:/laragon/www/stack-buffers/home-fixing/app/Services/Payment/TapPaymentService.php)
- **Changes**:
  - When Tap status is `CAPTURED`:
    - `payment->update(['status' => 'captured'])`.
    - `order->update(['paid_to_system' => 1])`.
    - Provider is already hired; mark completion and trigger settlement.
    - No duplicate hiring errors.

### API 8.3: Settlement & Wallet Calculation (`WithdrawalController.php`)
- Calculate Provider earnings using fixed Provider Commission Fee (5 SAR) instead of percentage.

---

## 🎯 Implementation Order:
Aap jis sequence me instruction dein ge, hum us Module/API ko implement karte jayenge:
1. **Module 1**: Database & Model
2. **Module 2**: Admin Setting (5 SAR Fixed)
3. **Module 3**: FCM Notifications
4. **Module 4**: Provider Completion API (`update-order-status`)
5. **Module 5**: Customer Extra Decision API (`extra-amount-action`)
6. **Module 6**: Tracking, Details & Receipt APIs
7. **Module 7**: Home & Orders List APIs
8. **Module 8**: Payment Initiation & Post-Completion Settlement
